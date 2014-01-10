<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderShippingAddress as CustomerOrderShippingAddressModel;

/**
 * CustomerOrderShippingAddress Model
 * @access public
 */
class CustomerOrderShippingAddress extends CustomerOrderShippingAddressModel
{
    protected $_fields = array(
        '_id' => '',
        '_customerId' => '',
        '_salutation' => '',
        '_firstName' => '',
        '_lastName' => '',
        '_title' => '',
        '_company' => '',
        '_deliveryInstruction' => '',
        '_street' => '',
        '_extraAddressLine' => '',
        '_zipCode' => '',
        '_city' => '',
        '_state' => '',
        '_countryIso' => '',
        '_phone' => '',
        '_mobile' => '',
        '_fax' => '',
        '_eMail' => ''
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