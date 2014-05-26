<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\ModelContainer\ManufacturerContainer;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \Shopware\Models\Article\Supplier as SupplierModel;
use \jtl\Connector\Logger\Logger;

class Manufacturer extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'supplier'
        )
        ->from('Shopware\Models\Article\Supplier', 'supplier');

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
                'supplier',
                'attribute'
            ))
            ->from('Shopware\Models\Article\Supplier', 'supplier')
            ->leftJoin('supplier.attribute', 'attribute')
            ->where('supplier.id BETWEEN :first AND :last')
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

    public function prepareData(ManufacturerContainer $container)
    {
        $manufacturers = $container->getManufacturers();
        $manufacturer = $manufacturers[0];

        // Manufacturer
        $data = DataConverter::toArray(DataModel::map(false, null, $manufacturer));

        // ManufacturerI18n
        foreach ($container->getManufacturerI18ns() as $manufacturerI18n) {
            $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $manufacturerI18n)));
        }

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Supplier')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        try {
            return $this->update($data['id'], $data);
        } catch (ApiException\NotFoundException $exc) {
            return $this->create($data);
        }
    }

    /**
     * @param int $id
     * @param array $params
     * @return \Shopware\Models\Article\Supplier
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    protected function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $manufacturer \Shopware\Models\Article\Supplier */
        $manufacturer = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find($id);

        if (!$manufacturer) {
            throw new ApiException\NotFoundException("Manufacturer by id $id not found");
        }

        $manufacturer->fromArray($params);

        $violations = $this->Manager()->validate($manufacturer);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $manufacturer;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Article\Supplier
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    protected function create(array $params)
    {
        $supplier = new SupplierModel();

        $supplier->fromArray($params);

        $violations = $this->Manager()->validate($supplier);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($supplier);
        $this->flush();

        return $supplier;
    }
}