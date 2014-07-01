<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Shop\Currency as CurrencyModel;

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

    public function save(array $data, $namespace = '\Shopware\Models\Shop\Currency')
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
     * @return \Shopware\Models\Shop\Currency
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    protected function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $currency \Shopware\Models\Shop\Currency */
        $currency = $this->Manager()->getRepository('Shopware\Models\Shop\Currency')->find($id);

        if (!$currency) {
            throw new ApiException\NotFoundException("Currency by id $id not found");
        }

        $currency->fromArray($params);

        $violations = $this->Manager()->validate($currency);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $currency;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Shop\Currency
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    protected function create(array $params)
    {
        $currency = new CurrencyModel();

        $currency->fromArray($params);

        $violations = $this->Manager()->validate($currency);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($currency);
        $this->flush();

        return $currency;
    }
}