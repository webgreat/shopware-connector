<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Currency as CurrencyModel;

/**
 * Currency Model
 * @access public
 */
class Currency extends CurrencyModel
{
    protected $_fields = array(
        '_id' => '',
        '_name' => '',
        '_iso' => '',
        '_nameHtml' => '',
        '_factor' => '',
        '_isDefault' => '',
        '_hasCurrencySignBeforeValue' => '',
        '_delimiterCent' => '',
        '_delimiterThousand' => ''
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