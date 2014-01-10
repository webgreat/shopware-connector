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
        '_id' => '',
        '_customerId' => '',
        '_shippingAddressId' => '',
        '_billingAddressId' => '',
        '_shippingMethodId' => '',
        '_localeName' => '',
        '_currencyIso' => '',
        '_estimatedDeliveryDate' => '',
        '_credit' => '',
        '_totalSum' => '',
        '_session' => '',
        '_shippingMethodName' => '',
        '_orderNumber' => '',
        '_shippingInfo' => '',
        '_shippingDate' => '',
        '_paymentDate' => '',
        '_ratingNotificationDate' => '',
        '_tracking' => '',
        '_note' => '',
        '_logistic' => '',
        '_trackingURL' => '',
        '_ip' => '',
        '_isFetched' => '',
        '_status' => '',
        '_created' => '',
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