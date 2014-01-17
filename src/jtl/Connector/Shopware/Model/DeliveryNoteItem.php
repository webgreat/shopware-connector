<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\DeliveryNoteItem as DeliveryNoteItemModel;

/**
 * DeliveryNoteItem Model
 * @access public
 */
class DeliveryNoteItem extends DeliveryNoteItemModel
{
    protected $_fields = array(
        '_id' => '',
        '_customerOrderItemId' => '',
        '_quantity' => '',
        '_warehouseId' => '',
        '_serialNumber' => '',
        '_batchNumber' => '',
        '_bestBefore' => '',
        '_deliveryNoteId' => ''
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