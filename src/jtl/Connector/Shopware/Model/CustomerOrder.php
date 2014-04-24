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
    protected $_fields = array(
        '_id' => 'id',
        '_customerId' => array('customer', 'id'),
        '_shippingAddressId' => array('shipping', 'id'),
        '_billingAddressId' => array('billing', 'id'),
        '_shippingMethodId' => '',
        '_localeName' => '',
        '_currencyIso' => 'currency',
        '_estimatedDeliveryDate' => '',
        '_credit' => '',
        '_totalSum' => 'invoiceAmountNet',
        '_session' => '',
        '_shippingMethodName' => '',
        '_orderNumber' => 'number',
        '_shippingInfo' => '',
        '_shippingDate' => '',
        '_paymentDate' => '',
        '_ratingNotificationDate' => '',
        '_tracking' => 'trackingCode',
        '_note' => 'customerComment',
        '_carrierName' => '',
        '_trackingURL' => '',
        '_ip' => 'remoteAddress',
        '_isFetched' => '',
        '_status' => '',
        '_created' => 'orderTime',
        '_paymentModuleId' => ''
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