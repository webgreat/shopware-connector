<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Image as ImageModel;

/**
 * Image Model
 * @access public
 */
class Image extends ImageModel
{
    protected $_fields = array(
        '_id' => 'id',
        '_masterImageId' => '',
        '_relationType' => 'type',
        '_foreignKey' => '',
        '_filename' => 'path',
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