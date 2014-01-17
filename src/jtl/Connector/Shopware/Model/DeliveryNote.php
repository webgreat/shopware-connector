<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\DeliveryNote as DeliveryNoteModel;

/**
 * DeliveryNote Model
 * @access public
 */
class DeliveryNote extends DeliveryNoteModel
{
    protected $_fields = array(
        '_id' => '',
        '_customerOrderId' => '',
        '_note' => '',
        '_created' => '',
        '_isFulfillment' => '',
        '_status' => ''
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