<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\ModelContainer\CategoryContainer;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;

class Category extends DataMapper
{
    public function findById($id)
    {
        
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        //'parent',

        $query = $this->Manager()->createQueryBuilder()->select(array(
            'category',            
            'attribute',
            'customergroup'
        ))
        ->from('Shopware\Models\Category\Category', 'category')
        //->leftJoin('category.parent', 'parent')
        ->leftJoin('category.attribute', 'attribute')
        ->leftJoin('category.customerGroups', 'customergroup')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery();

        if ($count) {
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

            return $paginator->count();
        }
        else {
            return $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    } 

    public function prepareData(CategoryContainer $container)
    {
        $categories = $container->getCategories();
        $category = $categories[0];

        $categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find($category->getId());

        // Category
        $data = DataConverter::toArray(DataModel::map(false, null, $category));

        if (isset($data['parentId']) && intval($data['parentId']) == 0) {
            unset($data['parentId']);
        }

        // CategoryI18n
        foreach ($container->getCategoryI18ns() as $categoryI18n) {
            // Main language
            if ($categoryI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $categoryI18n)));
            }
        }

        // CategoryAttributes
        foreach ($container->getCategoryAttrs() as $categoryAttr) {
            if (!isset($data['attribute'])) {
                $data['attribute'] = array();
            }

            $data['attribute']['id'] = $categoryAttr->getId();
            $data['attribute']['categoryId'] = $category->getId();
        }

        // CategoryAttributesI18n
        foreach ($container->getCategoryAttrI18ns() as $i => $categoryAttrI18n) {
            $data['attribute']['attribute' . ($i + 1)] = $categoryAttrI18n->getValue();
        }

        // CategoryCustomerGroups
        foreach ($container->getCategoryCustomerGroups() as $categoryCustomerGroup) {
            // TODO - $data['customerGroups']
        }

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Category\Category')
    {
        $categoryResource = \Shopware\Components\Api\Manager::getResource('Category');

        try {
            return $categoryResource->update($data['id'], $data);
        } catch (ApiException\NotFoundException $exc) {
            return $categoryResource->create($data);
        }
    }
}