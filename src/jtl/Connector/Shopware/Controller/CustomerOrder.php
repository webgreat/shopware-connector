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
use \Shopware\Components\Api\Manager as ShopwareManager;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\ModelContainer\CustomerOrderContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;

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
            if ($filter->isOffset()) {
                $offset = $filter->getOffset();
            }

            if ($filter->isLimit()) {
                $limit = $filter->getLimit();
            }

            $container = new CustomerOrderContainer();

            $builder = Shopware()->Models()->createQueryBuilder();

            $orders = $builder->select(array(
                    'orders',
                    'customer',
                    'attribute',
                    'details',
                    'tax',
                    'billing',
                    'shipping',
                    'countryS',
                    'countryB'
                ))
                ->from('Shopware\Models\Order\Order', 'orders')
                ->leftJoin('orders.customer', 'customer')
                ->leftJoin('orders.attribute', 'attribute')
                ->leftJoin('orders.details', 'details')
                ->leftJoin('details.tax', 'tax')
                ->leftJoin('orders.billing', 'billing')
                ->leftJoin('orders.shipping', 'shipping')
                ->leftJoin('billing.country', 'countryS')
                ->leftJoin('shipping.country', 'countryB')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            foreach ($orders as $orderSW) {

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
                        $container->add('customer_order_attr', $customerOrderAttr->getPublic(array("_fields", "_isEncrypted")), false);
                    }
                }

                $container->add('customer_order', $order->getPublic(array('_fields', '_isEncrypted')), false);

                // CustomerOrderItemVariations

                // CustomerOrderPaymentInfos
            }
            
            /*
            "customer_order" => array("CustomerOrder", "CustomerOrders"),
            "customer_order_attr" => array("CustomerOrderAttr", "CustomerOrderAttrs"),
            "customer_order_item" => array("CustomerOrderItem", "CustomerOrderItems"),
            "customer_order_item_variation" => array("CustomerOrderItemVariation", "CustomerOrderItemVariations"),
            "customer_order_payment_info" => array("CustomerOrderPaymentInfo", "CustomerOrderPaymentInfos"),
            "customer_order_shipping_address" => array("CustomerOrderShippingAddress", "CustomerOrderShippingAddresss"),
            "customer_order_billing_address" => array("CustomerOrderBillingAddress", "CustomerOrderBillingAddresss")
            */

            $result[] = $container->getPublic(array("items"), array("_fields", "_isEncrypted"));

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
            $result = new TransactionResult();
            $result->setTransactionId($trid);

            if ($this->insert($container)) {
                $action->setResult($result->getPublic());
            }
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