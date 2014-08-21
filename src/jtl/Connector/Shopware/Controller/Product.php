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
            $filter = $params;

            $offset = $filter->isOffset() ? $filter->getOffset() : 0;
            $limit = $filter->isLimit() ?  $filter->getLimit() : 100;

            $fetchChilden = ($filter->isFilter('fetchChilden') && $filter->getFilter('parentId') > 0);
            $mapper = Mmc::getMapper('Product');

            $products = array();
            if ($fetchChilden) {
                $products = $mapper->findDetails($filter->isFilter('parentId'), $offset, $limit);
            } else {
                $products = $mapper->findAll($offset, $limit);
            }

            foreach ($products as $productSW) {
                try {
                    $result[] = $this->buildProduct($productSW, $fetchChilden);
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            $action->setResult($result);

            /*
            "product_file_download" => array("ProductFileDownload", "ProductFileDownloads"),
            "product_variation_invisibility" => array("ProductVariationInvisibility", "ProductVariationInvisibilities"),
            "product_variation_value_extra_charge" => array("ProductVariationValueExtraCharge", "ProductVariationValueExtraCharges"),
            "product_variation_value_invisibility" => array("ProductVariationValueInvisibility", "ProductVariationValueInvisibilities"),
            "product_variation_value_dependency" => array("ProductVariationValueDependency", "ProductVariationValueDependencies"),
            "product_specific" => array("ProductSpecific", "ProductSpecifics"),
            */
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    protected function buildProduct(array &$data, $isDetail = false)
    {
        $productSW = $data;
        if ($isDetail) {
            $productSW = $data['article'];

            $productSW['mainDetail'] = array();
            foreach (array_keys($data) as $key) {
                if (!in_array($key, array('article', 'configuratorOptions'))) {
                    $productSW['mainDetail'][$key] = $data[$key];
                }
            }
        }

        $productSW['tax']['tax'] = floatval($productSW['tax']['tax']);
        $productSW['mainDetail']['weight'] = floatval($productSW['mainDetail']['weight']);

        $product = Mmc::getModel('Product');
        $product->map(true, DataConverter::toObject($productSW, true));

        // Stock
        $product->setConsiderStock(($product->getStockLevel() > 0))
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
            $exists = false;
            foreach ($productSW['priceGroup']['discounts'] as $discount) {
                if (intval($discount['start']) != 1) {
                    continue;
                }

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
                $exists = true;
            }

            if ($exists) {
                $product->addSpecialPrice($productSpecialPrice);
            }
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
        if (isset($data['configuratorOptions']) && count($data['configuratorOptions']) > 0) {
            foreach ($data['configuratorOptions'] as $configuratorOption) {
                // @todo: fill productVarCombination
            }
        } else {
            $configuratorSetMapper = Mmc::getMapper('ConfiguratorSet');
            $configuratorSets = $configuratorSetMapper->findByProductId($productSW['id']);
            if (is_array($configuratorSets) && count($configuratorSets) > 0) {
                foreach ($configuratorSets as $cs) {

                    // ProductVariationI18n
                    foreach ($cs['configuratorSet']['groups'] as $group) {
                        $groupId = $group['id'];
                        $group['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                        $group['id'] = $product->getId()->getEndpoint() . '_' . $group['id'];
                        $group['articleId'] = $product->getId()->getEndpoint();

                        $productVariation = Mmc::getModel('ProductVariation');
                        $productVariation->map(true, DataConverter::toObject($group, true));

                        // Main Language
                        $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                        $productVariationI18n->setLocaleName(Shopware()->Shop()->getLocale()->getLocale())
                            ->setProductVariationId(new Identity($group['id']))
                            ->setName($group['name']);

                        $productVariation->addI18n($productVariationI18n);

                        if (isset($group['translations'])) {
                            foreach ($group['translations'] as $localeName => $translation) {
                                $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                $productVariationI18n->setLocaleName($localeName)
                                    ->setProductVariationId(new Identity($group['id']))
                                    ->setName($translation['name']);

                                $productVariation->addI18n($productVariationI18n);
                            }
                        }

                        // ProductVariationValueI18n
                        foreach ($cs['configuratorSet']['options'] as $option) {
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
                                ->setProductVariationValueId(new Identity($option['id']))
                                ->setName($option['name']);

                            $productVariationValue->addI18n($productVariationValueI18n);

                            if (isset($option['translations'])) {
                                foreach ($option['translations'] as $localeName => $translation) {
                                    $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                    $productVariationValueI18n->setLocaleName($localeName)
                                        ->setProductVariationValueId(new Identity($option['id']))
                                        ->setName($translation['name']);

                                    $productVariationValue->addI18n($productVariationValueI18n);
                                }
                            }

                            $productVariation->addValue($productVariationValue);
                        }

                        $product->addVariation($productVariation);
                    }
                }
            }
        }

        return $product->getPublic();
    }
}