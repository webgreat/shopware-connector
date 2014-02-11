<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Product extends DataMapper
{
    protected $relations = array(
        'article' => 'Shopware\Models\Article\Article',
        'tax' => 'Shopware\Models\Tax\Tax',
        'categories' => 'Shopware\Models\Category\Category',
        'details' => 'Shopware\Models\Article\Detail',
        'mainDetail' => 'Shopware\Models\Article\Detail',
        'detailprices' => 'Shopware\Models\Article\Article',
        'prices' => 'Shopware\Models\Article\Article',
        'links' => 'Shopware\Models\Article\Link',
        'attribute' => 'Shopware\Models\Article\Article',
        'downloads' => 'Shopware\Models\Article\Download',
        'supplier' => 'Shopware\Models\Article\Supplier',
        'related' => 'Shopware\Models\Article\Article',
        'pricegroup' => 'Shopware\Models\Price\Group',
        'customergroups' => 'Shopware\Models\Customer\Group'
    );

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
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
        )
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

    public function save(array $array)
    {
        die(print_r($array, 1));

        foreach ($array as $key => $value) {
            if (is_array($value) && isset($this->relations[$key])) {
                $ns = $this->relations[$key];

                parent::save($ns, $value);
            }   
        }

        return parent::save('\Shopware\Models\Article\Article', $array);
    }
}