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
use \jtl\Connector\ModelContainer\CustomerContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;

/**
 * Customer Controller
 * @access public
 */
class Customer extends DataController
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

            $builder = Shopware()->Models()->createQueryBuilder();

            $customers = $builder->select(array(
                    'customer',
                    'billing',
                    'shipping',
                    'customergroup',
                    'attribute',
                    'shop',
                    'locale'
                ))
                ->from('Shopware\Models\Customer\Customer', 'customer')
                ->leftJoin('customer.billing', 'billing')
                ->leftJoin('customer.shipping', 'shipping')
                ->leftJoin('customer.group', 'customergroup')
                ->leftJoin('billing.attribute', 'attribute')
                ->leftJoin('customer.languageSubShop', 'shop')
                ->leftJoin('shop.locale', 'locale')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            foreach ($customers as $customerSW) {
                $container = new CustomerContainer();

                $customer = Mmc::getModel('Customer');
                $customer->map(true, DataConverter::toObject($customerSW));

                $country = Shopware()->Models()->getRepository('Shopware\Models\Country\Country')
                    ->findOneById($customerSW['billing']['countryId']);

                $customer->_countryIso = $country->getIso();

                // Attributes
                $attributeExists = false;
                if (isset($customerSW['billing']['attribute']) && is_array($customerSW['billing']['attribute'])) {
                    $attributeExists = true;
                    for ($i = 1; $i <= 6; $i++) {
                        if (isset($customerSW['billing']['attribute']["text{$i}"]) && strlen(trim($customerSW['billing']['attribute']["text{$i}"]))) {
                            $customerAttr = Mmc::getModel('CustomerAttr');
                            $customerAttr->map(true, DataConverter::toObject($customerSW['billing']['attribute']));
                            $customerAttr->_customerId = $customer->_id;
                            $customerAttr->_key = "text{$i}";
                            $customerAttr->_value = $customerSW['billing']['attribute']["text{$i}"];

                            $container->add('customer_attr', $customerAttr->getPublic(array("_fields", "_isEncrypted")), false);
                        }
                    }
                }

                $container->add('customer', $customer->getPublic(array('_fields', '_isEncrypted')), false);

                $result[] = $container->getPublic(array("items"), array("_fields", "_isEncrypted"));
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