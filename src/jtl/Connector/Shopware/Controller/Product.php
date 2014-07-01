<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Core\Exception\TransactionException;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Core\Utilities\DataInjector;
use \jtl\Connector\ModelContainer\ProductContainer;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;

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

            //$articleResource = \Shopware\Components\Api\Manager::getResource('Article');

            $mapper = Mmc::getMapper('Product');
            $products = $mapper->findAll($offset, $limit);

            foreach ($products as $productSW) {
                try {
                    //$productSW = $articleResource->getOne($productSW['id']);

                    $container = new ProductContainer();

                    $product = Mmc::getModel('Product');
                    $product->map(true, DataConverter::toObject($productSW));

                    // Stock
                    $product->_considerStock = ($product->_stockLevel > 0);
                    $product->_permitNegativeStock = (bool)!$productSW['lastStock'];

                    // ProductI18n
                    $this->addContainerPos($container, 'product_i18n', $productSW);
                    if (isset($productSW['translations'])) {
                        foreach ($productSW['translations'] as $localeName => $translation) {
                            $productI18n = Mmc::getModel('ProductI18n');
                            $productI18n->setLocaleName($localeName)
                                ->setProductId($product->getId())
                                ->setName($translation['name'])
                                ->setDescription($translation['descriptionLong']);

                            $container->add('product_i18n', $productI18n, false);
                        }
                    }

                    // ProductPrice
                    $this->addContainerPos($container, 'product_price', $productSW['mainDetail']['prices'], true);

                    // ProductSpecialPrice
                    if ($productSW['priceGroupActive']) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['priceGroup'], array('articleId', 'active'), array($product->_id, true));
                        $this->addContainerPos($container, 'product_special_price', $productSW['priceGroup']);

                        // SpecialPrices
                        foreach ($productSW['priceGroup']['discounts'] as $discount) {
                            $customerGroup = CustomerGroupUtil::get($discount['customerGroupId']);
                            $price = null;
                            $priceCount = count($productSW['mainDetail']['prices']);

                            if ($priceCount == 1) {
                                $price = reset($productSW['mainDetail']['prices']);
                            } elseif ($priceCount > 1) {
                                foreach ($productSW['mainDetail']['prices'] as $mainPrice) {
                                    if ($mainPrice['customerGroupKey'] == $customerGroup->getKey()) {
                                        $price = $mainPrice;

                                        break;
                                    }
                                }
                            } else {
                                Logger::write(sprintf('Could not find any price for customer group (%s)', $customerGroup->getKey()), Logger::WARNING, 'controller');

                                continue;
                            }

                            // Calling shopware core method
                            $discountPriceNet = Shopware()->Modules()->Articles()->sGetPricegroupDiscount(
                                $customerGroup->getKey(),
                                $discount['groupId'],
                                $price['price'],
                                1,
                                false
                            );

                            $specialPrice = Mmc::getModel('SpecialPrice');
                            $specialPrice->_customerGroupId = $discount['customerGroupId'];
                            $specialPrice->_productSpecialPriceId = $discount['groupId'];
                            $specialPrice->_priceNet = $discountPriceNet;

                            $container->add('special_price', $specialPrice, false);
                        }
                    }

                    // Product2Categories
                    if (isset($productSW['categories'])) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['categories'], 'articleId', $product->_id, true);
                        $this->addContainerPos($container, 'product2_category', $productSW['categories'], true);
                    }

                    // Attributes
                    $attributeExists = false;
                    for ($i = 1; $i <= 20; $i++) {
                        if (isset($productSW['mainDetail']['attribute']["attr{$i}"]) && strlen($productSW['mainDetail']['attribute']["attr{$i}"]) > 0) {
                            $attributeExists = true;
                            $productAttrI18n = Mmc::getModel('ProductAttrI18n');
                            $productAttrI18n->map(true, DataConverter::toObject($productSW['mainDetail']['attribute']));
                            $productAttrI18n->_key = "attr{$i}";
                            $productAttrI18n->_value = $productSW['mainDetail']['attribute']["attr{$i}"];
                            $container->add('product_attr_i18n', $productAttrI18n->getPublic(), false);
                        }
                    }

                    if ($attributeExists) {
                        $this->addContainerPos($container, 'product_attr', $productSW['mainDetail']['attribute']);
                    }

                    // ProductInvisibility
                    if (isset($productSW['customerGroups'])) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['customerGroups'], 'articleId', $product->_id, true);
                        $this->addContainerPos($container, 'product_invisibility', $productSW['customerGroups'], true);
                    }

                    // ProductVariation
                    $configuratorSetMapper = Mmc::getMapper('ConfiguratorSet');
                    $configuratorSets = $configuratorSetMapper->findByProductId($productSW['id']);
                    if (is_array($configuratorSets)) {
                        foreach ($configuratorSets as $cs) {
                            $configuratorSet = $cs['configuratorSet'];

                            // ProductVariationI18n
                            foreach ($configuratorSet['groups'] as $group) {
                                $group['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                                $group['id'] = "{$product->_id}_" . $group['id'];
                                $group['articleId'] = $product->_id;

                                $this->addContainerPos($container, 'product_variation', $group, false);

                                // Main Language
                                $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                $productVariationI18n->setLocaleName(Shopware()->Shop()->getLocale()->getLocale())
                                    ->setProductVariationId($group['id'])
                                    ->setName($group['name']);

                                $container->add('product_variation_i18n', $productVariationI18n, false);

                                if (isset($group['translations'])) {
                                    foreach ($group['translations'] as $localeName => $translation) {
                                        $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                        $productVariationI18n->setLocaleName($localeName)
                                            ->setProductVariationId($group['id'])
                                            ->setName($translation['name']);

                                        $container->add('product_variation_i18n', $productVariationI18n, false);
                                    }
                                }
                            }

                            // ProductVariationValueI18n
                            foreach ($configuratorSet['options'] as $option) {
                                $id = $option['id'];
                                $option['id'] = "{$product->_id}_" . $option['groupId'] . '_' . $option['id'];
                                $option['groupId'] = "{$product->_id}_" . $option['groupId'];

                                // ProductVariationValue
                                $this->addContainerPos($container, 'product_variation_value', $option, false);

                                $productVarCombination = Mmc::getModel('ProductVarCombination');
                                $productVarCombination->setProductId($product->_id)
                                    ->setProductVariationId($option['groupId'])
                                    ->setProductVariationValueId($option['id']);

                                //$container->add('product_var_combination', $productVarCombination, false);

                                // Main Language
                                $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                $productVariationValueI18n->setLocaleName(Shopware()->Shop()->getLocale()->getLocale())
                                    ->setProductVariationValueId($option['id'])
                                    ->setName($option['name']);

                                $container->add('product_variation_value_i18n', $productVariationValueI18n, false);

                                if (isset($option['translations'])) {
                                    foreach ($option['translations'] as $localeName => $translation) {
                                        $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                        $productVariationValueI18n->setLocaleName($localeName)
                                            ->setProductVariationValueId($option['id'])
                                            ->setName($translation['name']);

                                        $container->add('product_variation_value_i18n', $productVariationValueI18n, false);
                                    }
                                }
                            }
                        }

                        // Handle children
                        //$this->addChildren($container, $productSW);
                    }

                    $container->add('product', $product, false);

                    $result[] = $container->getPublic(array('items'));
                } catch (\Exception $exc) { 
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            /*
            "product_file_download" => array("ProductFileDownload", "ProductFileDownloads"),
            "product_variation_invisibility" => array("ProductVariationInvisibility", "ProductVariationInvisibilities"),
            "product_variation_value_extra_charge" => array("ProductVariationValueExtraCharge", "ProductVariationValueExtraCharges"),
            "product_variation_value_invisibility" => array("ProductVariationValueInvisibility", "ProductVariationValueInvisibilities"),
            "product_variation_value_dependency" => array("ProductVariationValueDependency", "ProductVariationValueDependencies"),
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
     * Handle children
     *
     * @param \jtl\Connector\ModelContainer\ProductContainer $container
     * @param multiple string $product
     */
    /*
    protected function addChildren(ProductContainer &$container, array &$productSW)
    {
        if (is_array($productSW['details']) && count($productSW['details']) > 0) {
            foreach ($productSW['details'] as $detail) {
                $product = Mmc::getModel('Product');

                $product->_id = $detail['id'];
                $product->_masterProductId = $productSW['id'];
                $product->_manufacturerId = 'supplierId'
                $product->_unitId = array('mainDetail', 'unitId')
                $product->_taxClassId = array('tax', 'id')
                $product->_sku = array('mainDetail', 'number')
                $product->_stockLevel = array('mainDetail', 'inStock')
                $product->_vat = array('tax', 'tax')
                $product->_minimumOrderQuantity = array('mainDetail', 'minPurchase')
                $product->_ean = array('mainDetail', 'ean'),
                $product->_productWeight = array('mainDetail', 'weight')
                $product->_keywords = 'keywords'
                $product->_created = 'added'
                $product->_availableFrom = 'availableFrom'
                $product->_takeOffQuantity = array('mainDetail', 'purchaseSteps')

                die(print_r($detail, 1));

                $container->add('product', $product, false);
            }
        }
    }
    */

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
            $result = $this->insert($container);

            if ($result !== null) {
                $action->setResult($result->getPublic());
            }
        }
        catch (\Exception $exc) {
            $message = (strlen($exc->getMessage()) > 0) ? $exc->getMessage() : ExceptionFormatter::format($exc);

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }
}