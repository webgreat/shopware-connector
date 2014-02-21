<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Shop extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(array(
            'shop',
            'locale',
            'category',
            'currencies'
        ))
        ->from('Shopware\Models\Shop\Shop', 'shop')
        ->leftJoin('shop.locale', 'locale')
        ->leftJoin('shop.category', 'category')
        ->leftJoin('shop.currencies', 'currencies');

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $query = $builder->getQuery();

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

    public function save(array $array, $namespace = '\Shopware\Models\Shop\Shop')
    {
        return parent::save($array, $namespace);
    }
}