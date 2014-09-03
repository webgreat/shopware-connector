<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;

/**
 * Manufacturer Controller
 * @access public
 */
class Manufacturer extends DataController
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

            $mapper = Mmc::getMapper('Manufacturer');
            $manufacturers = $mapper->findAll($offset, $limit);
            foreach ($manufacturers as $manufacturerSW) {
                try {
                    $manufacturer = Mmc::getModel('Manufacturer');
                    $manufacturer->map(true, DataConverter::toObject($manufacturerSW));

                    $this->addPos($manufacturer, 'addI18n', 'ManufacturerI18n', $manufacturerSW);

                    $result[] = $manufacturer->getPublic();
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