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
use \Shopware\Models\Category\Category as CategoryShopware;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\ModelContainer\CategoryContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\ModelContainer\CoreContainer;

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

            $mapper = Mmc::getMapper('Category');
            $categories = $mapper->findAll($offset, $limit);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $rootCategories = array();
            $rootCategoryIds = array();
            foreach ($shops as $shop) {
                $rootCategory = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                        ->findOneById($shop['category']['id']);

                $rootCategories[$shop['locale']['locale']] = $rootCategory;
                $rootCategoryIds[] = $rootCategory->getId();
            }

            foreach ($categories as $categorySW) {
                try {
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

                                $container->add('category_attr', $categoryAttr, false);

                                $categoryAttrI18n = Mmc::getModel('CategoryAttrI18n');
                                $categoryAttrI18n->map(true, DataConverter::toObject($categorySW['attribute']));
                                $categoryAttrI18n->_localName = Shopware()->Shop()->getLocale()->getLocale();
                                $categoryAttrI18n->_key = "attribute{$i}";
                                $categoryAttrI18n->_value = $categorySW['attribute']["attribute{$i}"];

                                $container->add('category_attr_i18n', $categoryAttrI18n, false);
                            }
                        }
                    }

                    // Invisibility
                    if (isset($categorySW['customerGroups']) && is_array($categorySW['customerGroups'])) {
                        foreach ($categorySW['customerGroups'] as $customerGroup) {
                            $categoryInvisibility = Mmc::getModel('CategoryInvisibility');
                            $categoryInvisibility->_customerGroupId = $customerGroup['id'];
                            $categoryInvisibility->_categoryId = $category->_id;

                            $container->add('category_invisibility', $categoryInvisibility, false);
                        }
                    }

                    // CategoryI18n
                    if ($categoryObj->getParent() === null) {
                        $categorySW['localeName'] = Shopware()->Locale()->toString();
                    }
                    else if (in_array($categoryObj->getId(), $rootCategoryIds)) {
                        foreach ($rootCategories as $localeName => $rootCategory) {
                            if ($categoryObj->getId() == $rootCategory->getId()) {
                                $categorySW['localeName'] = $localeName;
                            }
                        }
                    }
                    else {
                        foreach ($rootCategories as $localeName => $rootCategory) {
                            if ($this->isChildOf($categoryObj, $rootCategory)) {
                                $categorySW['localeName'] = $localeName;
                                break;
                            }
                        }
                    }

                    $this->addContainerPos($container, 'category_i18n', $categorySW);

                    // Default locale hack
                    if ($categorySW['localeName'] != Shopware()->Shop()->getLocale()->getLocale()) {
                        $categorySW['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                        $this->addContainerPos($container, 'category_i18n', $categorySW);
                    }

                    $container->add('category', $category, false);

                    $result[] = $container->getPublic(array("items"));
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
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

    protected function isChildOf(CategoryShopware $category, CategoryShopware $parent)
    {
        if (!($category->getParent() instanceof CategoryShopware)) {
            return false;
        }

        if ($category->getParent()->getId() === $parent->getId()) {
            return true;
        }

        return $this->isChildOf($category->getParent(), $parent);
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

    /**
     * Insert
     *
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @return \jtl\Connector\ModelContainer\CategoryContainer
     */
    public function insert(CoreContainer $container)
    {
        $config = $this->getConfig();

        $mapper = Mmc::getMapper('Category');
        $data = $mapper->prepareData($container);
        $modelSW = $mapper->save($data);

        $resultContainer = new CategoryContainer();

        // Category
        foreach ($container->getCategories() as $category) {
            $resultContainer->addIdentity('category', new Identity($modelSW->getId(), $model->getId()->getHost()));
        }

        // Attributes
        foreach ($container->getCategoryAttrs() as $categoryAttr) {
            foreach ($modelSW->getAttribute() as $attrSW) {
                if ($attrSW->getAttribute()->getId() == $categoryAttr->getId()->getEndpoint()) {
                    $resultContainer->addIdentity('category_attr', new Identity($attrSW->getAttribute()->getId(), $categoryAttr->getId()->getHost()));
                }
            }
        }

        return $resultContainer;
    }
}