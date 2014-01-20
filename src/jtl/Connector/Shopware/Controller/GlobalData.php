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
use \jtl\Core\Utilities\DataInjector;
use \jtl\Connector\ModelContainer\GlobalDataContainer;
use \jtl\Connector\Shopware\Utilities\Mmc;

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

            $builder = Shopware()->Models()->createQueryBuilder();

            // Companys
            $company = Mmc::getModel('Company');

            Shopware()->Config()->setShop(Shopware()->Shop());
            $company->_name = Shopware()->Config()->get('company');
            $company->_street = Shopware()->Config()->get('address');
            $company->_eMail = Shopware()->Config()->get('mail');
            $company->_taxIdNumber = Shopware()->Config()->get('taxNumber');
            $company->_vatNumber = Shopware()->Config()->get('vatcheckadvancednumber');

            $container->add('company', $company->getPublic(array('_fields', '_isEncrypted')), false);

            // Languages
            $language = Mmc::getModel('Language');

            $locale = Shopware()->Shop()->getLocale();
            $language->_id = $locale->getId();
            $language->_nameEnglish = $locale->getLanguage();
            $language->_nameGerman = $locale->getLanguage();
            $language->_localeName = $locale->getLocale();
            $language->_isDefault = true;

            $container->add('language', $language->getPublic(array('_fields', '_isEncrypted')), false);

            // Currencies
            $currencies = Shopware()->Shop()->getCurrencies();

            foreach ($currencies as $i => $currSW) {
                $currency = Mmc::getModel('Currency');

                $currency->_id = $currSW->getId();
                $currency->_name = $currSW->getName();
                $currency->_iso = $currSW->getCurrency();
                $currency->_nameHtml = $currSW->getSymbol();
                $currency->_factor = $currSW->getFactor();
                $currency->_isDefault = $currSW->getDefault();
                $currency->_hasCurrencySignBeforeValue = ($currSW->getPosition() == 32) ? true : false;

                $container->add('currency', $currency->getPublic(array('_fields', '_isEncrypted')), false);
            }

            // CustomerGroups
            $customerGroups = $builder->select(array(
                    'customergroup',
                    'attribute'
                ))
                ->from('Shopware\Models\Customer\Group', 'customergroup')
                ->leftJoin('customergroup.attribute', 'attribute')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            for ($i = 0; $i < count($customerGroups); $i++) {
                $customerGroups[$i]['taxInput'] = !(bool)$customerGroups[$i]['taxInput'];
            }

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroups, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            $this->addContainerPos($container, 'customer_group', $customerGroups, true);
            $this->addContainerPos($container, 'customer_group_i18n', $customerGroups, true);

            // CustomerGroupAttrs

            // CrossSellingGroups

            // Units            
            $units = $builder->select(array('units'))
                ->from('Shopware\Models\Article\Unit', 'units')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroups, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            $this->addContainerPos($container, 'unit', $units, true);

            // TaxZones

            // TaxZoneCountries

            // TaxClasss

            // TaxRates
            $taxes = $builder->select(array('taxes'))
                ->from('Shopware\Models\Tax\Tax', 'taxes')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

            $this->addContainerPos($container, 'tax_rate', $taxes, true);

            // ShippingClasss
            
            $result[] = $container->getPublic(array("items"), array("_fields", "_isEncrypted"));

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