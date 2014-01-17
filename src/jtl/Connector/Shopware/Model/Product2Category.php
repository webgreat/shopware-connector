<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Product2Category as Product2CategoryModel;

/**
 * Product2Category Model
 * @access public
 */
class Product2Category extends Product2CategoryModel
{
    protected $_fields = array(
        '_id' => '',
        '_categoryId' => 'id',
        '_productId' => 'articleId'
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