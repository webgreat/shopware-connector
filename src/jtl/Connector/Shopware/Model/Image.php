<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Image as ImageModel;
use \jtl\Connector\Drawing\ImageRelationType;

/**
 * Image Model
 * @access public
 */
class Image extends ImageModel
{
    protected $fields = array(
        'id' => 'id',
        'masterImageId' => '',
        'relationType' => 'type',
        'foreignKey' => '',
        'filename' => 'path',
        'sort' => ''
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }

    public static function generateId($relationType, $id, $mediaId)
    {
        switch($relationType) {
            case ImageRelationType::TYPE_PRODUCT:
                return sprintf('a_%s_%s', $id, $mediaId);
            case ImageRelationType::TYPE_CATEGORY:
                return sprintf('c_%s_%s', $id, $mediaId);
            case ImageRelationType::TYPE_MANUFACTURER:
                return sprintf('s_%s_%s', $id, $mediaId);
        }
    }
}