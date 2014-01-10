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
    protected $_fields = array(
        'cName' => '_name',
        'cUnternehmer' => '_businessman',
        'cStrasse' => '_street',
        '' => '_streetNumber',
        'cPLZ' => '_zipCode',
        'cOrt' => '_city',
        'cLand' => '_country',
        'cTel' => '_phone',
        'cFax' => '_fax',
        'cEMail' => '_eMail',
        'cWWW' => '_www',
        'cBLZ' => '_bankCode',
        'cKontoNr' => '_accountNumber',
        'cBank' => '_bankAccount',
        'cKontoInhaber' => '_accountHolder',
        'cUSTID' => '_vatNumber',
        'cSteuerNr' => '_taxIdNumber',
        'cIBAN' => '_iban',
        'cBIC' => '_bic'
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