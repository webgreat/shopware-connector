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
use \jtl\Core\Logger\Logger;

class Category extends DataMapper
{
    public function findById($id)
    {
        
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

    public function prepareData(CategoryContainer $container)
    {
        $category = $container->getMainModel();

        //$categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find($category->getId());

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
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');

        $resource = \Shopware\Components\Api\Manager::getResource('Category');

        try {
            if (!$data['id']) {
                return $resource->create($data);
            } else {
                return $resource->update($data['id'], $data);
            }
        } catch (ApiException\NotFoundException $exc) {
            return $resource->create($data);
        }
    }
}