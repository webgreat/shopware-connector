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

            $mapper = Mmc::getMapper('Product');
            $products = $mapper->findAll($offset, $limit);

            foreach ($products as $productSW) {
                try {
                    $container = new ProductContainer();

                    $product = Mmc::getModel('Product');
                    $product->map(true, DataConverter::toObject($productSW));

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

                    $this->addContainerPos($container, 'product_price', $productSW['mainDetail']['prices'], true);

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
                            $container->add('product_attr_i18n', $productAttrI18n->getPublic(array("_fields", "_isEncrypted")), false);
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

                                $container->add('product_var_combination', $productVarCombination, false);

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
                    }

                    $container->add('product', $product, false);

                    $result[] = $container->getPublic(array('items'), array('_fields', '_isEncrypted'));
                }
                catch (\Exception $exc) { }
            }

            /*
            "product_file_download" => array("ProductFileDownload", "ProductFileDownloads"),
            "product_special_price" => array("ProductSpecialPrice", "ProductSpecialPrices"),
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