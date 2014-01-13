﻿<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductWarehouseInfo as ProductWarehouseInfoModel;

/**
 * ProductWarehouseInfo Model
 * @access public
 */
class ProductWarehouseInfo extends ProductWarehouseInfoModel
{
    protected $_fields = array(
        '_productId' => '',
        '_warehouseId' => '',
        '_stockLevel' => '',
        '_inflowQuantity' => '',
        '_inflowDate' => ''
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