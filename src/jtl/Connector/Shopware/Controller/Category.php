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

            $builder = Shopware()->Models()->createQueryBuilder();

            $categories = $builder->select(array(
                    'category',
                    'parent',
                    'attribute',
                    'customergroup'
                ))
                ->from('Shopware\Models\Category\Category', 'category')
                ->leftJoin('category.parent', 'parent')
                ->leftJoin('category.attribute', 'attribute')
                ->leftJoin('category.customerGroups', 'customergroup')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            foreach ($categories as $categorySW) {
                $container = new CategoryContainer();

                $category = Mmc::getModel('Category');
                $category->map(true, DataConverter::toObject($categorySW));

                $categoryObj = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                    ->findOneById($categorySW['id']);

                $category->_level = $categoryObj->getLevel();

                // Attributes
                $attributeExists = false;
                if (isset($categorySW['attribute']) && is_array($categorySW['attribute'])) {
                    $attributeExists = true;
                    for ($i = 1; $i <= 6; $i++) {
                        if (isset($categorySW['attribute']["attribute{$i}"]) && strlen(trim($categorySW['attribute']["attribute{$i}"]))) {
                            $categoryAttr = Mmc::getModel('CategoryAttr');
                            $categoryAttr->map(true, DataConverter::toObject($categorySW['attribute']));                            

                            $container->add('category_attr', $categoryAttr->getPublic(array("_fields", "_isEncrypted")), false);

                            $categoryAttrI18n = Mmc::getModel('CategoryAttrI18n');
                            $categoryAttrI18n->map(true, DataConverter::toObject($categorySW['attribute']));
                            $categoryAttrI18n->_localName = Shopware()->Shop()->getLocale()->getLocale();
                            $categoryAttrI18n->_key = "attribute{$i}";
                            $categoryAttrI18n->_value = $categorySW['attribute']["attribute{$i}"];

                            $container->add('category_attr_i18n', $categoryAttrI18n->getPublic(array("_fields", "_isEncrypted")), false);
                        }
                    }
                }

                // Invisibility
                if (isset($categorySW['customerGroups']) && is_array($categorySW['customerGroups'])) {
                    foreach ($categorySW['customerGroups'] as $customerGroup) {
                        $categoryInvisibility = Mmc::getModel('CategoryInvisibility');
                        $categoryInvisibility->_customerGroupId = $customerGroup['id'];
                        $categoryInvisibility->_categoryId = $category->_id;

                        $container->add('category_invisibility', $categoryInvisibility->getPublic(array('_fields', '_isEncrypted')), false);
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