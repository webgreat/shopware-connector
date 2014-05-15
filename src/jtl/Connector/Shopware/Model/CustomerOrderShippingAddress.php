<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderShippingAddress as CustomerOrderShippingAddressModel;
use \jtl\Connector\Shopware\Utilities\Salutation;

/**
 * CustomerOrderShippingAddress Model
 * @access public
 */
class CustomerOrderShippingAddress extends CustomerOrderShippingAddressModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_customerId' => 'customerId',
        '_salutation' => 'salutation',
        '_firstName' => 'firstName',
        '_lastName' => 'lastName',
        '_title' => '',
        '_company' => '',
        '_deliveryInstruction' => '',
        '_street' => 'street',
        '_extraAddressLine' => 'streetNumber',
        '_zipCode' => 'zipCode',
        '_city' => 'city',
        '_state' => '',
        '_countryIso' => array('country', 'iso'),
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
        if (isset($obj->salutation)) {
            $obj->salutation = Salutation::map($obj->salutation);
        }

        return DataModel::map($toWawi, $obj, $this);
    }
}