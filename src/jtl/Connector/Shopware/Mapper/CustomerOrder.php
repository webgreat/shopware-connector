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
use \Shopware\Models\Order\Order as OrderModel;
use \Shopware\Models\Order\Detail as DetailModel;

class CustomerOrder extends DataMapper
{
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
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
        ->getQuery();

        if ($count) {
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

            return $paginator->count();
        }
        else {
            return $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(CustomerOrderContainer $container)
    {
        $customerOrders = $container->getCustomerOrders();
        $customerOrder = $customerOrders[0];

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

        // getCustomerOrderItems
        foreach ($container->getCustomerOrderItems() as $customerOrderItem) {
            if (!isset($data['details'])) {
                $data['details'] = array();
            }

            $detail = new DetailModel();
            $detail->fromArray(DataConverter::toArray(DataModel::map(false, null, $customerOrderItem)));

            $data['details'][] = $detail;
        }

        // CustomerOrderBillingAddress
        if (isset($data['billing']['id']) && intval($data['billing']['id']) > 0)
            $billing = Shopware()->Models()->getRepository('Shopware\Models\Order\Billing')->findOneBy(array(
                'id' => $data['billing']['id']
            ));

            if (empty($billing)) {
                throw new ApiException\NotFoundException(sprintf("Billing by id %s not found", $data['billing']['id']));
            }

            $data['billing'] = $billing->fromArray($data['billing']);
        }

        // CustomerOrderShippingAddress
        if (isset($data['shipping']['id']) && intval($data['shipping']['id']) > 0)
            $shipping = Shopware()->Models()->getRepository('Shopware\Models\Order\Shipping')->findOneBy(array(
                'id' => $data['shipping']['id']
            ));

            if (empty($shipping)) {
                throw new ApiException\NotFoundException(sprintf("Shipping by id %s not found", $data['shipping']['id']));
            }

            $data['shipping'] = $shipping->fromArray($data['shipping']);
        }

        return $data;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Order\Order')
    {
        $customerOrderResource = \Shopware\Components\Api\Manager::getResource('Order');

        try {
            return $customerOrderResource->update($data['id'], $data);
        } catch (ApiException\NotFoundException $exc) {
            return $this->create($data);
        }
    }

    protected function create(array $data)
    {
        $customerOrder = new OrderModel();
        $customerOrder->fromArray($data);

        $violations = $this->getManager()->validate($customerOrder);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($customerOrder);
        $this->flush();

        return $customerOrder;
    }

    /**
     * @param int $id
     * @return \Shopware\Models\Customer\Customer
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function delete($id)
    {
        $this->checkPrivilege('delete');

        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $customer \Shopware\Models\Customer\Customer */
        $customer = $this->Manager()->getRepository('Shopware\Models\Customer\Customer')->find($id);

        if (!$customer) {
            throw new ApiException\NotFoundException("Customer by id $id not found");
        }

        $this->Manager()->remove($customer);
        $this->flush();

        return $customer;
    }
}