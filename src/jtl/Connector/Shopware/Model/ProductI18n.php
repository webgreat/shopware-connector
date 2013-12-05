<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductI18n as ProductI18nModel;

/**
 * ProductI18n Model
 * @access public
 */
class ProductI18n extends ProductI18nModel
{
    protected $_fields = array(
        '_localeName' => '',
        '_productId' => 'id',
        '_name' => 'name',
        '_url' => '',
        '_description' => 'descriptionLong',
        '_shortDescription' => 'description'
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