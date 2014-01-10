<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderItem as CustomerOrderItemModel;

/**
 * CustomerOrderItem Model
 * @access public
 */
class CustomerOrderItem extends CustomerOrderItemModel
{
    protected $_fields = array(
        '_id' => '',
        '_basketId' => '',
        '_productId' => '',
        '_shippingClassId' => '',
        '_customerOrderId' => '',
        '_name' => '',
        '_sku' => '',
        '_price' => '',
        '_vat' => '',
        '_quantity' => '',
        '_type' => '',
        '_unique' => '',
        '_configItemId' => ''
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