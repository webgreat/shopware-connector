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
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Core\Logger\Logger;

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

            // Companies
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
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

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

            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }

    /**
     * Insert
     *
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @return \jtl\Connector\Result\Transaction
     */
    public function insert(ModelContainer $container)
    {
        // Companies
        $configMapper = Mmc::getMapper('Config');
        foreach ($container->getCompanies() as $company) {
            $configMapper->update('company', $company->_name);
            $configMapper->update('address', $company->_street);
            $configMapper->update('mail', $company->_eMail);
            $configMapper->update('taxNumber', $company->_taxIdNumber);
            $configMapper->update('vatcheckadvancednumber', $company->_vatNumber);
        }

        // Languages

        // Currencies
        $currencyMapper = Mmc::getMapper('Currency');
        foreach ($container->getCurrencies() as $currency) {
            try {
                $data = DataConverter::toArray(DataModel::map(false, null, $currency));
                $currencyMapper->save($data);
            } catch (\Exception $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
            }
        }

        // CustomerGroups
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        foreach ($container->getCustomerGroups() as $customerGroup) {
            try {
                $data = DataConverter::toArray(DataModel::map(false, null, $customerGroup));
                $customerGroupMapper->save($data);
            } catch (\Exception $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
            }
        }

        // Units
        $unitMapper = Mmc::getMapper('Unit');
        foreach ($container->getUnits() as $unit) {
            try {
                $data = DataConverter::toArray(DataModel::map(false, null, $unit));
                $unitMapper->save($data);
            } catch (\Exception $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
            }
        }

        // TaxRates
        $taxRatesMapper = Mmc::getMapper('TaxRate');
        foreach ($container->getTaxRates() as $taxRate) {
            try {
                $data = DataConverter::toArray(DataModel::map(false, null, $taxRate));
                $taxRatesMapper->save($data);
            } catch (\Exception $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
            }
        }

        $mapper = Mmc::getMapper($class);
        $data = $mapper->prepareData($container);
        $modelSW = $mapper->save($data);

        $model = $container->getMainModel();

        $result = new \jtl\Connector\Result\Transaction();
        $result->setId(new \jtl\Connector\Model\Identity($modelSW->getId(), $model->getId()->getHost()));

        return $result;
    }
}