<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Article\Configurator\Option as ConfiguratorOptionModel;

class ConfiguratorOption extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Option')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Option')->findOneBy($kv);
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Configurator\Option')
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
     * @return \Shopware\Models\Article\Configurator\Option
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $configuratorOption \Shopware\Models\Article\Supplier */
        $configuratorOption = $this->find($id);

        if (!$configuratorOption) {
            throw new ApiException\NotFoundException("Configurator Option by id $id not found");
        }

        $configuratorOption->fromArray($params);

        $violations = $this->Manager()->validate($configuratorOption);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $configuratorOption;
    }

    /**
     * @param array $params
     * @param string $localId
     * @return \Shopware\Models\Article\Configurator\Option
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function create(array $params, $localId = null)
    {
        if ($localId === null) {
            $localId = Shopware()->Shop()->getLocale()->getLocale();
        }

        $configuratorOption = new ConfiguratorOptionModel();

        $configuratorOption->fromArray($params);

        $violations = $this->Manager()->validate($configuratorOption);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($configuratorOption);
        $this->flush();

        if ($localId != Shopware()->Shop()->getLocale()->getLocale()) {
            $resource = \Shopware\Components\Api\Manager::getResource('Translation');
            $resource->create(array(
                'type' => Shopware\Components\Api\Resource\Translation::TYPE_CONFIGURATOR_OPTION,
                'key' => $configuratorOption->getId(),
                'localeId' => $localId,
                'data' => $configuratorOption->getName()
            ));
        }

        return $configuratorOption;
    }
}