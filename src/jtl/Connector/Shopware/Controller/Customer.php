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

            $customerResource = ShopwareManager::getResource('Customer');
            $customers = $customerResource->getList($offset, $limit, $filter->getFilters());

            foreach ($customers['data'] as $customerSW) {
                $container = new CustomerContainer();

                $customer = Mmc::getModel('Customer');
                $customer->map(true, DataConverter::toObject($customerSW));

                if ($customerTmp = $customerResource->getRepository()->find($customer->_id)) {
                    $customerGroupResource = ShopwareManager::getResource('CustomerGroup');
                    if ($customerGroup = $customerGroupResource->getRepository()->findOneBy(array('key' => $customerSW['groupKey']))) {
                        $customer->_customerGroupId = $customerGroup->getId();
                    }

                    if ($billing = $customerTmp->getBilling()) {
                        if ($country = $customerResource->getManager()->getRepository('\Shopware\Models\Country\Country')->findOneBy(array('id' => $billing->getCountryId()))) {
                            $customer->_countryIso = $country->getIso();
                        }

                        if ($billingAttr = $billing->getAttribute()) {
                            for ($i = 1; $i <= 6; $i++) {
                                $member = "getText{$i}";
                                if (strlen(trim($billingAttr->$member())) > 0) {
                                    $customerAttr = Mmc::getModel('CustomerAttr');
                                    $customerAttr->_id = $billingAttr->getId() . "_{$i}";
                                    $customerAttr->_customerId = $customer->_id;
                                    $customerAttr->_key = "Text{$i}";
                                    $customerAttr->_value = $billingAttr->$member();

                                    $container->add('customer_attr', $customerAttr->getPublic(array('_fields', '_isEncrypted')), false);
                                }
                            }
                        }

                        $customer->_customerNumber = $billing->getNumber();
                        $customer->_salutation = $billing->getSalutation();
                        $customer->_firstName = $billing->getFirstName();
                        $customer->_lastName = $billing->getLastName();
                        $customer->_company = $billing->getCompany();
                        $customer->_street = $billing->getStreet();
                        $customer->_streetNumber = $billing->getStreetNumber();
                        $customer->_zipCode = $billing->getZipCode();
                        $customer->_city = $billing->getCity();
                        $customer->_phone = $billing->getPhone();
                        $customer->_fax = $billing->getFax();
                        $customer->_vatNumber = $billing->getVatId();
                        $customer->_birthday = $billing->getBirthday();
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