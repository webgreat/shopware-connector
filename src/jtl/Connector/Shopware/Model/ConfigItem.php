<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigItem as ConfigItemModel;

/**
 * ConfigItem Model
 * @access public
 */
class ConfigItem extends ConfigItemModel
{
    protected $_fields = array(
        '_id' => '',
        '_configGroupId' => '',
        '_productId' => '',
        '_type' => '',
        '_isPreSelected' => '',
        '_isRecommended' => '',
        '_inheritProductName' => '',
        '_inheritProductPrice' => '',
        '_showDiscount' => '',
        '_showSurcharge' => '',
        '_ignoreMultiplier' => '',
        '_minQuantity' => '',
        '_maxQuantity' => '',
        '_initialQuantity' => '',
        '_sort' => '',
        '_vat' => ''
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