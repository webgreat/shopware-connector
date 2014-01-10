<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Language as LanguageModel;

/**
 * Language Model
 * @access public
 */
class Language extends LanguageModel
{
    protected $_fields = array(
        '_id' => '',
        '_nameEnglish' => '',
        '_nameGerman' => '',
        '_localeName' => '',
        '_isDefault' => ''
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
?>