<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Shopware\Utilities\Mmc;

final class CustomerGroup
{
    private static $customerGroups;

    /**
     * @param int $id
     * @return \Shopware\Models\Customer\Group
     */
    public static function get($id)
    {
        if ($customerGroups === null) {
            self::$customerGroups = array();
        }

        if (!isset(self::$customerGroups[$id])) {
            $mapper = Mmc::getMapper('CustomerGroup');

            self::$customerGroups[$id] = $mapper->find($id);
        }

        return self::$customerGroups[$id];
    }

    /**
     * @param string $key
     * @return \Shopware\Models\Customer\Group
     */
    public static function getByKey($key)
    {
        if (self::$customerGroups === null) {
            self::$customerGroups = array();
        }

        foreach (self::$customerGroups as $customerGroup) {
            if ($customerGroup->getKey() == $key) {
                return $customerGroup;
            }
        }

        $mapper = Mmc::getMapper('CustomerGroup');
        $customerGroup = $mapper->findOneBy(array(
            'key' => $key
        ));

        if ($customerGroup) {
            self::$customerGroups[$customerGroup->getId()] = $customerGroup;
        }

        return $customerGroup;
    }

    private function __construct() { }
    private function __clone() { }
}