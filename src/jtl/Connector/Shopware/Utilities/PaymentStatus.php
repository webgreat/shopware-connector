<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \Shopware\Models\Order\Status;
use \jtl\Connector\Model\CustomerOrder;

final class PaymentStatus
{
    private static $_mappings = array(
        CustomerOrder::PAYMENT_STATUS_COMPLETED = 12;
        CustomerOrder::PAYMENT_STATUS_PARTIALLY = 11;
        CustomerOrder::PAYMENT_STATUS_UNPAID = 17;
    );

    public static function map($paymentStatus)
    {
        if (isset($self::$_mappings[$paymentStatus])) {
            return $self::$_mappings[$paymentStatus];
        }

        return null;
    }
}