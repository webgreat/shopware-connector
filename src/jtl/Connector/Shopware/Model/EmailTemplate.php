<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\EmailTemplate as EmailTemplateModel;

/**
 * EmailTemplate Model
 * @access public
 */
class EmailTemplate extends EmailTemplateModel
{
    protected $_fields = array(
        '_id' => '',
        '_name' => '',
        '_description' => '',
        '_emailType' => '',
        '_moduleId' => '',
        '_filename' => '',
        '_isActive' => '',
        '_isOii' => '',
        '_isAgb' => '',
        '_isWrb' => '',
        '_error' => ''
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