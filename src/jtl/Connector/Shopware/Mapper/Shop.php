<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Shop extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'shop'
        )
        ->from('Shopware\Models\Shop\Shop', 'shop');

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
                'shop',
                'locale',
                'category',
                'currencies'
            ))
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->leftJoin('shop.category', 'category')
            ->leftJoin('shop.currencies', 'currencies')
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

    public function save(array $data, $namespace = '\Shopware\Models\Shop\Shop')
    {
        return parent::save($data, $namespace);
    }
}