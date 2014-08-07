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
use \jtl\Core\Logger\Logger;
use \jtl\Core\Utilities\Money;

class Product extends DataMapper
{
    public function find($id, $full = false)
    {
        return $this->Manager()->find('Shopware\Models\Article\Article', $id);
    }

    public function findDetails($productId, $offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'detail',
                'article',
                'tax',
                'categories',
                'detailprices',
                'links',
                'attribute',
                'downloads',
                'supplier',
                'related',
                'pricegroup',
                'discounts',
                'customergroups',
                'configuratorOptions'
            )
            ->from('Shopware\Models\Article\Detail', 'detail')
            ->leftJoin('detail.article', 'article')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.categories', 'categories')
            ->leftJoin('detail.prices', 'detailprices')
            ->leftJoin('article.links', 'links')
            ->leftJoin('article.attribute', 'attribute')
            ->leftJoin('article.downloads', 'downloads')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('article.related', 'related')
            ->leftJoin('article.priceGroup', 'pricegroup')
            ->leftJoin('pricegroup.discounts', 'discounts')
            ->leftJoin('article.customerGroups', 'customergroups')
            ->leftJoin('detail.configuratorOptions', 'configuratorOptions')
            ->where('detail.articleId = :productId')
            ->setParameter('productId', $productId)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $products = iterator_to_array($paginator);

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
                'discounts',
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
            ->leftJoin('pricegroup.discounts', 'discounts')
            ->leftJoin('article.customerGroups', 'customergroups')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $products = iterator_to_array($paginator);

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

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    protected function prepareProduct(ProductContainer $container)
    {
        $product = $container->getMainModel();

        $data = DataConverter::toArray(DataModel::map(false, null, $product));

        if (intval($data['id']) == 0) {
            $data['active'] = 1;
        }

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
            $priceSW = DataConverter::toArray(DataModel::map(false, null, $productPrice));
            $priceSW['price'] = Money::AsGross($priceSW['price'], $data['tax']['tax']);

            $data['mainDetail']['prices'][] = $priceSW;
        }

        // ProductSpecialPrice
        foreach ($container->getProductSpecialPrices() as $productSpecialPrice) {
            $data['priceGroupActive'] = true;
            $data['priceGroupId'] = $productSpecialPrice->getId()->getEndpoint();
        }

        // Product2Categories
        foreach ($container->getProduct2Categories() as $product2Category) {
            $data['categories'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        $productSW = null;
        if (empty($product->getId()->getEndpoint())) {
            $productSW = $this->save($data);
        }

        return array(
            'productSW' => $productSW,
            'data' => $data
        );
    }

    public function prepareData(ProductContainer $container)
    {
        Logger::write(print_r($container, 1), Logger::DEBUG, 'database');

        $product = $container->getMainModel();
        
        $result = $this->prepareProduct($container);
        $data = $result['data'];
        $productSW = $result['productSW'];
        $productId = ($productSW !== null) ? $productSW->getId() : $product->getId()->getEndpoint();

        // Attributes
        foreach ($container->getProductAttrs() as $productAttr) {
            //$data['attribute'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        // ProductInvisibility

        // ProductVariation
        $configuratorGroupMapper = Mmc::getMapper('ConfiguratorGroup');
        $configuratorGroupMapper->prepareData($container, $productId, $data);

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Article')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        $resource = \Shopware\Components\Api\Manager::getResource('Article');

        try {
            if (!$data['id']) {
                return $resource->create($data);
            } else {
                return $resource->update($data['id'], $data);
            }
        } catch (ApiException\NotFoundException $exc) {
            return $resource->create($data);
        }
    }
}