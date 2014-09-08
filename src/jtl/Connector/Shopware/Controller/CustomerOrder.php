<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Exception\DatabaseException;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;

/**
 * CustomerOrder Controller
 * @access public
 */
class CustomerOrder extends DataController
{
    /**
     * Pull
     * 
     * @param \jtl\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();

            $offset = $queryFilter->isOffset() ? $queryFilter->getOffset() : 0;
            $limit = $queryFilter->isLimit() ?  $queryFilter->getLimit() : 100;

            $from = null;
            $until = null;
            if ($queryFilter->isFrom() && $queryFilter->isUntil() && (!($queryFilter->getFrom() == 0 && $queryFilter->getUntil() == 0))) {
                $from = $queryFilter->getFrom();
                $until = $queryFilter->getUntil();
            }

            $mapper = Mmc::getMapper('CustomerOrder');
            $orders = $mapper->findAll($offset, $limit, false, $from, $until);

            foreach ($orders as $orderSW) {
                try {
                    // CustomerOrders
                    $order = Mmc::getModel('CustomerOrder');
                    $order->map(true, DataConverter::toObject($orderSW, true));

                    $this->addPos($order, 'addItem', 'CustomerOrderItem', $orderSW['details'], true);
                    $this->addPos($order, 'addBillingAddress', 'CustomerOrderBillingAddress', $orderSW['billing']);
                    $this->addPos($order, 'addShippingAddress', 'CustomerOrderShippingAddress', $orderSW['shipping']);

                    // Attributes
                    /*
                     * @todo: waiting for entity
                    $attributeExists = false;
                    for ($i = 1; $i <= 6; $i++) {
                        if (isset($orderSW['attribute']["attribute{$i}"]) && strlen($orderSW['attribute']["attribute{$i}"]) > 0) {
                            $attributeExists = true;
                            $customerOrderAttr = Mmc::getModel('CustomerOrderAttrs');
                            $customerOrderAttr->map(true, DataConverter::toObject($orderSW['attribute']));
                            $customerOrderAttr->_key = "attribute{$i}";
                            $customerOrderAttr->_value = $orderSW['attribute']["attribute{$i}"];
                            $container->add('customer_order_attr', $customerOrderAttr->getPublic(), false);
                        }
                    }
                    */

                    // CustomerOrderItemVariations

                    // CustomerOrderPaymentInfos

                    $result[] = $order->getPublic();
                } catch (\Exception $exc) { 
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            $action->setResult($result);
        }
        catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}