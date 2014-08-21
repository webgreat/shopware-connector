<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Exception\DatabaseException;
use \Shopware\Components\Api\Manager as ShopwareManager;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataInjector;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Core\Logger\Logger;
use \Shopware\Models\Customer\Group as GroupModel;

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
            $filter = $params;

            $offset = $filter->isOffset() ? $filter->getOffset() : 0;
            $limit = $filter->isLimit() ?  $filter->getLimit() : 100;

            $globalData = Mmc::getModel('GlobalData');

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $builder = Shopware()->Models()->createQueryBuilder();

            // Companies
            $company = Mmc::getModel('Company');

            Shopware()->Config()->setShop(Shopware()->Shop());
            $company->setName(Shopware()->Config()->get('company'))
                ->setStreet(Shopware()->Config()->get('address'))
                ->setEMail(Shopware()->Config()->get('mail'))
                ->setTaxIdNumber(Shopware()->Config()->get('taxNumber'))
                ->setVatNumber(Shopware()->Config()->get('vatcheckadvancednumber'));

            $globalData->addCompany($company);

            foreach ($shops as $shop) {
                $shop['locale']['default'] = (intval($shop['default']) == 1);
                $shop['customerGroup']['localeName'] = $shop['locale']['locale'];

                // Languages
                $language = Mmc::getModel('Language');
                $language->map(true, DataConverter::toObject($shop['locale'], true));

                $globalData->addLanguage($language);

                // Currencies
                if (isset($shop['currencies']) && is_array($shop['currencies'])) {
                    foreach ($shop['currencies'] as $currencySW) {
                        $currencySW['default'] = (bool)$currencySW['default'];
                        $currencySW['hasCurrencySignBeforeValue'] = ($currencySW['position'] == 32) ? true : false;

                        $currency = Mmc::getModel('Currency');
                        $currency->map(true, DataConverter::toObject($currencySW, true));

                        $globalData->addCurrency($currency);
                    }
                }
            }

            // CustomerGroups
            $mapper = Mmc::getMapper('CustomerGroup');
            $customerGroupSWs = $mapper->findAll($offset, $limit);

            for ($i = 0; $i < count($customerGroupSWs); $i++) {
                $customerGroupSWs[$i]['taxInput'] = !(bool)$customerGroupSWs[$i]['taxInput'];
            }

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroupSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($customerGroupSWs as $customerGroupSW) {
                $customerGroup = Mmc::getModel('CustomerGroup');
                $customerGroup->map(true, DataConverter::toObject($customerGroupSW, true));

                $customerGroupI18n = Mmc::getModel('CustomerGroupI18n');
                $customerGroup->map(true, DataConverter::toObject($customerGroupSW, true));

                $customerGroup->addI18n($customerGroupI18n);
                $globalData->addCustomerGroup($customerGroup);
            }

            // CustomerGroupAttrs

            // CrossSellingGroups

            // Units
            $mapper = Mmc::getMapper('Unit');
            $unitSWs = $mapper->findAll($offset, $limit);

            DataInjector::inject(DataInjector::TYPE_ARRAY, $unitSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($unitSWs as $unitSW) {
                $unit = Mmc::getModel('Unit');
                $unit->map(true, DataConverter::toObject($unitSW, true));

                // @todo: waiting for entity unitI18n
                //$unitI18n = Mmc::getModel('UnitI18n');
                //$unitI18n->map(true, DataConverter::toObject($unitSW, true));

                //$unit->addI18n($unitI18n);
                $globalData->addUnit($unit);
            }

            // TaxZones

            // TaxZoneCountries

            // TaxClasss

            // TaxRates
            $mapper = Mmc::getMapper('TaxRate');
            $taxSWs = $mapper->findAll($offset, $limit);

            foreach ($taxSWs as $taxSW) {
                $taxSW['tax'] = (float)$taxSW['tax'];
                $tax = Mmc::getModel('TaxRate');
                $tax->map(true, DataConverter::toObject($taxSW, true));

                $globalData->addTaxRate($tax);
            }

            // ShippingClasss
            
            $result[] = $globalData->getPublic();

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

    public function push($globalData)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = new GroupModel;

            // Companies
            $configMapper = Mmc::getMapper('Config');
            foreach ($globalData->getCompanies() as $company) {
                $configMapper->update(array('name', 'company'), $company->getName(), Shopware()->Shop()->getId());
                $configMapper->update(array('name', 'address'), $company->getStreet(), Shopware()->Shop()->getId());
                $configMapper->update(array('name', 'mail'), $company->getEMail(), Shopware()->Shop()->getId());
                $configMapper->update(array('name', 'taxNumber'), $company->getTaxIdNumber(), Shopware()->Shop()->getId());
                $configMapper->update(array('name', 'vatcheckadvancednumber'), $company->getVatNumber(), Shopware()->Shop()->getId());
            }

            // CustomerGroups
            $customerGroupMapper = Mmc::getMapper('CustomerGroup');
            foreach ($globalData->getCustomerGroups() as $customerGroup) {
                $customerGroupResult = $customerGroupMapper->save($customerGroup);
                $result->addCustomerGroup($customerGroupResult);
            }
            
            $action->setResult($result->getPublic());
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
}