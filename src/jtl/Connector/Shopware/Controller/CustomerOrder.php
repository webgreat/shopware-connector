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

            $orders = $builder->select(array('orders'))
                ->from('Shopware\Models\Order\Order', 'orders')
                ->getQuery()->getResult();

            foreach ($orders as $orderSW) {

                // CustomerOrders
                $order = Mmc::getModel('CustomerOrder');

                $order->_id = $orderSW->getId();
                $order->_customerId = $orderSW->getCustomer()->getId();
                $order->_shippingAddressId = $orderSW->getShipping()->getId();
                $order->_billingAddressId = $orderSW->getBilling()->getId();
                //$order->_shippingMethodId = 
                //$order->_localeName = Shopware()->Shop()->getLocale()->getLocale();
                $order->_localeName = $orderSW->getLanguageIso();
                $order->_currencyIso = $orderSW->getCurrency();
                //$order->_estimatedDeliveryDate = 
                //$order->_credit = 
                $order->_totalSum = $orderSW->getInvoiceAmountNet();
                //$order->_session = 
                //$order->_shippingMethodName = 
                $order->_orderNumber = $orderSW->getNumber(); 
                //$order->_shippingInfo = 
                //$order->_shippingDate = 
                //$order->_paymentDate = 
                //$order->_ratingNotificationDate = 
                $order->_tracking = $orderSW->getTrackingCode();
                $order->_note = $orderSW->getCustomerComment();
                //$order->_logistic = 
                //$order->_trackingURL = 
                $order->_ip = $orderSW->getRemoteAddress();
                //$order->_isFetched = 
                //$order->_status = // TODO: Status Mapper
                $order->_created = $orderSW->getOrderTime()->format('Y-m-d H:i:s');
                //$order->_paymentModuleId = // TODO: Payment Mapper

                $container->add('customer_order', $order->getPublic(array('_fields', '_isEncrypted')), false);

                // CustomerOrderItems
                foreach ($orderSW->getdetails() as $detailSW) {
                    $orderItem = Mmc::getModel('CustomerOrderItem');

                    $orderItem->_id = $detailSW->getId();
                    //$orderItem->_basketId = 
                    $orderItem->_productId = $detailSW->getArticleId();
                    //$orderItem->_shippingClassId = 
                    $orderItem->_customerOrderId = $detailSW->getOrder()->getId();
                    $orderItem->_name = $detailSW->getArticleName();
                    $orderItem->_sku = $detailSW->getNumber();
                    $orderItem->_price = $detailSW->getPrice();
                    $orderItem->_vat = $detailSW->getTax()->getTax();
                    $orderItem->_quantity = $detailSW->getQuantity();
                    //$orderItem->_type =
                    //$orderItem->_unique =
                    //$orderItem->_configItemId =

                    $container->add('customer_order_item', $orderItem->getPublic(array('_fields', '_isEncrypted')), false);
                }

                // CustomerOrderBillingAddress
                $orderBilling = Mmc::getModel('CustomerOrderBillingAddress');

                $orderBilling->_id = $orderSW->getBilling()->getId();
                $orderBilling->_customerId = $orderSW->getBilling()->getCustomer()->getId();
                $orderBilling->_salutation = $orderSW->getBilling()->getSalutation();
                $orderBilling->_firstName = $orderSW->getBilling()->getFirstName();
                $orderBilling->_lastName = $orderSW->getBilling()->getLastName();
                //$orderBilling->_title = $orderSW->getBilling()->getId();
                $orderBilling->_company = $orderSW->getBilling()->getCompany();
                //$orderBilling->_deliveryInstruction = $orderSW->getBilling()->getId();
                $orderBilling->_street = $orderSW->getBilling()->getStreet() . ' ' . $orderSW->getBilling()->getStreetNumber();
                //$orderBilling->_extraAddressLine = $orderSW->getBilling()->getId();
                $orderBilling->_zipCode = $orderSW->getBilling()->getZipCode();
                $orderBilling->_city = $orderSW->getBilling()->getCity();
                //$orderBilling->_state = $orderSW->getBilling()->getId();
                $orderBilling->_countryIso = $orderSW->getBilling()->getCountry()->getIso();
                $orderBilling->_phone = $orderSW->getBilling()->getPhone();
                //$orderBilling->_mobile = $orderSW->getBilling()->getId();
                $orderBilling->_fax = $orderSW->getBilling()->getFax();
                //$orderBilling->_eMail = $orderSW->getBilling()->getId();

                $container->add('customer_order_billing_address', $orderBilling->getPublic(array('_fields', '_isEncrypted')), false);

                // CustomerOrderShippingAddress
                $orderShipping = Mmc::getModel('CustomerOrderShippingAddress');

                $orderShipping->_id = $orderSW->getShipping()->getId();
                $orderShipping->_customerId = $orderSW->getShipping()->getCustomer()->getId();
                $orderShipping->_salutation = $orderSW->getShipping()->getSalutation();
                $orderShipping->_firstName = $orderSW->getShipping()->getFirstName();
                $orderShipping->_lastName = $orderSW->getShipping()->getLastName();
                //$orderShipping->_title = $orderSW->getShipping()->getId();
                $orderShipping->_company = $orderSW->getShipping()->getCompany();
                //$orderShipping->_deliveryInstruction = $orderSW->getShipping()->getId();
                $orderShipping->_street = $orderSW->getShipping()->getStreet() . ' ' . $orderSW->getShipping()->getStreetNumber();
                //$orderShipping->_extraAddressLine = $orderSW->getShipping()->getId();
                $orderShipping->_zipCode = $orderSW->getShipping()->getZipCode();
                $orderShipping->_city = $orderSW->getShipping()->getCity();
                //$orderShipping->_state = $orderSW->getShipping()->getId();
                $orderShipping->_countryIso = $orderSW->getShipping()->getCountry()->getIso();
                //$orderShipping->_phone = $orderSW->getShipping()->getPhone();
                //$orderShipping->_mobile = $orderSW->getShipping()->getId();
                //$orderShipping->_fax = $orderSW->getShipping()->getFax();
                //$orderShipping->_eMail = $orderSW->getShipping()->getId();

                $container->add('customer_order_shipping_address', $orderShipping->getPublic(array('_fields', '_isEncrypted')), false);
            }

            // CustomerOrderAttrs

            // CustomerOrderItemVariations

            // CustomerOrderPaymentInfos

            // CustomerOrderShippingAddresss

            

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