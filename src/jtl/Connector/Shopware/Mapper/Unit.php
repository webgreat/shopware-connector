<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Model\Unit as UnitModel;

class Unit extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Unit')->find($id);
    }
    
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'unit'
            )
            ->from('Shopware\Models\Article\Unit', 'unit')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function save(DataModel $unit)
    {
        $unitSW = null;

        $id = (strlen($unit->getId()->getEndpoint()) > 0) ? (int)$unit->getId()->getEndpoint() : null;

        if ($id > 0) {
            $unitSW = $this->find($id);
        }

        if ($unit->getAction() == DataModel::ACTION_DELETE) {   // Delete
            if ($unitSW !== null) {
                $this->Manager()->remove($unitSW);
                $this->flush();
            }
        } else {    // Update or Insert
            if ($unitSW === null) {
                $unitSW = new \Shopware\Models\Article\Unit;
            }

            // @todo: waiting for entity
            /*
            $unitSW->setUnit($unit->getRate())
                ->setName($unit->getRate());
            */

            $violations = $this->Manager()->validate($unitSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->Manager()->persist($unitSW);
            $this->flush();
        }

        // Result
        $result = new UnitModel;
        $result->setId(new Identity($unitSW->getId(), $unit->getId()->getHost()));

        return $result;
    }
}