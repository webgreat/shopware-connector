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
        echo "huhu";
    }
}