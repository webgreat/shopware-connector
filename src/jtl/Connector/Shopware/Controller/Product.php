<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Result\Transaction as TransactionResult;
use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Core\Exception\TransactionException;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Core\Utilities\DataInjector;
use \jtl\Connector\ModelContainer\ProductContainer;

/**
 * Product Controller
 * @access public
 */
class Product extends DataController
{
    /**
     * Pull
     * 
     * @params object $params
     * @return \jtl\Connector\Result\Action
     */
    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $filter = new QueryFilter();
            $filter->set($params);

            $offset = 0;
            $limit = 100;
            if ($filter->isOffset()) {
                $offset = $filter->getOffset();
            }

            if ($filter->isLimit()) {
                $limit = $filter->getLimit();
            }

            $builder = Shopware()->Models()->createQueryBuilder();

            $products = $builder->select(array(
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
                    'customergroups',
                    'configuratorset',
                    'configuratorgroups',
                    'configuratoroptions'
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
                ->leftJoin('article.configuratorSet', 'configuratorset')
                ->leftJoin('configuratorset.groups', 'configuratorgroups')
                ->leftJoin('configuratorset.options', 'configuratoroptions')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            foreach ($products as $productSW) {
                $container = new ProductContainer();

                $product = Mmc::getModel('Product');
                $product->map(true, DataConverter::toObject($productSW));

                $this->addContainerPos($container, 'product_i18n', $productSW);
                $this->addContainerPos($container, 'product_price', $productSW['mainDetail']['prices'], true);

                // Product2Categories
                DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['categories'], 'articleId', $product->_id, true);
                $this->addContainerPos($container, 'product2_category', $productSW['categories'], true);

                // Attributes
                $attributeExists = false;
                for ($i = 1; $i <= 20; $i++) {
                    if (isset($productSW['mainDetail']['attribute']["attr{$i}"]) && strlen($productSW['mainDetail']['attribute']["attr{$i}"]) > 0) {
                        $attributeExists = true;
                        $productAttrI18n = Mmc::getModel('ProductAttrI18n');
                        $productAttrI18n->map(true, DataConverter::toObject($productSW['mainDetail']['attribute']));
                        $productAttrI18n->_key = "attr{$i}";
                        $productAttrI18n->_value = $productSW['mainDetail']['attribute']["attr{$i}"];
                        $container->add('product_attr_i18n', $productAttrI18n->getPublic(array("_fields", "_isEncrypted")), false);
                    }
                }

                if ($attributeExists) {
                    $this->addContainerPos($container, 'product_attr', $productSW['mainDetail']['attribute']);
                }

                // ProductInvisibility
                DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['customerGroups'], 'articleId', $product->_id, true);
                $this->addContainerPos($container, 'product_invisibility', $productSW['customerGroups'], true);

                // ProductVariation
                if (is_array($productSW['configuratorSet'])) {
                    $configuratorSet = $productSW['configuratorSet'];
                    DataInjector::inject(DataInjector::TYPE_ARRAY, $configuratorSet['groups'], 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
                    DataInjector::inject(DataInjector::TYPE_ARRAY, $configuratorSet['groups'], 'articleId', $product->_id, true);
                    DataInjector::inject(DataInjector::TYPE_ARRAY, $configuratorSet['options'], 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);

                    $this->addContainerPos($container, 'product_variation', $configuratorSet['groups'], true);
                    $this->addContainerPos($container, 'product_variation_i18n', $configuratorSet['groups'], true);

                    $this->addContainerPos($container, 'product_variation_value', $configuratorSet['options'], true);
                    $this->addContainerPos($container, 'product_variation_value_i18n', $configuratorSet['options'], true);
                }

                $container->add('product', $product->getPublic(array('_fields', '_isEncrypted')), false);

                $result[] = $container->getPublic(array('items'), array('_fields', '_isEncrypted'));
            }

            /*
            "product_file_download" => array("ProductFileDownload", "ProductFileDownloads"),            
            "product_special_price" => array("ProductSpecialPrice", "ProductSpecialPrices"),
            "product_variation_invisibility" => array("ProductVariationInvisibility", "ProductVariationInvisibilities"),
            "product_variation_value_extra_charge" => array("ProductVariationValueExtraCharge", "ProductVariationValueExtraCharges"),
            "product_variation_value_invisibility" => array("ProductVariationValueInvisibility", "ProductVariationValueInvisibilities"),
            "product_variation_value_dependency" => array("ProductVariationValueDependency", "ProductVariationValueDependencies"),
            "product_var_combination" => array("ProductVarCombination", "ProductVarCombinations"),
            "product_specific" => array("ProductSpecific", "ProductSpecifics"),
            */

            $action->setResult($result);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    /**
     * Transaction Commit
     *
     * @param mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function commit($params, $trid)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $container = TransactionHandler::getContainer($this->getMethod()->getController(), $trid);
            $result = new TransactionResult();
            $result->setTransactionId($trid);

            if ($this->insert($container)) {
                $action->setResult($result->getPublic());
            }
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}