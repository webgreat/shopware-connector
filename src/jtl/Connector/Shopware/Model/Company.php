<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Company as CompanyModel;

/**
 * Company Model
 * @access public
 */
class Company extends CompanyModel
{
    protected $fields = array(
        'name' => '',
        'businessman' => '',
        'street' => '',
        'streetNumber' => '',
        'zipCode' => '',
        'city' => '',
        'countryIso' => '',
        'phone' => '',
        'fax' => '',
        'eMail' => '',
        'www' => '',
        'bankCode' => '',
        'accountNumber' => '',
        'bankName' => '',
        'accountHolder' => '',
        'vatNumber' => '',
        'taxIdNumber' => '',
        'iban' => '',
        'bic' => ''
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