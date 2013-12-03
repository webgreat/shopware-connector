<?php 
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

/**
 * Model Mapper Controller Class
 */
final class Mmc
{
    const NAMESPACE_MODEL = "\\jtl\\Connector\\Shopware\\Model\\";
    const NAMESPACE_CONTROLLER = "\\jtl\\Connector\\Shopware\\Controller\\";

    private function __construct() { }
    
    /**
     * Model Getter
     * 
     * @param string $class
     * @param boolean $useNamespace
     * @throws \Exception
     * @return string|mixed
     */
    public static function getModel($class, $useNamespace = false)
    {
        if (class_exists(self::NAMESPACE_MODEL . $class)) {
            if ($useNamespace) {
                return self::NAMESPACE_MODEL . $class;
            }
            else {
                $class = self::NAMESPACE_MODEL . $class;
                
                return new $class();
            }
        }
        
        throw new \Exception("Class '" . self::NAMESPACE_MODEL . $class . "' not found");
    }
    
    /**
     * Controller Getter
     *
     * @param string $class
     * @param boolean $useNamespace
     * @throws \Exception
     * @return string|mixed
     */
    public static function getController($class, $useNamespace = false)
    {
        if (class_exists(self::NAMESPACE_CONTROLLER . $class)) {
            if ($useNamespace) {
                return self::NAMESPACE_CONTROLLER . $class;
            }
            else {
                $class = self::NAMESPACE_CONTROLLER . $class;
        
                return $class::getInstance();
            }
        }
        
        throw new \Exception("Class '" . self::NAMESPACE_MODEL . $class . "' not found");
    }
}