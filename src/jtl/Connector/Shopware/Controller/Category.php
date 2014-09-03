<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Exception\DatabaseException;
use \Shopware\Components\Api\Manager as ShopwareManager;
use \Shopware\Models\Category\Category as CategoryShopware;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;

/**
 * Category Controller
 * @access public
 */
class Category extends DataController
{
    /**
     * Pull
     * 
     * @param \jtl\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();

            $offset = $queryFilter->isOffset() ? $queryFilter->getOffset() : 0;
            $limit = $queryFilter->isLimit() ?  $queryFilter->getLimit() : 100;

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
                    $category = Mmc::getModel('Category');
                    $category->map(true, DataConverter::toObject($categorySW, true));

                    $categoryObj = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                        ->findOneById($categorySW['id']);

                    // Level
                    $category->setLevel($categoryObj->getLevel());

                    // Attributes
                    /*
                     * @todo: waiting for entity
                    $attributeExists = false;
                    if (isset($categorySW['attribute']) && is_array($categorySW['attribute'])) {
                        $attributeExists = true;
                        for ($i = 1; $i <= 6; $i++) {
                            if (isset($categorySW['attribute']["attribute{$i}"]) && strlen(trim($categorySW['attribute']["attribute{$i}"]))) {
                                $categoryAttr = Mmc::getModel('CategoryAttr');
                                $categoryAttr->map(true, DataConverter::toObject($categorySW['attribute'], true));

                                $container->add('category_attr', $categoryAttr, false);

                                $categoryAttrI18n = Mmc::getModel('CategoryAttrI18n');
                                $categoryAttrI18n->map(true, DataConverter::toObject($categorySW['attribute'], true));
                                $categoryAttrI18n->_localName = Shopware()->Shop()->getLocale()->getLocale();
                                $categoryAttrI18n->_key = "attribute{$i}";
                                $categoryAttrI18n->_value = $categorySW['attribute']["attribute{$i}"];

                                $container->add('category_attr_i18n', $categoryAttrI18n, false);
                            }
                        }
                    }
                    */

                    // Invisibility
                    if (isset($categorySW['customerGroups']) && is_array($categorySW['customerGroups'])) {
                        foreach ($categorySW['customerGroups'] as $customerGroup) {
                            $categoryInvisibility = Mmc::getModel('CategoryInvisibility');
                            $categoryInvisibility->setCustomerGroupId(new Identity($customerGroup['id']))
                                ->setCategoryId($category->getId());

                            $category->addInvisibility($categoryInvisibility);
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

                    $this->addPos($category, 'addI18n', 'CategoryI18n', $categorySW);

                    // Default locale hack
                    if ($categorySW['localeName'] != Shopware()->Shop()->getLocale()->getLocale()) {
                        $categorySW['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                        $this->addPos($category, 'addI18n', 'CategoryI18n', $categorySW);
                    }

                    $result[] = $category->getPublic();
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

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
}