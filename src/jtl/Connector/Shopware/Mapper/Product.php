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
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'article'
        )
        ->from('Shopware\Models\Article\Article', 'article');

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $es = $builder->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $entityCount = count($es);
        $lastIndex = $entityCount - 1;

        if ($count) {
            return $entityCount;
        }

        if ($entityCount > 0) {
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
            ->where('article.id BETWEEN :first AND :last')
            ->setParameter('first', $es[0]['id'])
            ->setParameter('last', $es[$lastIndex]['id'])
            ->getQuery();

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

        /*
            'configuratorset',
            'configuratorgroups',
            'configuratoroptions'

            ->leftJoin('article.configuratorSet', 'configuratorset')
            ->leftJoin('configuratorset.groups', 'configuratorgroups')
            ->leftJoin('configuratorset.options', 'configuratoroptions')
        */

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(ProductContainer $container)
    {
        $product = $container->getMainModel();

        //$productSW = $this->Manager()->getRepository('Shopware\Models\Article\Article')->find($product->getId());

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

        // Product2Categories
        foreach ($container->getProduct2Categories() as $product2Category) {
            $data['categories'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        // Attributes
        foreach ($container->getProductAttrs() as $productAttr) {
            //$data['attribute'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        // ProductInvisibility

        // ProductVariation
        if (count($container->getProductVariations()) > 0) {
            $data['configuratorSet'] = array();

            foreach ($container->getProductVariations() as $productVariation) {
                list($productId, $groupId) = explode('_', $productVariation->getId());

                $data['configuratorSet']['groups'][$groupId] = DataConverter::toArray(DataModel::map(false, null, $productVariation));
            }

            // ProductVariationI18n
            foreach ($container->getProductVariationI18ns() as $productVariationI18n) {
                list($productId, $groupId) = explode('_', $productVariationI18n->getProductVariationId());

                // Main language
                if ($productVariationI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                    $data['configuratorSet']['groups'][$groupId]['name'] = $productVariationI18n->getName();
                } else {
                    if (!isset($data['configuratorSet']['groups'][$groupId]['translations'])) {
                        $data['configuratorSet']['groups'][$groupId]['translations'] = array();
                    }

                    $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLocaleName()] = array();
                    $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLocaleName()]['name'] = $productVariationI18n->getName();
                    $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLocaleName()]['groupId'] = $groupId;
                }

                $data['configuratorSet']['groups'][$groupId]['id'] = $groupId;
            }

            // ProductVariationValue
            foreach ($container->getProductVariationValues() as $productVariationValue) {
                list($productId, $groupId, $optionId) = explode('_', $productVariationValue->getId());

                $data['configuratorSet']['options'][$optionId]['id'] = $optionId;
                $data['configuratorSet']['options'][$optionId]['groupId'] = $groupId;
            }

            // ProductVariationValueI18n
            foreach ($container->getProductVariationValueI18ns() as $productVariationValueI18n) {
                list($productId, $groupId, $optionId) = explode('_', $productVariationValueI18n->getProductVariationValueId());

                if ($productVariationValueI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                    $data['configuratorSet']['options'][$optionId]['name'] = $productVariationValueI18n->getName();
                } else {
                    if (!isset($data['configuratorSet']['options'][$optionId]['translations'])) {
                        $data['configuratorSet']['options'][$optionId]['translations'] = array();
                    }

                    $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLocaleName()] = array();
                    $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLocaleName()]['name'] = $productVariationValueI18n->getName();
                    $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLocaleName()]['optionId'] = $optionId;
                }
            }
        }

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Article')
    {
        $articleResource = \Shopware\Components\Api\Manager::getResource('Article');

        try {
            return $articleResource->update($data['id'], $data);
        } catch (ApiException\NotFoundException $exc) {
            return $articleResource->create($data);
        }
    }
}