<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\FileDownload as FileDownloadModel;

/**
 * FileDownload Model
 * @access public
 */
class FileDownload extends FileDownloadModel
{
    protected $fields = array(
        'id' => '',
        'path' => '',
        'previewPath' => '',
        'maxDownloads' => '',
        'maxDays' => '',
        'sort' => '',
        'created' => ''
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