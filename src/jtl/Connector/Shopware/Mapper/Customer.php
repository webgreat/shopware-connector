<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

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

    public function save(array $array, $namespace = '\Shopware\Models\Customer\Customer')
    {
        return parent::save($array, $namespace);
    }
}