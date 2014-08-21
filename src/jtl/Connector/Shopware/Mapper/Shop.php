<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;

class Shop extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'shop',
                'locale',
                'category',
                'currencies'
            )
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->leftJoin('shop.category', 'category')
            ->leftJoin('shop.currencies', 'currencies')
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

    public function save(array $data, $namespace = '\Shopware\Models\Shop\Shop')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        return parent::save($data, $namespace);
    }
}