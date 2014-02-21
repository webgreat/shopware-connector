<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\ModelContainer\CoreContainer;
use \jtl\Core\Controller\Controller as CoreController;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Model\Statistic;
use \jtl\Core\Utilities\ClassName;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Transaction\Handler as TransactionHandler;

/**
 * Product Controller
 * @access public
 */
abstract class DataController extends CoreController
{
    /**
     * Statistic
     *
     * @params mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function statistic($params)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $class = ClassName::getFromNS(get_called_class());
            
            $statModel = new Statistic();
            $mapper = Mmc::getMapper($class);

            $statModel->_available = $mapper->fetchCount();

            $statModel->_pending = 0;
            if (is_callable(array($mapper, 'fetchPendingCount'))) {
                $statModel->_pending = $mapper->fetchPendingCount();
            }

            $statModel->_controllerName = lcfirst($class);

            $action->setResult($statModel->getPublic());
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
     * Insert or update
     *
     * @params mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function push($params)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $class = ClassName::getFromNS(get_called_class());
            
            $obj = Mmc::getModel($class);
            $obj->setOptions($params);

            $array = DataConverter::toArray($obj->map());

            $mapper = Mmc::getMapper($class);
            $result = $mapper->save($array);
            if ($result->getErrno() > 0) {
                throw new DatabaseException($result->getError(), $result->getErrno());
            }
            else {
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
    
    /**
     * Select
     *
     * @params mixed $params
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

            $class = ClassName::getFromNS(get_called_class());

            $mapper = Mmc::getMapper($class);
            $models = $mapper->findAll($offset, $limit);

            foreach ($models as $modelSW) {
                $model = Mmc::getModel($class);
                $model->map(true, DataConverter::toObject($modelSW));

                $result[] = $model->getPublic();
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
     * Delete
     *
     * @params mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function delete($params)
    {
        $action = new Action();
        $action->setHandled(true);

        return $action;
    }

    /**
     * (non-PHPdoc)
     * @see \jtl\Core\ModelContainer\IModelContainer::insert()
     */
    public function insert(ModelContainer $container)
    {
        if (TransactionHandler::isMain($this->getMethod()->getController())) {
            $config = $this->getConfig();
            $item = array();
            foreach ($container->items as $items) {
                $getter = "get" . ucfirst($items[1]);
                $class = $items[0];
                
                $sub = array();
                foreach ($container->$getter() as $model) {
                    $sub = array_merge($sub, DataConverter::toArray(DataModel::map(false, null, $model)));
                }

                $item = array_merge($item, $sub);
            }

            $class = ClassName::getFromNS(get_called_class());

            $mapper = Mmc::getMapper($class);
            var_dump($mapper->save($item));
                    
            return true;
        }
        
        return false;
    }

    /**
     * Add Item to Container
     * 
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @param string $type
     * @param multiple: mixed $kvs
     * @param multiple: mixed $members
     */
    protected function addContainerPos(CoreContainer &$container, $type, $data, $isSeveral = false)
    {
        if (isset($container->items[$type][0]) && $data !== null) {
            $class = $container->items[$type][0];

            if ($isSeveral) {
                foreach ($data as $swArr) {
                    $model = Mmc::getModel($class);
                    $model->map(true, DataConverter::toObject($swArr));
                    $container->add($type, $model, false);
                }
            }
            else {
                $model = Mmc::getModel($class);
                $model->map(true, DataConverter::toObject($data));
                $container->add($type, $model, false);
            }
        }
    }
}