<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Core\Model\QueryFilter;

/**
 * Connector Controller
 * @access public
 */
class Connector extends DataController
{
    /**
     * Statistic
     *
     * @param \jtl\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function statistic(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        $path = APP_DIR . '/jtl/Connector/Shopware/Controller/';

        $results = array();
        $mainControllers = array(
            'Category',
            'Customer',
            'CustomerOrder',
            'GlobalData',
            'Image',
            'Product',
            'Manufacturer'
        );

        $excludes = array(
            'DataController',
            'Connector',
            'GlobalData'
        );

        // Only Main Controller
        if ($queryFilter !== null && $queryFilter) {
            foreach ($mainControllers as $mainController) {
                try {
                    if (!in_array($mainController, $excludes)) {
                        $controller = Mmc::getController($mainController);
                        $result = $controller->statistic($queryFilter);
                        if ($result !== null && $result->isHandled() && !$result->isError()) {
                            $results[] = $result->getResult();
                        }
                    }
                } catch(\Exception $exc) { 
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }
        }

        // All Controller except excludes
        else
        {
            foreach (glob("{$path}*.php") as $filename) {
                try {
                    $class = str_replace(array($path, '.php'), '', $filename);
                    if (!in_array($class, $excludes)) {
                        $controller = Mmc::getController(str_replace(array($path, '.php'), '', $filename));
                        $result = $controller->statistic($queryFilter);
                        if ($result !== null && $result->isHandled() && !$result->isError()) {
                            $results[] = $result->getResult();
                        }
                    }
                } catch(\Exception $exc) { 
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }
        }

        $action->setResult($results);

        return $action;
    }
}