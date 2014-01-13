﻿<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\MediaFile as MediaFileModel;

/**
 * MediaFile Model
 * @access public
 */
class MediaFile extends MediaFileModel
{
    protected $_fields = array(
        '_id' => '',
        '_productId' => '',
        '_path' => '',
        '_url' => '',
        '_mediaFileCategory' => '',
        '_type' => '',
        '_sort' => ''
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