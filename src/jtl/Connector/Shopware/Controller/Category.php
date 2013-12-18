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
use \jtl\Core\Exception\DatabaseException;
use \Shopware\Components\Api\Manager as ShopwareManager;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\ModelContainer\CategoryContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;

/**
 * Category Controller
 * @access public
 */
class Category extends DataController
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

            $categoryResource = ShopwareManager::getResource('Category');
            $categories = $categoryResource->getList($offset, $limit, $filter->getFilters());

            foreach ($categories['data'] as $categorySW) {
                $container = new CategoryContainer();

                $category = Mmc::getModel('Category');
                $category->map(true, DataConverter::toObject($categorySW));

                if ($catTmp = $categoryResource->getRepository()->find($category->_id)) {
                    $category->_level = $catTmp->getLevel();

                    if ($attr = $catTmp->getAttribute()) {
                        for ($i = 1; $i <= 6; $i++) {
                            $member = "getAttribute{$i}";
                            if (strlen(trim($attr->$member())) > 0) {
                                $categoryAttr = Mmc::getModel('CategoryAttr');
                                $categoryAttr->_id = $attr->getId() . "_{$i}";
                                $categoryAttr->_categoryId = $attr->getCategoryId();
                                $categoryAttr->_localeName = Shopware()->Locale()->toString();

                                $categoryAttr->_name = "Attribute{$i}";
                                $categoryAttr->_value = $attr->$member();

                                $container->add('category_attr', $categoryAttr->getPublic(array('_fields', '_isEncrypted')), false);
                            }
                        }
                    }

                    if ($customerGroups = $catTmp->getCustomerGroups()) {
                        foreach ($customerGroups as $customerGroup) {
                            $categoryVisibility = Mmc::getModel('CategoryVisibility');
                            $categoryVisibility->_customerGroupId = $customerGroup->getId();
                            $categoryVisibility->_categoryId = $category->_id;

                            $container->add('category_visibility', $categoryVisibility->getPublic(array('_fields', '_isEncrypted')), false);
                        }
                    }
                }

                $this->addContainerPos($container, 'category_i18n', $categorySW);

                $container->add('category', $category->getPublic(array('_fields', '_isEncrypted')), false);

                $result[] = $container->getPublic(array("items"), array("_fields", "_isEncrypted"));
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