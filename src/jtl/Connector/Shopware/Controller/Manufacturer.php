<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Result\Transaction as TransactionResult;
use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Model\QueryFilter;
use \jtl\Connector\ModelContainer\ManufacturerContainer;
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

            $mapper = Mmc::getMapper('Manufacturer');
            $manufacturers = $mapper->findAll($offset, $limit);
            foreach ($manufacturers as $manufacturerSW) {
                try {
                    $container = new ManufacturerContainer();

                    $manufacturer = Mmc::getModel('Manufacturer');
                    $manufacturer->map(true, DataConverter::toObject($manufacturerSW));

                    $this->addContainerPos($container, 'manufacturer_i18n', $manufacturerSW);

                    $container->add('manufacturer', $manufacturer, false);

                    $result[] = $container->getPublic(array('items'));
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
     * @return \jtl\Connector\ModelContainer\ManufacturerContainer
     */
    public function insert(CoreContainer $container)
    {
        $config = $this->getConfig();

        $mapper = Mmc::getMapper('Manufacturer');
        $data = $mapper->prepareData($container);
        $modelSW = $mapper->save($data);

        $resultContainer = new ManufacturerContainer();

        // Manufacturer
        $main = $container->getMainModel();
        $resultContainer->addIdentity('manufacturer', new Identity($modelSW->getId(), $main->getId()->getHost()));

        return $resultContainer;
    }
}