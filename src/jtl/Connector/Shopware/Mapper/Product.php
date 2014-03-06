<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\ModelContainer\ProductContainer;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;

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
            $products = $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationReader = new \Shopware_Components_Translation();
            for ($i = 0; $i < count($products); $i++) {
                foreach ($shops as $shop) {
                    $translation = $translationReader->read($shop['locale']['id'], 'article', $products[$i]['id']);
                    if (!empty($translation)) {
                        $translation['shopId'] = $shop['id'];
                        $products[$i]['translations'][$shop['locale']['locale']] = $translation;
                    }
                }
            }

            return $products;
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(ProductContainer $container)
    {
        foreach ($container->items as $items) {
            $getter = "get" . ucfirst($items[1]);
            $class = $items[0];
            
            $data = array();
            foreach ($container->$getter() as $model) {
                switch ($items[0]) {

                    case 'Product':
                        $arr = DataConverter::toArray(DataModel::map(false, null, $model));
                        
                        break;
                }
            }
        }
    }

    public function save(array $array, $namespace = '\Shopware\Models\Article\Article')
    {
        $articleResource = \Shopware\Components\Api\Manager::getResource('Article');

        try {
            return $articleResource->update($array['id'], $array);
        } catch (ApiException\NotFoundException $exc) {
            return $articleResource->create($array);
        }
    }
}