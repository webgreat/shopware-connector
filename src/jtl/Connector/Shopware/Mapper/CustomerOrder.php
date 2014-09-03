<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\ModelContainer\CustomerOrderContainer;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Model\CustomerOrder as CustomerOrderModel;
use \jtl\Connector\Model\CustomerOrderItem;
use \Shopware\Models\Order\Order as OrderModel;
use \Shopware\Models\Order\Detail as DetailModel;
use \jtl\Core\Logger\Logger;
use \jtl\Core\Utilities\Money;
use \jtl\Connector\Shopware\Utilities\Payment as PaymentUtil;
use \jtl\Connector\Shopware\Utilities\Status as StatusUtil;

class CustomerOrder extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Order')->find($id);
    }

    public function findAll($offset = 0, $limit = 100, $count = false, $from = null, $until = null)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(
            'orders',
            'history'
        )
        ->from('Shopware\Models\Order\Order', 'orders')
        ->leftJoin('orders.history', 'history');

        if ($from !== null && $until !== null) {
            $builder->where('orders.orderTime >= :from')
                    ->andWhere('orders.orderTime <= :until')
                    ->andWhere('history.changeDate is null or history.changeDate >= :from')
                    ->andWhere('history.changeDate is null or history.changeDate <= :until')
                    ->setParameter('from', $from)
                    ->setParameter('until', $until);
        }

        if ($offset !== null && $limit !== null) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $es = $builder->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $entityCount = count($es);
        $lastIndex = $entityCount - 1;

        if ($count) {
            return $entityCount;
        }

        if ($entityCount > 0) {
            return $this->Manager()->createQueryBuilder()->select(array(
                'orders',
                'customer',
                'attribute',
                'details',
                'tax',
                'billing',
                'shipping',
                'countryS',
                'countryB',
                'history'
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
            ->leftJoin('orders.history', 'history')
            ->where('orders.id BETWEEN :first AND :last')
            ->setParameter('first', $es[0]['id'])
            ->setParameter('last', $es[$lastIndex]['id'])
            ->getQuery()
            ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function save(CustomerOrderModel $customerOrder)
    {
        $orderSW = null;
        $result = new CustomerOrderModel;

        if ($customerOrder->getAction() == DataModel::ACTION_DELETE) { // DELETE
            $this->deleteOrderData($customerOrder, $orderSW);
        } else { // UPDATE or INSERT
            $this->prepareOrderAssociatedData($customerOrder, $orderSW);
            $this->prepareItemsAssociatedData($customerOrder, $orderSW);

            $violations = $this->Manager()->validate($orderSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            // Save Order
            $this->Manager()->persist($orderSW);
            $this->Manager()->flush();
        }
        
        // CustomerOrderPaymentInfo
        // CustomerOrderItem
        // CustomerOrderBillingAddress
        // CustomerOrderAttr
        
        $result->setId(new Identity($customerOrderSW->getId(), $customerOrder->getId()->getHost()));

        return $result;
    }

    protected function deleteOrderData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        $orderId = (strlen($customerOrder->getId()->getEndpoint()) > 0) ? (int)$customerOrder->getId()->getEndpoint() : null;

        if ($orderId !== null && $orderId > 0) {
            $orderSW = $this->find($orderId);
            if ($orderSW !== null) {
                $this->removeItems($orderSW);
                $this->removeBilling($orderSW);
                $this->removeShipping($orderSW);

                $this->Manager()->remove($orderSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function removeItems(\Shopware\Models\Order\Order &$orderSW)
    {
        foreach ($orderSW->getDetails() as $detailSW) {
            $this->Manager()->remove($detailSW);
        }
    }

    protected function removeBilling(\Shopware\Models\Order\Order &$orderSW)
    {
        $this->Manager()->remove($orderSW->getBilling());
    }

    protected function removeShipping(\Shopware\Models\Order\Order &$orderSW)
    {
        $this->Manager()->remove($orderSW->getShipping());
    }

    protected function prepareOrderAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        $orderId = (strlen($customerOrder->getId()->getEndpoint()) > 0) ? (int)$customerOrder->getId()->getEndpoint() : null;

        if ($orderId !== null && $orderId > 0) {
            $orderSW = $this->find($orderId);
        } elseif (strlen($customerOrder->getOrderNumber()) > 0) {
            $orderSW = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $customerOrder->getOrderNumber()));
        }

        if ($orderSW === null) {
            $orderSW = new \Shopware\Models\Order\Order;
        }

        // Customer
        $customerMapper = Mmc::getMapper('Customer');
        $customer = $customerMapper->find($customerOrder->getCustomerId()->getEndpoint());
        if ($customer === null) {
            throw new \Exception(sprintf('Customer with id (%s) not found', $customerOrder->getCustomerId()->getEndpoint()));
        }

        // CurrencyFactor
        $currencySW = $this->Manager()->getRepository('Shopware\Models\Shop\Currency')->findOneBy(array('currency' => $customerOrder->getLocaleName()));
        if ($currencySW === null) {
            throw new \Exception(sprintf('Currency with iso (%s) not found', $customerOrder->getLocaleName()));
        }

        // Payment
        $paymentName = PaymentUtil::mapCode($customerOrder->getPaymentModuleCode());
        if ($paymentName === null) {
            throw new \Exception(sprintf('Payment with code (%s) not found', $customerOrder->getPaymentModuleCode()));
        }

        $paymentSW = $this->Manager()->getRepository('Shopware\Models\Payment\Payment')->findOneBy(array('name' => $paymentName));
        if ($paymentSW === null) {
            throw new \Exception(sprintf('Payment with name (%s) not found', $paymentName));
        }

        // Order Status
        $status = StatusUtil::mapStatus($customerOrder->getStatus());
        if ($status === null) {
            throw new \Exception(sprintf('OrderStatus with status (%s) not found', $customerOrder->getStatus()));
        }

        $statusSW = $this->Manager()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $status));
        if ($statusSW === null) {
            throw new \Exception(sprintf('OrderStatus with id (%s) not found', $status));
        }

        $orderSW->setNumber($customerOrder->getOrderNumber())
            ->setInvoiceAmountNet($customerOrder->getTotalSum())
            ->setOrderTime($customerOrder->getCreated())
            ->setCustomerComment($customerOrder->getNote())
            ->setNet(0)
            ->setTrackingCode($customerOrder->getTracking())
            ->setLanguageIso($customerOrder->getLocaleName())
            ->setCurrency($customerOrder->getCurrencyIso())
            ->setRemoteAddress($customerOrder->getIp())
            ->setCustomer($customer)
            ->setCurrencyFactor($currencySW->getFactor())
            ->setPayment($paymentSW)
            ->setDispatch()
            ->setPaymentStatus()
            ->setOrderStatus($statusSW)
            ->setShop()
            ->setShipping()
            ->setBilling()
            ->setHistory()
            ->setAttribute()
            ->setPartner()
            ->setDocuments()
            ->setEsd()
            ->setLanguageSubShop()
            ->setPaymentInstances();
    }

    protected function prepareItemsAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        $taxFree = 0;
        foreach ($customerOrder->getItems() $as $item) {
            switch ($item->getType()) {
                case CustomerOrderItem::TYPE_PRODUCT:
                    break;
                case CustomerOrderItem::TYPE_SHIPPING:
                    $orderSW->setInvoiceShipping(Money::AsGross($item->getPrice(), $item->getVat()))
                        ->setInvoiceShippingNet($item->getPrice());
                    break;
            }

            if ($item->getVat() > 0) {
                $taxFree = 1;
            }
        }

        $orderSW->setTaxFree($taxFree);
    }

    /*
    public function prepareData(CustomerOrderContainer $container)
    {
        $customerOrder = $container->getMainModel();

        //$customerOrderSW = $this->Manager()->getRepository('Shopware\Models\Order\Order')->find($customerOrder->getId());

        // CustomerOrder
        $data = DataConverter::toArray(DataModel::map(false, null, $customerOrder));

        if (isset($data['customer']['id']) && intval($data['customer']['id']) > 0) {
            $data['customerId'] = $data['customer']['id'];
        }

        // CustomerOrderAttr
        foreach ($container->getCustomerOrderAttrs() as $i => $customerOrderAttr) {
            if (!isset($data['attribute'])) {
                $data['attribute'] = array();
            }

            $data['attribute']['id'] = $customerOrderAttr->getId();
            $data['attribute']['orderId'] = $customerOrder->getId();
            $data['attribute']['attribute' . ($i + 1)] = $customerOrderAttr->getValue();
        }

        // CustomerOrderItems
        foreach ($container->getCustomerOrderItems() as $customerOrderItem) {
            if (!isset($data['details'])) {
                $data['details'] = array();
            }

            $detail = new DetailModel();
            $detail->fromArray(DataConverter::toArray(DataModel::map(false, null, $customerOrderItem)));

            $data['details'][] = $detail;
        }

        // CustomerOrderBillingAddress
        if (isset($data['billing']['id']) && intval($data['billing']['id']) > 0) {
            $billing = Shopware()->Models()->getRepository('Shopware\Models\Order\Billing')->findOneBy(array(
                'id' => $data['billing']['id']
            ));

            if (empty($billing)) {
                throw new ApiException\NotFoundException(sprintf("Billing by id %s not found", $data['billing']['id']));
            }

            $data['billing'] = $billing->fromArray($data['billing']);
        }

        // CustomerOrderShippingAddress
        if (isset($data['shipping']['id']) && intval($data['shipping']['id']) > 0) {
            $shipping = Shopware()->Models()->getRepository('Shopware\Models\Order\Shipping')->findOneBy(array(
                'id' => $data['shipping']['id']
            ));

            if (empty($shipping)) {
                throw new ApiException\NotFoundException(sprintf("Shipping by id %s not found", $data['shipping']['id']));
            }

            $data['shipping'] = $shipping->fromArray($data['shipping']);
        }

        // getCustomerOrderItems

        // customer_order_item_variation

        // customer_order_payment_info

        return $data;
    }
    */
}