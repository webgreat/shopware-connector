<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\MeasurementUnit as MeasurementUnitModel;

/**
 * MeasurementUnit Model
 * @access public
 */
class MeasurementUnit extends MeasurementUnitModel
{
    protected $_fields = array(
        '_id' => '',
        '_code' => '',
        '_displayCode' => ''
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}