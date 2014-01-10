<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\TaxRate as TaxRateModel;

/**
 * TaxRate Model
 * @access public
 */
class TaxRate extends TaxRateModel
{
    protected $_fields = array(
        '_id' => '',
        '_taxZoneId' => '',
        '_taxClassId' => '',
        '_rate' => '',
        '_priority' => ''
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