<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\FileDownloadHistory as FileDownloadHistoryModel;

/**
 * FileDownloadHistory Model
 * @access public
 */
class FileDownloadHistory extends FileDownloadHistoryModel
{
    protected $_fields = array(
        '_id' => '',
        '_fileDownloadId' => '',
        '_customerId' => '',
        '_customerOrderId' => '',
        '_created' => ''
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