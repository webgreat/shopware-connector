<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrder as CustomerOrderModel;

/**
 * CustomerOrder Model
 * @access public
 */
class CustomerOrder extends CustomerOrderModel
{
    protected $fields = array(
        'id' => 'id',
        'customerId' => array('customer', 'id'),
        'shippingAddressId' => array('shipping', 'id'),
        'billingAddressId' => array('billing', 'id'),
        'shippingMethodCode' => 'dispatchId',
        'localeName' => '',
        'currencyIso' => 'currency',
        'estimatedDeliveryDate' => '',
        'credit' => '',
        'totalSum' => 'invoiceAmountNet',
        'session' => '',
        'shippingMethodName' => '',
        'orderNumber' => 'number',
        'shippingInfo' => '',
        'shippingDate' => '',
        'paymentDate' => '',
        'ratingNotificationDate' => '',
        'tracking' => 'trackingCode',
        'note' => 'customerComment',
        'carrierName' => '',
        'trackingURL' => '',
        'ip' => 'remoteAddress',
        'isFetched' => '',
        'status' => '',
        'paymentStatus' => '',
        'created' => 'orderTime',
        'paymentModuleCode' => ''
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