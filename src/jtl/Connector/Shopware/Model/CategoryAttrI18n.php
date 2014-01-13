<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CategoryAttrI18n as CategoryAttrI18nModel;

/**
 * CategoryAttrI18n Model
 * @access public
 */
class CategoryAttrI18n extends CategoryAttrI18nModel
{
    protected $_fields = array(
        '_localeName' => '',
        '_categoryAttrId' => '',
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