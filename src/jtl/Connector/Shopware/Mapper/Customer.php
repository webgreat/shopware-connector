<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\Customer as CustomerModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Model\Identity;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Salutation;

class Customer extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Customer\Customer', $id);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'customer'
        )
        ->from('Shopware\Models\Customer\Customer', 'customer');

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $es = $builder->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $entityCount = count($es);
        $lastIndex = $entityCount - 1;

        if ($count) {
            return $entityCount;
        }

        if ($entityCount > 0) {
            return $this->Manager()->createQueryBuilder()->select(array(
                'customer',
                'billing',
                'shipping',
                'customergroup',
                'attribute',
                'shop',
                'locale'
            ))
            ->from('Shopware\Models\Customer\Customer', 'customer')
            ->leftJoin('customer.billing', 'billing')
            ->leftJoin('customer.shipping', 'shipping')
            ->leftJoin('customer.group', 'customergroup')
            ->leftJoin('billing.attribute', 'attribute')
            ->leftJoin('customer.languageSubShop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->where('customer.id BETWEEN :first AND :last')
            ->setParameter('first', $es[0]['id'])
            ->setParameter('last', $es[$lastIndex]['id'])
            ->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(CustomerContainer $container)
    {
        $customer = $container->getMainModel();

        //$customerSW = $this->Manager()->getRepository('Shopware\Models\Customer\Customer')->find($customer->getId());

        // Customer
        $data = DataConverter::toArray(DataModel::map(false, null, $customer));

        if (isset($data['group']['id']) && intval($data['group']['id']) > 0) {
            $data['groupKey'] = $data['group']['id'];
        }

        // Billing
        if (isset($data['billing'])) {
            $billing = Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing')->findOneBy(array(
                'customerId' => $data['id']
            ));

            if (empty($billing)) {
                throw new ApiException\NotFoundException(sprintf("Billing by customerId %s not found", $data['id']));
            }

            $data['billing'] = $billing->fromArray($data['billing']);
        }

        // Shipping
        if (isset($data['shipping'])) {
            $shipping = Shopware()->Models()->getRepository('Shopware\Models\Customer\Shipping')->findOneBy(array(
                'customerId' => $data['id']
            ));

            if (empty($shipping)) {
                throw new ApiException\NotFoundException(sprintf("Shipping by customerId %s not found", $data['id']));
            }

            $data['shipping'] = $shipping->fromArray($data['shipping']);
        }

        return $data;
    }

    public function save(DataModel $customer)
    {
        $customerSW = null;
        $billingSW = null;

        $id = (strlen($customer->getId()->getEndpoint()) > 0) ? (int)$customer->getId()->getEndpoint() : null;

        if ($id > 0) {
            $customerSW = $this->find($id);
            $billingSW = $this->Manager()->getRepository('Shopware\Models\Customer\Billing')->findOneBy(array('userId' => $id));
        }

        if ($customerSW === null) {
            $customerSW = new \Shopware\Models\Customer\Customer;
        }

        // CustomerGroup
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        $customerGroupSW = $customerGroupMapper->find($customer->getCustomerGroupId()->getEndpoint());
        if ($customerGroupSW) {
            $customerSW->setGroup($customerGroupSW);
        }

        $customerSW->setEmail($customer->getEMail())
            ->setActive($customer->getIsActive())
            ->setNewsletter(intval($customer->getHasNewsletterSubscription()))
            ->setFirstLogin($customer->getCreated())
            ->setPassword(md5($customer->getPassword()));

        $violations = $this->Manager()->validate($customerSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($customerSW);
        $this->flush();

        // Billing
        if (!$billingSW) {
            $billingSW = new \Shopware\Models\Customer\Billing;
        }

        $billingSW->setCompany($customer->getCompany())
            ->setSalutation(Salutation::toEndpoint($customer->getSalutation()))
            ->setNumber($customer->getCustomerNumber())
            ->setFirstName($customer->getFirstName())
            ->setLastName($customer->getLastName())
            ->setStreet($customer->getStreet())
            ->setZipCode($customer->getZipCode())
            ->setCity($customer->getCity())
            ->setPhone($customer->getPhone())
            ->setFax($customer->getFax())
            ->setVatId($customer->getVatNumber())
            ->setBirthday($customer->getBirthday())
            ->setCustomer($customerSW);

        $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $customer->getCountryIso()));
        if ($countrySW) {
            $billingSW->setCountryId($countrySW->getId());
        }

        $this->Manager()->persist($countrySW);
        $this->flush();

        // Result
        $result = new CustomerModel;
        $result->setId(new Identity($customerSW->getId(), $customer->getId()->getHost()));

        return $result;
    }

    /*
    public function save(array $data, $namespace = '\Shopware\Models\Customer\Customer')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        $resource = \Shopware\Components\Api\Manager::getResource('Customer');

        try {
            if (!$data['id']) {
                return $resource->create($data);
            } else {
                return $resource->update($data['id'], $data);
            }
        } catch (ApiException\NotFoundException $exc) {
            return $resource->create($data);
        }
    }
    */
}