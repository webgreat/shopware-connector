<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerGroupAttr as CustomerGroupAttrModel;

/**
 * CustomerGroupAttr Model
 * @access public
 */
class CustomerGroupAttr extends CustomerGroupAttrModel
{
    protected $fields = array(
        'id' => '',
        'customerGroupId' => '',
        'key' => '',
        'value' => ''
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