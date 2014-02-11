<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Category extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
            'category',
            'parent',
            'attribute',
            'customergroup'
        ))
        ->from('Shopware\Models\Category\Category', 'category')
        ->leftJoin('category.parent', 'parent')
        ->leftJoin('category.attribute', 'attribute')
        ->leftJoin('category.customerGroups', 'customergroup')
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

    public function save(array $array)
    {
        return parent::save('\Shopware\Models\Category\Category', $array);
    }
}