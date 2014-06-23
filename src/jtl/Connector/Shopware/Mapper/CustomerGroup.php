<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Customer\Group as GroupModel;

class CustomerGroup extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Customer\Group')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Customer\Group')->findOneBy($kv);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'customergroup'
        )
        ->from('Shopware\Models\Customer\Group', 'customergroup');

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
                'customergroup',
                'attribute'
            ))
            ->from('Shopware\Models\Customer\Group', 'customergroup')
            ->leftJoin('customergroup.attribute', 'attribute')
            ->where('customergroup.id BETWEEN :first AND :last')
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

    public function save(array $data, $namespace = '\Shopware\Models\Customer\Group')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        return parent::save($data, $namespace);
    }

    public function save(array $data, $namespace = '\Shopware\Models\Customer\Group')
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
     * @return \Shopware\Models\Customer\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    protected function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $group \Shopware\Models\Customer\Group */
        $group = $this->Manager()->getRepository('Shopware\Models\Customer\Group')->find($id);

        if (!$group) {
            throw new ApiException\NotFoundException("Group by id $id not found");
        }

        $group->fromArray($params);

        $violations = $this->Manager()->validate($group);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $group;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Customer\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    protected function create(array $params)
    {
        $group = new GroupModel();

        $group->fromArray($params);

        $violations = $this->Manager()->validate($group);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($group);
        $this->flush();

        return $group;
    }
}