<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Product extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->builder->select(array(
            'article',
            'tax',
            'categories',
            'details',
            'maindetail',
            'detailprices',
            'prices',
            'links',
            'attribute',
            'downloads',
            'supplier',
            'related',
            'pricegroup',
            'customergroups'
        ))
        ->from('Shopware\Models\Article\Article', 'article')
        ->leftJoin('article.tax', 'tax')
        ->leftJoin('article.categories', 'categories')
        ->leftJoin('article.details', 'details')
        ->leftJoin('article.mainDetail', 'maindetail')
        ->leftJoin('details.prices', 'detailprices')
        ->leftJoin('maindetail.prices', 'prices')
        ->leftJoin('article.links', 'links')
        ->leftJoin('article.attribute', 'attribute')
        ->leftJoin('article.downloads', 'downloads')
        ->leftJoin('article.supplier', 'supplier')
        ->leftJoin('article.related', 'related')
        ->leftJoin('article.priceGroup', 'pricegroup')
        ->leftJoin('article.customerGroups', 'customergroups')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery();

        /*
            'configuratorset',
            'configuratorgroups',
            'configuratoroptions'

            ->leftJoin('article.configuratorSet', 'configuratorset')
            ->leftJoin('configuratorset.groups', 'configuratorgroups')
            ->leftJoin('configuratorset.options', 'configuratoroptions')
        */

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