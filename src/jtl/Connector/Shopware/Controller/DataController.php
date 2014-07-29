<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Controller\Controller as CoreController;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Model\Statistic;
use \jtl\Core\Utilities\ClassName;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;

/**
 * Product Controller
 * @access public
 */
abstract class DataController extends CoreController
{
    /**
     * Statistic
     *
     * @param mixed $params
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
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

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
     * @param mixed $params
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
            $model = $mapper->save($array);
            if ($model === null) {
                throw new DatabaseException($result->getError(), $result->getErrno());
            }
            else {
                $action->setResult($result->getPublic());
            }
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
     * Select
     *
     * @param mixed $params
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
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

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
     * @param mixed $params
     * @return \jtl\Connector\Result\Action
     */
    public function delete($params)
    {
        $action = new Action();
        $action->setHandled(true);

        return $action;
    }

    /**
     * Add Subobject to Object
     * 
     * @param \jtl\Connector\Model\DataModel $model
     * @param string $setter
     * @param string $className
     * @param multiple: mixed $kvs
     * @param multiple: mixed $members
     */
    protected function addPos(\jtl\Connector\Model\DataModel &$model, $setter, $className, $data, $isSeveral = false)
    {
        $callableName = get_class($model) . '::' . $setter;

        if (is_callable(array($model, $setter), false, $callableName) && $data !== null) {
            if ($isSeveral) {
                foreach ($data as $swArr) {
                    $subModel = Mmc::getModel($className);
                    $subModel->map(true, DataConverter::toObject($swArr, true));
                    $model->{$setter}($subModel);
                }
            }
            else {
                $subModel = Mmc::getModel($className);
                $subModel->map(true, DataConverter::toObject($data, true));
                $model->{$setter}($subModel);
            }
        } else {
            throw new \InvalidArgumentException(sprintf('Method %s in class %s not found', $setter, get_class($model)));
        }
    }
}