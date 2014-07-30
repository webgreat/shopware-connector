<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Core\Utilities\DataInjector;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \jtl\Connector\Model\Identity;

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

            $mapper = Mmc::getMapper('Product');
            $products = $mapper->findAll($offset, $limit);

            foreach ($products as $productSW) {
                try {
                    $productSW['tax']['tax'] = floatval($productSW['tax']['tax']);
                    $productSW['mainDetail']['weight'] = floatval($productSW['mainDetail']['weight']);

                    $product = Mmc::getModel('Product');
                    $product->map(true, DataConverter::toObject($productSW, true));

                    // Stock
                    $product->setConsiderStock(($product->_stockLevel > 0))
                        ->setPermitNegativeStock((bool)!$productSW['lastStock']);

                    // ProductI18n
                    $this->addPos($product, 'addI18n', 'ProductI18n', $productSW);
                    if (isset($productSW['translations'])) {
                        foreach ($productSW['translations'] as $localeName => $translation) {
                            $productI18n = Mmc::getModel('ProductI18n');
                            $productI18n->setLocaleName($localeName)
                                ->setProductId($product->getId())
                                ->setName($translation['name'])
                                ->setDescription($translation['descriptionLong']);

                            $product->addI18n($productI18n);
                        }
                    }

                    // ProductPrice
                    for ($i = 0; $i < count($productSW['mainDetail']['prices']); $i++) {
                        $customerGroup = CustomerGroupUtil::getByKey($productSW['mainDetail']['prices'][$i]['customerGroupKey']);
                        $productSW['mainDetail']['prices'][$i]['customerGroupId'] = $customerGroup->getId();
                    }

                    $this->addPos($product, 'addPrice', 'ProductPrice', $productSW['mainDetail']['prices'], true);

                    // ProductSpecialPrice
                    if ($productSW['priceGroupActive'] && $productSW['priceGroup'] !== null) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['priceGroup'], array('articleId', 'active'), array($product->getId()->getEndpoint(), true));
                        $productSpecialPrice = Mmc::getModel('ProductSpecialPrice');
                        $productSpecialPrice->map(true, DataConverter::toObject($productSW['priceGroup'], true));

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
                            $specialPrice->setCustomerGroupId(new Identity($discount['customerGroupId']))
                                ->setProductSpecialPriceId(new Identity($discount['groupId']))
                                ->setPriceNet($discountPriceNet);

                            $productSpecialPrice->addSpecialPrice($specialPrice);
                        }

                        $product->addSpecialPrice($productSpecialPrice);
                    }

                    // Product2Categories
                    if (isset($productSW['categories'])) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['categories'], 'articleId', $product->getId()->getEndpoint(), true);
                        $this->addPos($product, 'addCategory', 'Product2Category', $productSW['categories'], true);
                    }

                    // Attributes
                    /*
                     * @todo: waiting on connector entity
                    $attributeExists = false;
                    for ($i = 1; $i <= 20; $i++) {
                        if (isset($productSW['mainDetail']['attribute']["attr{$i}"]) && strlen($productSW['mainDetail']['attribute']["attr{$i}"]) > 0) {
                            $attributeExists = true;
                            $productAttrI18n = Mmc::getModel('ProductAttrI18n');
                            $productAttrI18n->map(true, DataConverter::toObject($productSW['mainDetail']['attribute'], true));
                            $productAttrI18n->_key = "attr{$i}";
                            $productAttrI18n->_value = $productSW['mainDetail']['attribute']["attr{$i}"];
                            $container->add('product_attr_i18n', $productAttrI18n->getPublic(), false);
                        }
                    }

                    if ($attributeExists) {
                        $this->addContainerPos($container, 'product_attr', $productSW['mainDetail']['attribute']);
                    }
                    */

                    // ProductInvisibility
                    if (isset($productSW['customerGroups'])) {
                        DataInjector::inject(DataInjector::TYPE_ARRAY, $productSW['customerGroups'], 'articleId', $product->getId()->getEndpoint(), true);
                        $this->addPos($product, 'addInvisibility', 'ProductInvisibility', $productSW['customerGroups'], true);
                    }

                    // ProductVariation
                    $configuratorSetMapper = Mmc::getMapper('ConfiguratorSet');
                    $configuratorSets = $configuratorSetMapper->findByProductId($productSW['id']);
                    if (is_array($configuratorSets)) {
                        foreach ($configuratorSets as $cs) {
                            $configuratorconfiguratorSetsSet = $cs['configuratorSet'];

                            // ProductVariationI18n
                            foreach ($configuratorSet['groups'] as $group) {
                                $groupId = $group['id'];
                                $group['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                                $group['id'] = $product->getId()->getEndpoint() . '_' . $group['id'];
                                $group['articleId'] = $product->getId()->getEndpoint();

                                $productVariation = Mmc::getModel('ProductVariation');
                                $productVariation->map(true, DataConverter::toObject($group, true));

                                // Main Language
                                $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                $productVariationI18n->setLocaleName(Shopware()->Shop()->getLocale()->getLocale())
                                    ->setProductVariationId($group['id'])
                                    ->setName($group['name']);

                                $productVariation->addI18n($productVariationI18n);

                                if (isset($group['translations'])) {
                                    foreach ($group['translations'] as $localeName => $translation) {
                                        $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                        $productVariationI18n->setLocaleName($localeName)
                                            ->setProductVariationId($group['id'])
                                            ->setName($translation['name']);

                                        $productVariation->addI18n($productVariationI18n);
                                    }
                                }

                                // ProductVariationValueI18n
                                foreach ($configuratorSet['options'] as $option) {
                                    if ($option['groupId'] != $groupId) {
                                        continue;
                                    }

                                    $id = $option['id'];
                                    $option['id'] = $product->getId()->getEndpoint() . '_' . $option['groupId'] . '_' . $option['id'];
                                    $option['groupId'] = $product->getId()->getEndpoint() . '_' . $option['groupId'];

                                    $productVariationValue = Mmc::getModel('ProductVariationValue');
                                    $productVariationValue->map(true, DataConverter::toObject($option, true));

                                    /*
                                    $productVarCombination = Mmc::getModel('ProductVarCombination');
                                    $productVarCombination->setProductId($product->getId()->getEndpoint())
                                        ->setProductVariationId($option['groupId'])
                                        ->setProductVariationValueId($option['id']);

                                    $container->add('product_var_combination', $productVarCombination, false);
                                    */

                                    // Main Language
                                    $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                    $productVariationValueI18n->setLocaleName(Shopware()->Shop()->getLocale()->getLocale())
                                        ->setProductVariationValueId($option['id'])
                                        ->setName($option['name']);

                                    $productVariationValue->addI18n($productVariationValueI18n);

                                    if (isset($option['translations'])) {
                                        foreach ($option['translations'] as $localeName => $translation) {
                                            $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                            $productVariationValueI18n->setLocaleName($localeName)
                                                ->setProductVariationValueId($option['id'])
                                                ->setName($translation['name']);

                                            $productVariationValue->addI18n($productVariationValueI18n);
                                        }
                                    }

                                    $productVariation->addValue($productVariationValue);
                                }

                                $product->addVariation($productVariation);
                            }
                        }

                        // Handle children
                        //$this->addChildren($container, $productSW);
                    }

                    $result[] = $product->getPublic();
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
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

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

            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }

    /**
     * Insert
     *
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @return \jtl\Connector\ModelContainer\ProductContainer
     */
    public function insert(CoreContainer $container)
    {
        $config = $this->getConfig();

        $mapper = Mmc::getMapper('Product');
        $data = $mapper->prepareData($container);
        $modelSW = $mapper->save($data);

        $resultContainer = new ProductContainer();

        // Product
        $main = $container->getMainModel();
        $resultContainer->addIdentity('product', new Identity($modelSW->getId(), $main->getId()->getHost()));

        // Product2Category
        foreach ($modelSW->getCategories() as $categorySW) {
            $resultContainer->addIdentity('product2_category', new Identity(sprintf('%s_%s', $modelSW->getId(), $categorySW->getId()), $main->getId()->getHost()));
        }

        // ProductAttr
        $attrSW = $modelSW->getAttribute();
        if ($attrSW) {
            $productAttrs = $container->getProductAttrs();
            if (isset($productAttrs[0])) {
                $resultContainer->addIdentity('product_attr', new Identity($attrSW->getId(), $productAttrs[0]->getId()->getHost()));
            }
        }

        // ProductSpecialPrice
        $priceGroupSW = $modelSW->getPriceGroup();
        if ($priceGroupSW) {
            $productSpecialPrices = $container->getProductSpecialPrices();
            if (isset($productSpecialPrices[0])) {
                $resultContainer->addIdentity('product_special_price', new Identity($priceGroupSW->getId(), $productSpecialPrices[0]->getId()->getHost()));
            }
        }

        // ProductVariation
        /*
        $setSW = $modelSW->getConfiguratorSet();
        if ($setSW) {
            foreach ($setSW->getGroups() as $groupSW) {
                $resultContainer->addIdentity('product_variation', new Identity(sprintf('%s_%s', $modelSW->getId(), $groupSW->getId()), $productSpecialPrices[0]->getId()->getHost()));
            }
        }

        // ProductVariationValue
        */

        //\Doctrine\Common\Util\Debug::dump($modelSW);

        return $resultContainer;
    }
}