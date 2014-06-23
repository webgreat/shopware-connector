<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Tax\Tax as TaxModel;

class TaxRate extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
            'tax'
        ))
        ->from('Shopware\Models\Tax\Tax', 'tax')
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

    public function save(array $data, $namespace = '\Shopware\Models\Tax\Tax')
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
     * @return \Shopware\Models\Tax\Tax
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    protected function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $Tax \Shopware\Models\Tax\Tax */
        $tax = $this->Manager()->getRepository('Shopware\Models\Tax\Tax')->find($id);

        if (!$tax) {
            throw new ApiException\NotFoundException("Tax by id $id not found");
        }

        $tax->fromArray($params);

        $violations = $this->Manager()->validate($tax);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $tax;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Tax\Tax
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    protected function create(array $params)
    {
        $tax = new UnitModel();

        $tax->fromArray($params);

        $violations = $this->Manager()->validate($tax);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($tax);
        $this->flush();

        return $tax;
    }
}