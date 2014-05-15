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
use \jtl\Core\Utilities\DataInjector;
use \jtl\Connector\ModelContainer\GlobalDataContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;

/**
 * GlobalData Controller
 * @access public
 */
class GlobalData extends DataController
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

            $container = new GlobalDataContainer();

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $builder = Shopware()->Models()->createQueryBuilder();

            // Companys
            $company = Mmc::getModel('Company');

            Shopware()->Config()->setShop(Shopware()->Shop());
            $company->_name = Shopware()->Config()->get('company');
            $company->_street = Shopware()->Config()->get('address');
            $company->_eMail = Shopware()->Config()->get('mail');
            $company->_taxIdNumber = Shopware()->Config()->get('taxNumber');
            $company->_vatNumber = Shopware()->Config()->get('vatcheckadvancednumber');

            $container->add('company', $company, false);

            foreach ($shops as $shop) {

                $shop['locale']['default'] = (intval($shop['default']) == 1);
                $shop['customerGroup']['localeName'] = $shop['locale']['locale'];

                // Languages
                $language = Mmc::getModel('Language');
                $language->map(true, DataConverter::toObject($shop['locale']));

                $container->add('language', $language, false);

                // Currencies
                if (isset($shop['currencies']) && is_array($shop['currencies'])) {
                    foreach ($shop['currencies'] as $currencySW) {
                        $currencySW['hasCurrencySignBeforeValue'] = ($currencySW['position'] == 32) ? true : false;

                        $currency = Mmc::getModel('Currency');
                        $currency->map(true, DataConverter::toObject($currencySW));

                        $container->add('currency', $currency, false);
                    }
                }
            }

            // CustomerGroups
            $mapper = Mmc::getMapper('CustomerGroup');
            $customerGroups = $mapper->findAll($offset, $limit);

            for ($i = 0; $i < count($customerGroups); $i++) {
                $customerGroups[$i]['taxInput'] = !(bool)$customerGroups[$i]['taxInput'];
            }

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroups, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            $this->addContainerPos($container, 'customer_group', $customerGroups, true);
            $this->addContainerPos($container, 'customer_group_i18n', $customerGroups, true);

            // CustomerGroupAttrs

            // CrossSellingGroups

            // Units
            $mapper = Mmc::getMapper('Unit');
            $units = $mapper->findAll($offset, $limit);

            //DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroups, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            $this->addContainerPos($container, 'unit', $units, true);

            // TaxZones

            // TaxZoneCountries

            // TaxClasss

            // TaxRates
            $mapper = Mmc::getMapper('TaxRate');
            $taxes = $mapper->findAll($offset, $limit);

            $this->addContainerPos($container, 'tax_rate', $taxes, true);

            // ShippingClasss
            
            $result[] = $container->getPublic(array("items"));

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
            $message = (strlen($exc->getMessage()) > 0) ? $exc->getMessage() : "unknown";

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }
}