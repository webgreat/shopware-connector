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
use \jtl\Connector\Shopware\Utilities\Salutation;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;

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

            $mapper = Mmc::getMapper('Customer');
            $customers = $mapper->findAll($offset, $limit);

            foreach ($customers as $customerSW) {
                try {
                    $container = new CustomerContainer();

                    $customer = Mmc::getModel('Customer');
                    $customer->map(true, DataConverter::toObject($customerSW));

                    // Salutation
                    $customer->_salutation = Salutation::map($customer->_salutation);

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

                                $container->add('customer_attr', $customerAttr, false);
                            }
                        }
                    }

                    $container->add('customer', $customer, false);

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