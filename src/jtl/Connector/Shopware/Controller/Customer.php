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
use \jtl\Connector\Shopware\Utilities\Salutation;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;

/**
 * Customer Controller
 * @access public
 */
class Customer extends DataController
{
    /**
     * Pull
     * 
     * @param \jtl\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();

            $offset = $queryFilter->isOffset() ? $queryFilter->getOffset() : 0;
            $limit = $queryFilter->isLimit() ?  $queryFilter->getLimit() : 100;

            $mapper = Mmc::getMapper('Customer');
            $customers = $mapper->findAll($offset, $limit);

            foreach ($customers as $customerSW) {
                try {
                    $customerSW['newsletter'] = (bool)$customerSW['newsletter'];
                    $customer = Mmc::getModel('Customer');
                    $customer->map(true, DataConverter::toObject($customerSW, true));

                    $country = Shopware()->Models()->getRepository('Shopware\Models\Country\Country')
                        ->findOneById($customerSW['billing']['countryId']);

                    // Salutation
                    $customer->setSalutation(Salutation::toConnector($customer->getSalutation()))
                        ->setCountryIso($country->getIso());

                    // Attributes
                    /*
                     * @todo: waiting for entity
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
                    */

                    $result[] = $customer->getPublic();
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
}