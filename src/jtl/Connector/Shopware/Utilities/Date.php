<?php 
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Core\Utilities\Date as CoreDate;
use \jtl\Core\Exception\DateException;

final class Date
{
    private function __construct() { }
    
    public static function check($platformValue)
    {
        return ($platformValue instanceof DateTime);
    }
    
    public static function map($platformValue = null, $connectorValue = null)
    {        
        if ($platformValue !== null && self::check($platformValue)) {
            $targetformat = 'Y-m-d H:i:s';

            return CoreDate::map($platformValue->format($targetformat), $targetformat);
        }
        
        if ($connectorValue !== null) {
            return \DateTime::createFromFormat(\DateTime::ISO8601, $connectorValue);
        }

        return null;
    }
}