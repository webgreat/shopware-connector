<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\DataModel;
use \jtl\Connector\Model\Unit as UnitModel;
use \Shopware\Models\Article\Unit as UnitSW;

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
        $result = new UnitModel;

        if ($unit->getAction() == DataModel::ACTION_DELETE) {   // Delete
            $this->deleteUnitData($unit);
        } else {    // Update or Insert
           $this->prepareUnitAssociatedData($unit, $unitSW);

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
        $result->setId(new Identity($unitSW->getId(), $unit->getId()->getHost()));

        return $result;
    }

    protected function deleteUnitData(DataModel &$unit)
    {
        $unitId = (strlen($unit->getId()->getEndpoint()) > 0) ? (int)$unit->getId()->getEndpoint() : null;

        if ($unitId !== null && $unitId > 0) {
            $unitSW = $this->find($unitId);
            if ($unitSW !== null) {
                $this->Manager()->remove($unitSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function prepareUnitAssociatedData(DataModel &$unit, UnitSW &$unitSW)
    {
        $unitId = (strlen($unit->getId()->getEndpoint()) > 0) ? (int)$unit->getId()->getEndpoint() : null;

        if ($unitId !== null && $unitId > 0) {
            $unitSW = $this->find($unitId);
        }

        if ($unitSW === null) {
            $unitSW = new UnitSW;
        }
    }
}