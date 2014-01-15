﻿<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderAttr as CustomerOrderAttrModel;

/**
 * CustomerOrderAttr Model
 * @access public
 */
class CustomerOrderAttr extends CustomerOrderAttrModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_customerOrderId' => 'orderId',
        '_key' => '',
        '_value' => ''
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