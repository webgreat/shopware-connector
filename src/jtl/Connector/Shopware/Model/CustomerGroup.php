<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerGroup as CustomerGroupModel;

/**
 * CustomerGroup Model
 * @access public
 */
class CustomerGroup extends CustomerGroupModel
{
    protected $fields = array(
        'id' => 'id',
        'discount' => 'discount',
        'isDefault' => '',
        'applyNetPrice' => 'taxInput'
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