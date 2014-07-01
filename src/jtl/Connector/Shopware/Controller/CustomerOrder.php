<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Result\Transaction as TransactionResult;
use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Core\Exception\TransactionException;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Exception\DatabaseException;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\ModelContainer\CustomerOrderContainer;
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
     * @params object $params
     * @return \jtl\Connector\Result\Action
     */
    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $filter = new QueryFilter();
            $filter->set($params);

            $offset = 0;
            $limit = 100;
            $from = null;
            $until = null;
            if ($filter->isOffset()) {
                $offset = $filter->getOffset();
            }

            if ($filter->isLimit()) {
                $limit = $filter->getLimit();
            }

            if ($filter->isFrom() && $filter->isUntil()) {
                $from = $filter->getFrom();
                $until = $filter->getUntil();
            }

            $mapper = Mmc::getMapper('CustomerOrder');
            $orders = $mapper->findAll($offset, $limit, false, $from, $until);

            foreach ($orders as $orderSW) {
                try {
                    $container = new CustomerOrderContainer();

                    // CustomerOrders
                    $order = Mmc::getModel('CustomerOrder');
                    $order->map(true, DataConverter::toObject($orderSW));

                    $this->addContainerPos($container, 'customer_order_item', $orderSW['details'], true);
                    $this->addContainerPos($container, 'customer_order_billing_address', $orderSW['billing']);
                    $this->addContainerPos($container, 'customer_order_shipping_address', $orderSW['shipping']);

                    // Attributes
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

                    $container->add('customer_order', $order->getPublic(), false);

                    // CustomerOrderItemVariations

                    // CustomerOrderPaymentInfos

                    $result[] = $container->getPublic(array("items"));
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

    /**
     * Transaction Commit
     *
     * @param mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function commit($params, $trid)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $container = TransactionHandler::getContainer($this->getMethod()->getController(), $trid);
            $result = $this->insert($container);

            if ($result !== null) {
                $action->setResult($result->getPublic());
            }
        }
        catch (\Exception $exc) {
            $message = (strlen($exc->getMessage()) > 0) ? $exc->getMessage() : ExceptionFormatter::format($exc);

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }
}