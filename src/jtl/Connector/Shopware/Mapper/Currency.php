<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Currency extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(array(
            'currency'
        ))
        ->from('Shopware\Models\Shop\Currency', 'currency');

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $query = $builder->getQuery();

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

    public function save(array $array)
    {
        return parent::save('\Shopware\Models\Shop\Currency', $array);
    }
}