<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Utilities\Singleton;

abstract class DataMapper extends Singleton
{
    protected $manager;

    protected function __construct()
    {
        $this->manager = Shopware()->Models();
    }

    protected function Manager()
    {
        return $this->manager;
    }

    /**
     * @param object $entity
     * @throws \Exception
     */
    protected function flush($entity = null)
    {
        $this->Manager()->getConnection()->beginTransaction();
        try {
            $this->Manager()->flush($entity);
            $this->Manager()->getConnection()->commit();
            $this->Manager()->clear();
        } catch (\Exception $e) {
            $this->Manager()->getConnection()->rollBack();
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    protected function save(array $data, $namespace)
    {
        if ($namespace === null || !class_exists($class)) {
            throw new \InvalidArgumentException("The namespace ({$namespace}) can not be null, and the class must exist");
        }

        $model = new $namespace();
        $model->fromArray($data);

        $violations = $this->Manager()->validate($model);
        if ($violations->count() > 0) {
            throw new \Exception($violations);
        }

        $this->Manager()->persist($model);
        $this->flush();

        return $model;
    }
}