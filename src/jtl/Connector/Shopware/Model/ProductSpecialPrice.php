<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductSpecialPrice as ProductSpecialPriceModel;

/**
 * ProductSpecialPrice Model
 * @access public
 */
class ProductSpecialPrice extends ProductSpecialPriceModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_productId' => 'articleId',
        '_isActive' => 'active',
        '_activeFrom' => '',
        '_activeUntil' => '',
        '_stockLimit' => '',
        '_considerStockLimit' => '',
        '_considerDateLimit' => ''
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