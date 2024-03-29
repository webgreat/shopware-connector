<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Specific as SpecificModel;

/**
 * Specific Model
 * @access public
 */
class Specific extends SpecificModel
{
    protected $fields = array(
        'id' => 'id',
        'sort' => '',
        'isGlobal' => '',
        'type' => ''
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