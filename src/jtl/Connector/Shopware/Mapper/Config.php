<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;

class Config extends DataMapper
{
    public function get($key)
    {
        return Shopware()->Config()->get($key);
    }

    public function update($key, $value)
    {
        $value = serialize($value);

        return Shopware()->Db()->query('UPDATE s_core_config_elements
                                    SET value = ?
                                    WHERE name = ?', array($key, $value));
    }
}