<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Category as CategoryModel;
use \jtl\Connector\Model\Identity;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Core\Logger\Logger;

class Category extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Category\Category', $id);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'category'
        )
        ->from('Shopware\Models\Category\Category', 'category');

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $es = $builder->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $entityCount = count($es);
        $lastIndex = $entityCount - 1;

        if ($count) {
            return $entityCount;
        }

        if ($entityCount > 0) {
            return $this->Manager()->createQueryBuilder()->select(array(
                'category',
                'attribute',
                'customergroup'
            ))
            ->from('Shopware\Models\Category\Category', 'category')
            //->leftJoin('category.parent', 'parent')
            ->leftJoin('category.attribute', 'attribute')
            ->leftJoin('category.customerGroups', 'customergroup')
            ->where('category.id BETWEEN :first AND :last')
            ->setParameter('first', $es[0]['id'])
            ->setParameter('last', $es[$lastIndex]['id'])
            ->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function save(DataModel $category)
    {
        $categorySW = null;

        $id = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;
        $parentId = (strlen($category->getParentCategoryId()->getEndpoint()) > 0) ? $category->getParentCategoryId()->getEndpoint() : null;

        if ($id > 0) {
            $categorySW = $this->find($id);
        }

        if ($categorySW === null) {
            $categorySW = new \Shopware\Models\Category\Category;
        }

        if ($parentId !== null) {
            $parentSW = $this->find($parentId);

            if ($parentSW) {
                $categorySW->setParent($parentSW);
            }
        }

        // I18n
        foreach ($category->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $categorySW->setName($i18n->getName());
                $categorySW->setMetaDescription($i18n->getMetaDescription());
                $categorySW->setMetaKeywords($i18n->getMetaKeywords());
                $categorySW->setCmsHeadline('');
                $categorySW->setCmsText('');
            }
        }

        // Invisibility
        $customerGroupsSW = new \Doctrine\Common\Collections\ArrayCollection;
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        $categorySW->setCustomerGroups($customerGroupsSW);
        foreach ($category->getInvisibilities() as $invisibility) {
            $customerGroupSW = $customerGroupMapper->find($invisibility->getCustomerGroupId()->getEndpoint());

            if ($customerGroupSW) {
                $customerGroupsSW->add($customerGroupSW);
            }
        }

        $categorySW->setCustomerGroups($customerGroupsSW);

        $categorySW->setPosition(1);
        $categorySW->setNoViewSelect(false);

        $violations = $this->Manager()->validate($categorySW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        // Save Category
        $this->Manager()->persist($categorySW);
        $this->flush();

        // Result
        $result = new CategoryModel;
        $result->setId(new Identity($categorySW->getId(), $category->getId()->getHost()));
        
        $categoryI18n = Mmc::getModel('CategoryI18n');
        $categoryI18n->setCategoryId($result->getId())
            ->setLocaleName(Shopware()->Shop()->getLocale()->getLocale());

        $result->addI18n($categoryI18n);

        return $result;
    }
}