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
use \jtl\Core\Utilities\DataInjector;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;

/**
 * Specific Controller
 * @access public
 */
class Specific extends DataController
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

            $specificMapper = Mmc::getMapper('Specific');
            $optionSWs = $specificMapper->findAll($offset, $limit);

            DataInjector::inject(DataInjector::TYPE_ARRAY, $optionSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);

            foreach ($optionSWs as $optionSW) {
                try {
                    $specific = Mmc::getModel('Specific');
                    $specific->map(true, DataConverter::toObject($optionSW, true));

                    DataInjector::inject(DataInjector::TYPE_ARRAY, $optionSW, 'specificId', $optionSW['id']);

                    $this->addPos($specific, 'addI18n', 'SpecificI18n', $optionSW);
                    if (isset($optionSW['translations'])) {
                        foreach ($optionSW['translations'] as $localeName => $translation) {
                            $specificI18n = Mmc::getModel('SpecificI18n');
                            $specificI18n->setLocaleName($localeName)
                                ->setSpecificId(new Identity($optionSW['id']))
                                ->setName($translation['name']);

                            $specific->addI18n($specificI18n);
                        }
                    }

                    // SpecificValues
                    foreach ($optionSW['values'] as $valueSW) {
                        $specificValue = Mmc::getModel('SpecificValue');
                        $specificValue->map(true, DataConverter::toObject($valueSW, true));

                        DataInjector::inject(DataInjector::TYPE_ARRAY, $valueSW, 'specificValueId', $valueSW['id']);

                        $this->addPos($specificValue, 'addI18n', 'SpecificValueI18n', $valueSW);
                        if (isset($valueSW['translations'])) {
                            foreach ($valueSW['translations'] as $localeName => $translation) {
                                $specificValueI18n = Mmc::getModel('SpecificValueI18n');
                                $specificValueI18n->setLocaleName($localeName)
                                    ->setSpecificValueId(new Identity($valueSW['id']))
                                    ->setValue($translation['name']);

                                $specificValue->addI18n($specificValueI18n);
                            }
                        }

                        $specific->addValue($specificValue);
                    }

                    $result[] = $specific->getPublic();
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

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
}