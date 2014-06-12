<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Article\Configurator\Group as ConfiguratorGroupModel;

class ConfiguratorGroup extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Group')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Group')->findOneBy($kv);
    }

    public function delete($id)
    {
        $group = $this->find($id);
        if ($group) {
            $this->Manager()->remove($group);
            $this->flush();
        }

        return $group;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Configurator\Group')
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
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $configuratorGroup \Shopware\Models\Article\Supplier */
        $configuratorGroup = $this->find($id);

        if (!$configuratorGroup) {
            throw new ApiException\NotFoundException("Configurator Group by id $id not found");
        }

        $configuratorGroup->fromArray($params);

        $violations = $this->Manager()->validate($configuratorGroup);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $configuratorGroup;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function create(array $params)
    {
        $configuratorGroup = new ConfiguratorGroupModel();

        $configuratorGroup->fromArray($params);

        $violations = $this->Manager()->validate($configuratorGroup);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($configuratorGroup);
        $this->flush();

        return $configuratorGroup;
    }

    /**
     * @param int $id
     * @param string $localId
     * @param string $translation
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function createTranslatation($id, $localId, $translation)
    {
        $configuratorGroup = $this->find($id);

        if (!$configuratorGroup) {
            throw new ApiException\NotFoundException("Configurator Group by id $id not found");
        }

        $resource = \Shopware\Components\Api\Manager::getResource('Translation');
        $resource->create(array(
            'type' => \Shopware\Components\Api\Resource\Translation::TYPE_CONFIGURATOR_GROUP,
            'key' => $configuratorGroup->getId(),
            'localeId' => $localId,
            'data' => array('name' => $translation)
        ));

        return $configuratorGroup;
    }
}