<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Payment\PaymentTypes;

final class Payment
{
    private static $_mappings = array(
        PaymentTypes::TYPE_DIRECT_DEBIT => 'debit',
        PaymentTypes::TYPE_CASH => 'cash',
        PaymentTypes::TYPE_INVOICE => 'invoice',
        PaymentTypes::TYPE_PREPAYMENT => 'prepayment',
    );

    public static function mapCode($paymentModuleCode)
    {
        if (isset($self::$_mappings[$paymentModuleCode])) {
            return $self::$_mappings[$paymentModuleCode];
        }

        return null;
    }
}