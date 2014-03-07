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
        $articleResource = \Shopware\Components\Api\Manager::getResource('Article');
        $products = $container->getProducts();
        $product = $products[0];

        //$productSW = $this->Manager()->getRepository('Shopware\Models\Article\Article')->find($products[0]->getId());
        $productSW = $this->Manager()->getRepository('Shopware\Models\Article\Article')->find($product->getId());

        // Product
        $data = DataConverter::toArray(DataModel::map(false, null, $product));

        // ProductI18n
        foreach ($container->getProductI18ns() as $productI18n) {

            // Main language
            if ($productI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $productI18n)));
            }
        }

        // ProductPrice
        $data['mainDetail']['prices'] = array();
        foreach ($container->getProductPrices() as $productPrice) {
            $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $product)));
            $data['mainDetail']['prices'][] = DataConverter::toArray(DataModel::map(false, null, $productPrice));
        }

        $articleResource->update($product->getId(), $data);

        die(print_r($data, 1));

        $productSW->fromArray($data);

        // Product2Categories

        // Attributes

        // ProductInvisibility

        // ProductVariation

        // ProductVariationI18n

        // ProductVariationValue

        // ProductVariationValueI18n
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