<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Utilities\Singleton;

abstract class DataMapper extends Singleton
{
    protected $builder;

    protected function __construct()
    {
        $this->initBuilder();
    }

    protected function initBuilder()
    {
        $this->builder = Shopware()->Models()->createQueryBuilder();
    }
}