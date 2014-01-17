<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigItemI18n as ConfigItemI18nModel;

/**
 * ConfigItemI18n Model
 * @access public
 */
class ConfigItemI18n extends ConfigItemI18nModel
{
    protected $_fields = array(
        '_configItemId' => '',
        '_localeName' => '',
        '_name' => '',
        '_description' => ''
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