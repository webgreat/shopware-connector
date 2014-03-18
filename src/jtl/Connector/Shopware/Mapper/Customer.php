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

class Customer extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
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
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery();

        if ($count) {
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

            return $paginator->count();
        }
        else {
            return $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(CustomerContainer $container)
    {
        $customers = $container->getCustomers();
        $customer = $customers[0];

        $customerSW = $this->Manager()->getRepository('Shopware\Models\Customer\Customer')->find($customer->getId());

        // Customer
        $data = DataConverter::toArray(DataModel::map(false, null, $customer));

        if (isset($data['group']['id']) && intval($data['group']['id']) > 0) {
            $data['groupKey'] = $data['group']['id'];
        }

        die(print_r($data, 1));

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Customer\Customer')
    {
        $customerResource = \Shopware\Components\Api\Manager::getResource('Customer');

        try {
            return $customerResource->update($data['id'], $data);
        } catch (ApiException\NotFoundException $exc) {
            return $customerResource->create($data);
        }
    }
}