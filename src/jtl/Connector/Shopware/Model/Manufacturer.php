<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Manufacturer as ManufacturerModel;

/**
 * Manufacturer Model
 * @access public
 */
class Manufacturer extends ManufacturerModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_name' => 'name',
        '_www' => 'link',
        '_sort' => '',
        '_urlPath' => ''
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