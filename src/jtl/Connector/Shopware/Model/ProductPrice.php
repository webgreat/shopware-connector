<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductPrice as ProductPriceModel;

/**
 * ProductPrice Model
 * @access public
 */
class ProductPrice extends ProductPriceModel
{
    protected $fields = array(
        'customerGroupId' => 'customerGroupId',
        'productId' => 'articleId',
        'netPrice' => 'price',
        'quantity' => 'from'
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
?>