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
        '_id' => 'id',
        '_productId' => 'articleId',
        '_shippingClassId' => '',
        '_customerOrderId' => 'orderId',
        '_name' => 'articleName',
        '_sku' => 'articleNumber',
        '_price' => 'price',
        '_vat' => 'taxRate',
        '_quantity' => 'quantity',
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