<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \Shopware\Models\Order\Status;
use \jtl\Connector\Model\CustomerOrder;

final class Status
{
    private static $_mappings = array(
        Status::GROUP_STATE => array(
            CustomerOrder::STATUS_NEW => 0,
            CustomerOrder::STATUS_PROCESSING => 1,
            CustomerOrder::STATUS_PAYMENT_COMPLETED => 11,
            CustomerOrder::STATUS_COMPLETED => 2,
            CustomerOrder::STATUS_PARTIALLY_SHIPPED => 6,
            CustomerOrder::STATUS_CANCELLED => 4
        ),
        Status::GROUP_PAYMENT => array(
            
        )
    );

    public static function mapStatus($orderStatus, $group = Status::GROUP_STATE)
    {
        if (isset($self::$_mappings[$group][$orderStatus])) {
            return $self::$_mappings[$group][$orderStatus];
        }

        return null;
    }
}