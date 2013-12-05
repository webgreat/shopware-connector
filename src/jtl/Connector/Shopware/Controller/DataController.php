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
use \jtl\Core\Utilities\DataConverter;

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
     * Add Item to Container
     * 
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @param string $type
     * @param multiple: mixed $kvs
     * @param multiple: mixed $members
     */
    protected function addContainerPos(CoreContainer &$container, $type, $data, $isSeveral = false)
    {
        if (isset($container->items[$type][0])) {
            $class = $container->items[$type][0];

            if ($isSeveral) {
                foreach ($data as $swArr) {
                    $model = Mmc::getModel($class);
                    $model->map(true, DataConverter::toObject($swArr));
                    $container->add($type, $model->getPublic(array("_fields", "_isEncrypted")), false);
                }
            }
            else {
                $model = Mmc::getModel($class);
                $model->map(true, DataConverter::toObject($data));
                $container->add($type, $model->getPublic(array("_fields", "_isEncrypted")), false);
            }
        }
    }
}