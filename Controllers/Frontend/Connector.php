<?php
class Shopware_Controllers_Frontend_Jtlconnector extends Enlight_Controller_Action
{
    public function preDispatch()
    {
        if(in_array($this->Request()->getActionName(), array('index'))) {
            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        }
    }

    public function indexAction()
    {
        session_destroy();
        define('APP_DIR', realpath(__DIR__ . '/../../src/'));
        try {
            include_once(APP_DIR . '/bootstrap.php');
        }
        catch (\Exception $exc) {
            exception_handler($exc);
        }
    }
}