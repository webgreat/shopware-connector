<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Customer as CustomerModel;

/**
 * Customer Model
 * @access public
 */
class Customer extends CustomerModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_customerGroupId' => '',
        '_localeName' => '',
        '_customerNumber' => '',
        '_password' => 'hashPassword',
        '_salutation' => '',
        '_title' => '',
        '_firstName' => '',
        '_lastName' => '',
        '_company' => '',
        '_street' => '',
        '_streetNumber' => '',
        '_deliveryInstruction' => '',
        '_extraAddressLine' => '',
        '_zipCode' => '',
        '_city' => '',
        '_state' => '',
        '_countryIso' => '',
        '_phone' => '',
        '_mobile' => '',
        '_fax' => '',
        '_eMail' => 'email',
        '_vatNumber' => '',
        '_www' => '',
        '_accountCredit' => '',
        '_hasNewsletterSubscription' => 'newsletter',
        '_birthday' => '',
        '_discount' => '',
        '_origin' => '',
        '_created' => 'firstLogin',
        '_modified' => '',
        '_isActive' => 'active',
        '_isFetched' => '',
        '_hasCustomerAccount' => ''
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