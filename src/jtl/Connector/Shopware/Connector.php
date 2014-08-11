<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware
 */
namespace jtl\Connector\Shopware;

use \jtl\Core\Config\Config;
use \jtl\Core\Config\Loader\Json as ConfigJson;
use \jtl\Core\Config\Loader\System as ConfigSystem;
use \jtl\Core\Exception\TransactionException;
use \jtl\Core\Rpc\RequestPacket;
use \jtl\Connector\Base\Connector as BaseConnector;
use \jtl\Core\Utilities\RpcMethod;
use \jtl\Core\Controller\Controller as CoreController;
use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Connector\ModelContainer\MainContainer;

/**
 * Shopware Connector
 *
 * @access public
 * @author Daniel Böhmer <daniel.boehmer@jtl-software.com
 */
class Connector extends BaseConnector
{
    /**
     * Current Controller
     *
     * @var \jtl\Core\Controller\Controller
     */
    protected $controller;
    
    /**
     * @var string
     */
    protected $action;
    
    protected function __construct()
    {
        $this->initializeConfiguration();
        $this->setModelNamespace('jtl\Connector\Shopware\Model');
    }
    
    protected function initializeConfiguration()
    {
        $config = null;
        if (isset($_SESSION['config'])) {
            $config = $_SESSION['config'];
        }
                
        if (empty($config)) {
            if (!is_null($this->config)) {
                $config = $this->getConfig();
            }

            if (empty($config)) {
                // Application object is not initialized. Bypass by manually creating
                // the Config object
                $json = new ConfigJson(realpath(APP_DIR . '/../config/') . '/config.json');
                $config = new Config(array(
                    $json,
                    new ConfigSystem()
                ));

                $this->setConfig($config);
            }
        }

        if (!isset($_SESSION['config'])) {
            $_SESSION['config'] = $config;
        }
    }


    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::canHandle()
     */
    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        
        $class = "\\jtl\\Connector\\Shopware\\Controller\\{$controller}";
        if (class_exists($class)) {
            $this->controller = $class::getInstance();
            $this->action = RpcMethod::buildAction($this->getMethod()->getAction());

            return is_callable(array($this->controller, $this->action));
        }

        return false;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::handle()
     */
    public function handle(RequestPacket $requestpacket)
    {
        $config = $this->getConfig();
        
        // Set the config to our controller 
        $this->controller->setConfig($config);

        // Set the method to our controller
        $this->controller->setMethod($this->getMethod());
        
        return $this->controller->{$this->action}($requestpacket->getParams());
    }
    
    /**
     * Getter Controller
     * 
     * @return \jtl\Core\Controller\Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Setter Controller
     * 
     * @param \jtl\Core\Controller\Controller $controller
     */
	public function setController(CoreController $controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * Getter Action
     * 
     * @return string
     */
	public function getAction()
    {
        return $this->action;
    }

    /**
     * Setter Action
     * 
     * @param string $action
     */
	public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }
}