<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Controller\Controller as CoreController;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;

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
}