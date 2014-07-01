<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Article\Unit as UnitModel;

class Unit extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
            'unit'
        ))
        ->from('Shopware\Models\Article\Unit', 'unit')
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

    public function save(array $data, $namespace = '\Shopware\Models\Article\Unit')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        try {
            if (!$data['id']) {
                return $this->create($data);
            } else {
                return $this->update($data['id'], $data);
            }
        } catch (ApiException\NotFoundException $exc) {
            return $this->create($data);
        }
    }

    /**
     * @param int $id
     * @param array $params
     * @return \Shopware\Models\Article\Unit
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    protected function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $unit \Shopware\Models\Article\Unit */
        $unit = $this->Manager()->getRepository('Shopware\Models\Article\Unit')->find($id);

        if (!$Unit) {
            throw new ApiException\NotFoundException("Unit by id $id not found");
        }

        $unit->fromArray($params);

        $violations = $this->Manager()->validate($unit);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $unit;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Article\Unit
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    protected function create(array $params)
    {
        $unit = new UnitModel();

        $unit->fromArray($params);

        $violations = $this->Manager()->validate($unit);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($unit);
        $this->flush();

        return $unit;
    }
}