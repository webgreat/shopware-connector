<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Core\Model\DataModel as MainDataModel;

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
        
        foreach ($original->getFields() as $platformField => $connectorField) {
            if ($toConnector) {
                if (strlen($platformField) > 0 && isset($obj->$platformField)) {
                    if (LanguageUtil::check($connectorField, $platformField)) {
                        $original->$connectorField = utf8_encode(LanguageUtil::map(null, $obj->$platformField));
                    }
                    else if (BooleanUtil::check($connectorField)) {
                        $original->$connectorField = BooleanUtil::map($obj->$platformField);
                    }
                    else if (DateUtil::check($platformField)) {
                        $original->$connectorField = DateUtil::map($obj->$platformField, null, $platformField);
                    }
                    else {
                        if (is_numeric($obj->$platformField)) {
                            $original->$connectorField = $obj->$platformField;
                        }
                        else if ($obj->$platformField === null) {
                            $original->$connectorField = null;
                        }
                        else {
                            $original->$connectorField = utf8_encode($obj->$platformField);
                        }
                    }
                }
            }
            else if (strlen($platformField) > 0) {
                if (LanguageUtil::check($connectorField, $platformField)) {
                    $obj->$platformField = LanguageUtil::map($original->$connectorField);
                }
                else if (BooleanUtil::check($connectorField)) {
                    $obj->$platformField = BooleanUtil::map(null, $platformField, $original->$connectorField);
                }
                else if (DateUtil::check($platformField)) {
                    $obj->$platformField = DateUtil::map(null, $original->$connectorField, $platformField);
                }
                else {
                    if (is_numeric($original->$connectorField)) {
                        $obj->$platformField = $original->$connectorField;
                    }
                    else if ($original->$connectorField === null) {
                        $obj->$platformField = null;
                    }
                    else {
                        $obj->$platformField = utf8_decode($original->$connectorField);
                    }
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