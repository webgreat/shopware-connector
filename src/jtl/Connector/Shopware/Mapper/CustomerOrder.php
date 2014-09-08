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
use \jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;

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
            $this->prepareCustomerAssociatedData($customerOrder, $orderSW);
            $this->prepareCurrencyFactorAssociatedData($customerOrder, $orderSW);
            $this->preparePaymentAssociatedData($customerOrder, $orderSW);
            $this->prepareStatusAssociatedData($customerOrder, $orderSW);
            $this->prepareShippingAssociatedData($customerOrder, $orderSW);
            $this->prepareBillingAssociatedData($customerOrder, $orderSW);
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

        $orderSW->setNumber($customerOrder->getOrderNumber())
            ->setInvoiceAmountNet($customerOrder->getTotalSum())
            ->setOrderTime($customerOrder->getCreated())
            ->setCustomerComment($customerOrder->getNote())
            ->setNet(0)
            ->setTrackingCode($customerOrder->getTracking())
            ->setLanguageIso($customerOrder->getLocaleName())
            ->setCurrency($customerOrder->getCurrencyIso())
            ->setRemoteAddress($customerOrder->getIp())
            ->setShop(Shopware()->Shop());

            /*
            ->setHistory()
            ->setAttribute()
            ->setPartner()
            ->setDocuments()
            ->setLanguageSubShop()
            ->setPaymentInstances();
            */
    }

    protected function prepareCustomerAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        // Customer
        $customerMapper = Mmc::getMapper('Customer');
        $customer = $customerMapper->find($customerOrder->getCustomerId()->getEndpoint());
        if ($customer === null) {
            throw new \Exception(sprintf('Customer with id (%s) not found', $customerOrder->getCustomerId()->getEndpoint()));
        }

        $orderSW->setCustomer($customer);
    }

    protected function prepareCurrencyFactorAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        // CurrencyFactor
        $currencySW = $this->Manager()->getRepository('Shopware\Models\Shop\Currency')->findOneBy(array('currency' => $customerOrder->getLocaleName()));
        if ($currencySW === null) {
            throw new \Exception(sprintf('Currency with iso (%s) not found', $customerOrder->getLocaleName()));
        }

        $orderSW->setCurrencyFactor($currencySW->getFactor());
    }

    protected function preparePaymentAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        // Payment
        $paymentName = PaymentUtil::mapCode($customerOrder->getPaymentModuleCode());
        if ($paymentName === null) {
            throw new \Exception(sprintf('Payment with code (%s) not found', $customerOrder->getPaymentModuleCode()));
        }

        $paymentSW = $this->Manager()->getRepository('Shopware\Models\Payment\Payment')->findOneBy(array('name' => $paymentName));
        if ($paymentSW === null) {
            throw new \Exception(sprintf('Payment with name (%s) not found', $paymentName));
        }

        $orderSW->setPayment($paymentSW);
    }

    protected function prepareStatusAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        // Order Status
        $status = StatusUtil::map($customerOrder->getStatus());
        if ($status === null) {
            throw new \Exception(sprintf('Order status with status (%s) not found', $customerOrder->getStatus()));
        }

        $statusSW = $this->Manager()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $status));
        if ($statusSW === null) {
            throw new \Exception(sprintf('Order status with id (%s) not found', $status));
        }

        // Payment Status
        $paymentStatus = PaymentStatusUtil::map($customerOrder->getPaymentStatus());
        if ($paymentStatus === null) {
            throw new \Exception(sprintf('Payment status with status (%s) not found', $customerOrder->getPaymentStatus()));
        }

        $paymentStatusSW = $this->Manager()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $paymentStatus));
        if ($paymentStatusSW === null) {
            throw new \Exception(sprintf('Payment status with id (%s) not found', $paymentStatus));
        }

        $orderSW->setPaymentStatus($paymentStatusSW);
        $orderSW->setOrderStatus($statusSW);
    }

    protected function prepareShippingAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        foreach ($customerOrder->getShippingAddress() as $shippingAddress) {
            $shippingSW = null;
            $id = (strlen($shippingAddress->getId()->getEndpoint()) > 0) ? (int)$shippingAddress->getId()->getEndpoint() : null;

            if (strlen($id) > 0) {
                $shippingSW = $this->Manager()->getRepository('Shopware\Models\Order\Shipping')->find((int)$id);
            }

            if ($shippingSW === null) {
                $shippingSW = new \Shopware\Models\Order\Shipping;
            }

            $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $shippingAddress->getCountryIso()));
            if ($countrySW === null) {
                throw new \Exception(sprintf('Country with iso (%s) not found', $shippingAddress->getCountryIso()));
            }

            $shippingSW->setCompany($shippingAddress->getCompany())
                ->setSalutation($shippingAddress->getSalutation())
                ->setFirstName($shippingAddress->getFirstName())
                ->setLastName($shippingAddress->getLastName())
                ->setStreet($shippingAddress->getStreet())
                ->setZipCode($shippingAddress->getZipCode())
                ->setCity($shippingAddress->getCity())
                ->setOrder($orderSW)
                ->setCountry($countrySW);
                //->setAttribute();
            
            $orderSW->setOrder($orderSW);
            $orderSW->setCustomer($orderSW->getCustomer());
            $orderSW->setShipping($billingSW);
        }
    }

    protected function prepareBillingAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        foreach ($customerOrder->getBillingAddress() as $billingAddress) {
            $billingSW = null;
            $id = (strlen($billingAddress->getId()->getEndpoint()) > 0) ? (int)$billingAddress->getId()->getEndpoint() : null;

            if (strlen($id) > 0) {
                $billingSW = $this->Manager()->getRepository('Shopware\Models\Order\Billing')->find((int)$id);
            }

            if ($billingSW === null) {
                $billingSW = new \Shopware\Models\Order\Billing;
            }

            $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $billingAddress->getCountryIso()));
            if ($countrySW === null) {
                throw new \Exception(sprintf('Country with iso (%s) not found', $billingAddress->getCountryIso()));
            }

            $billingSW->setCompany($billingAddress->getCompany())
                ->setSalutation($billingAddress->getSalutation())
                ->setFirstName($billingAddress->getFirstName())
                ->setLastName($billingAddress->getLastName())
                ->setStreet($billingAddress->getStreet())
                ->setZipCode($billingAddress->getZipCode())
                ->setCity($billingAddress->getCity())
                ->setCountry($countrySW);
                //->setAttribute();

            $orderSW->setCustomer($orderSW->getCustomer());
            $orderSW->setOrder($orderSW);
            $orderSW->setBilling($billingSW);
        }
    }

    protected function prepareItemsAssociatedData(DataModel &$customerOrder, \Shopware\Models\Order\Order &$orderSW)
    {
        $taxFree = 0;
        $invoiceShipping = 0.0;
        $invoiceShippingNet = 0.0;
        foreach ($customerOrder->getItems() as $item) {
            switch ($item->getType()) {
                case CustomerOrderItem::TYPE_PRODUCT:
                    $this->prepareItemAssociatedData($item, $orderSW);
                    break;
                case CustomerOrderItem::TYPE_SHIPPING:
                    $invoiceShipping += Money::AsGross($item->getPrice(), $item->getVat());
                    $invoiceShippingNet += $item->getPrice();
                    break;
            }

            if ($item->getVat() > 0) {
                $taxFree = 1;
            }
        }
        
        $orderSW->setInvoiceShipping($invoiceShipping)
            ->setInvoiceShippingNet($invoiceShippingNet)
            ->setTaxFree($taxFree);
    }

    protected function prepareItemAssociatedData(DataModel &$item, \Shopware\Models\Order\Order &$orderSW)
    {
        $detailSW = null;
        $id = (strlen($item->getId()->getEndpoint()) > 0) ? (int)$item->getId()->getEndpoint() : null;

        if (strlen($id) > 0) {
            $detailSW = $this->Manager()->getRepository('Shopware\Models\Order\Detail')->find((int)$id);
        }

        if ($detailSW === null) {
            $detailSW = new \Shopware\Models\Order\Detail;
        }

        $taxRateMapper = Mmc::getMapper('TaxRate');
        $taxRateSW = $taxRateMapper->findOneBy(array('tax' => $item->getVat()));
        if ($taxRateSW === null) {
            throw new \Exception(sprintf('Tax with rate (%s) not found', $item->getVat()));
        }

        $detailSW->setNumber($orderSW->getNumber())
            ->setArticleId($item->getProductId()->getEndpoint())
            ->setPrice($item->getPrice())
            ->setQuantity($item->getQuantity())
            ->setArticleName($item->getName())
            ->setShipped(0)
            ->setShippedGroup(0)
            //->setReleaseDate()
            ->setMode(0)
            ->setEsdArticle(0);
            //->setConfig();

        $detailSW->setTaxRate($item->getVat());
        $detailSW->setArticleNumber($item->getSku());
        //$detailSW->setAttribute();
        //$detailSW->setEsd();
        $detailSW->setTax($taxRateSW);
        $detailSW->setOrder($orderSW);
        $detailSW->setStatus(0);
    }
}