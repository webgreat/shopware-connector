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
        $query = $this->Manager()->createQueryBuilder()->select(
                'customer',
                'billing',
                'shipping',
                'customergroup',
                'attribute',
                'shop',
                'locale'
            )
            ->from('Shopware\Models\Customer\Customer', 'customer')
            ->leftJoin('customer.billing', 'billing')
            ->leftJoin('customer.shipping', 'shipping')
            ->leftJoin('customer.group', 'customergroup')
            ->leftJoin('billing.attribute', 'attribute')
            ->leftJoin('customer.languageSubShop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
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
            ->setBirthday($customer->getBirthday());

        $ref = new \ReflectionClass($billingSW);
        $prop = $ref->getProperty('customerId');
        $prop->setAccessible(true);
        $prop->setValue($billingSW, $customerSW->getId());

        $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $customer->getCountryIso()));
        if ($countrySW) {
            $billingSW->setCountryId($countrySW->getId());
        }

        $this->Manager()->persist($billingSW);
        $this->flush();

        // Result
        $result = new CustomerModel;
        $result->setId(new Identity($customerSW->getId(), $customer->getId()->getHost()));

        return $result;
    }
}