<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\MediaFileAttr as MediaFileAttrModel;

/**
 * MediaFileAttr Model
 * @access public
 */
class MediaFileAttr extends MediaFileAttrModel
{
    protected $fields = array(
        'id' => '',
        'mediaFileId' => '',
        'localeName' => '',
        'name' => '',
        'value' => ''
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