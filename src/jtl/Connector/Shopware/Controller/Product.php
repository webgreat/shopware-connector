<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \Shopware\Components\Api\Manager as ShopwareManager;
use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;
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

            $articleResource = ShopwareManager::getResource('Article');
            $products = $articleResource->getList($offset, $limit, $filter->getFilters());

            foreach ($products['data'] as $productSW) {
                $container = new ProductContainer();

                $productSW = $articleResource->getOne($productSW['id']);

                $product = Mmc::getModel('Product');
                $product->map(true, DataConverter::toObject($productSW));

                $this->addContainerPos($container, 'product_i18n', $productSW);
                $this->addContainerPos($container, 'product_price', $productSW['mainDetail']['prices'], true);
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

                // Product2Categories
                $product2Categories = $container->get('product2_category');
                if ($product2Categories !== null) {
                    foreach ($product2Categories as $product2Category) {
                        $product2Category->_productId = $product->_id;
                    }
                }

                $container->add('product', $product->getPublic(array('_fields', '_isEncrypted')), false);

                $result[] = $container->getPublic(array('items'), array('_fields', '_isEncrypted'));
            }

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
}