<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class CustomerOrder extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->builder->select(array(
            'orders',
            'customer',
            'attribute',
            'details',
            'tax',
            'billing',
            'shipping',
            'countryS',
            'countryB'
        ))
        ->from('Shopware\Models\Order\Order', 'orders')
        ->leftJoin('orders.customer', 'customer')
        ->leftJoin('orders.attribute', 'attribute')
        ->leftJoin('orders.details', 'details')
        ->leftJoin('details.tax', 'tax')
        ->leftJoin('orders.billing', 'billing')
        ->leftJoin('orders.shipping', 'shipping')
        ->leftJoin('billing.country', 'countryS')
        ->leftJoin('shipping.country', 'countryB')
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
}