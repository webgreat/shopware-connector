<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Core\Model\DataModel as MainDataModel;
use \jtl\Connector\Shopware\Utilities\Date as DateUtil;

/**
 * DataModel Class
 */
class DataModel
{
    /**
     * Object Mapping
     *
     * @param boolean $toConnector
     */
    public static function map($toConnector = false, \stdClass $obj = null, MainDataModel &$original)
    {
        if ($toConnector && $obj === null)
            throw new \BadMethodCallException("The second parameter can't be null if the first is true");
    
        if (!$toConnector) {
            $obj = new \stdClass();
        }

        $getValue = function (array $platformFields, \stdClass $data) use (&$getValue) {
            if (count($platformFields) > 1) {
                $value = array_shift($platformFields);
                
                return is_object($data->$value) ? $getValue($platformFields, $data->$value) : $data->$value;
            }
            else {
                $value = array_shift($platformFields);
                
                return $data->$value;
            }
        };

        $setValue = function (array $platformFields, $value, \stdClass $obj) use (&$setValue) {
            if (count($platformFields) > 1) {
                $field = array_shift($platformFields);
                $obj->$field = new \stdClass;
                
                return $setValue($platformFields, $value, $obj->$field);
            }
            else {
                $field = array_shift($platformFields);
                $obj->$field = $value;
                
                return $obj;
            }
        };

        foreach ($original->getFields() as $connectorField => $platformField) {
            if ($toConnector) {
                if (is_array($platformField)) {
                    $value = $getValue($platformField, $obj);
                    $this->$connectorField = DateUtil::check($value) ? DateUtil::map($platformField) : $value;
                }
                else {
                    $this->$connectorField = $obj->$platformField;
                    $this->$connectorField = DateUtil::check($obj->$platformField) ? DateUtil::map($obj->$platformField) : $obj->$platformField;
                }
            }
            else {
                if (is_array($platformField)) {
                    // TODO: Date Check
                    $setValue($platformField, $original->$connectorField, $obj);
                }
                else {
                    // TODO: Date Check                    
                    $obj->$platformField = $original->$connectorField;
                }
            }
        }

        if ($toConnector) {
            return true;
        }
        else {
            unset($obj->_fields);
            return $obj;
        }
    }
    
    /**
     * Single Field Mapping
     *
     * @param string $fieldName
     * @param MainDataModel $original
     * @param boolean $toWawi
     * @return string|NULL
     */
    public static function getMappingField($fieldName, MainDataModel &$original, $toWawi = false)
    {
        foreach ($original->getFields() as $shopField => $wawiField) {
            if ($toWawi) {
                if ($shopField === $fieldName) {
                    return $wawiField;
                }
            }
            else {
                if ($wawiField === $fieldName) {
                    return $shopField;
                }
            }
        }
        
        return null;
    }
}