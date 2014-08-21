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
     * @params object $params
     * @return \jtl\Connector\Result\Action
     */
    public function pull($params)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $filter = $params;

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
                    // CustomerOrders
                    $order = Mmc::getModel('CustomerOrder');
                    $order->map(true, DataConverter::toObject($orderSW, true));

                    $this->addPos($order, 'addPosition', 'CustomerOrderItem', $orderSW['details'], true);
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

    /**
     * Insert
     *
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @return \jtl\Connector\ModelContainer\CustomerOrderContainer
     */
    public function insert(CoreContainer $container)
    {
        $config = $this->getConfig();

        $mapper = Mmc::getMapper('CustomerOrder');
        $data = $mapper->prepareData($container);
        $modelSW = $mapper->save($data);

        $resultContainer = new CustomerOrderContainer();

        // CustomerOrder
        $main = $container->getMainModel();
        $resultContainer->addIdentity('customer_order', new Identity($modelSW->getId(), $main->getId()->getHost()));

        // Item

        // Billing

        // Shipping

        // Attributes
        /*
        $attrSW = $modelSW->getAttribute();
        if ($attrSW) {
            $attr = $container->getCustomerAttrs();
            $resultContainer->addIdentity('customer_order_attr', new Identity($attrSW->getId(), $attrSW[0]->getId()->getHost()));
        }
        */

        return $resultContainer;
    }
}