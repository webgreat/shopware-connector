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
        '_customerGroupId' => array('group', 'id'),
        '_localeName' => array('languageSubShop', 'locale', 'locale'),
        '_customerNumber' => array('billing', 'number'),
        '_password' => 'hashPassword',
        '_salutation' => array('billing', 'salutation'),
        '_title' => '',
        '_firstName' => array('billing', 'firstName'),
        '_lastName' => array('billing', 'lastName'),
        '_company' => array('billing', 'company'),
        '_street' => array('billing', 'street'),
        '_deliveryInstruction' => '',
        '_extraAddressLine' => '',
        '_zipCode' => array('billing', 'zipCode'),
        '_city' => array('billing', 'city'),
        '_state' => '',
        '_countryIso' => '',
        '_phone' => array('billing', 'phone'),
        '_mobile' => '',
        '_fax' => array('billing', 'fax'),
        '_eMail' => 'email',
        '_vatNumber' => array('billing', 'vatId'),
        '_www' => '',
        '_accountCredit' => '',
        '_hasNewsletterSubscription' => 'newsletter',
        '_birthday' => array('billing', 'birthday'),
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