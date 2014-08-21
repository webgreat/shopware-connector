<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Shopware\Utilities\Mmc;

final class Locale
{
    private static $locales;

    /**
     * @param int $id
     * @return \Shopware\Models\Shop\Locale
     */
    public static function get($id)
    {
        if (self::$locales === null) {
            self::$locales = array();
        }

        if (!isset(self::$locales[$id])) {
            $mapper = Mmc::getMapper('Locale');

            self::$locales[$id] = $mapper->find($id);
        }

        return self::$locales[$id];
    }

    /**
     * @param string $key
     * @return \Shopware\Models\Shop\Locale
     */
    public static function getByKey($key)
    {
        if (self::$locales === null) {
            self::$locales = array();
        }

        foreach (self::$locales as $locale) {
            if ($locale->getLocale() == $key) {
                return $locale;
            }
        }

        $mapper = Mmc::getMapper('Locale');
        $locale = $mapper->findOneBy(array(
            'locale' => $key
        ));

        if ($locale) {
            self::$locales[$locale->getId()] = $locale;
        }

        return $locale;
    }

    private function __construct() { }
    private function __clone() { }
}