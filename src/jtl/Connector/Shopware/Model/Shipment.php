<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Shipment as ShipmentModel;

/**
 * Shipment Model
 * @access public
 */
class Shipment extends ShipmentModel
{
    protected $_fields = array(
        '_id' => '',
        '_deliveryNoteId' => '',
        '_logistic' => '',
        '_logisticURL' => '',
        '_identCode' => '',
        '_created' => '',
        '_note' => ''
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