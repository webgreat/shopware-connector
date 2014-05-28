<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\ModelContainer\CustomerContainer;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Logger\Logger;

class Customer extends DataMapper
{
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
}