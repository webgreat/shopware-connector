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
                } catch (\Exception $exc) { }
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