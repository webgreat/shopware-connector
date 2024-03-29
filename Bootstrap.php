<?php
class Shopware_Plugins_Frontend_Jtlconnector_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => false,
            'enable' => true,
        );
    }

    public function getLabel()
    {
        return 'JTL Shopware Connector';
    }
 
    public function getVersion()
    {
        return '1.0.0';
    }
 
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => 'JTL-Software GmbH',
            'description' => '',
            'support' => 'JTL-Software Forum',
            'link' => 'http://forum.jtl-software.de'
        );
    }
 
    public function install()
    {
        if (!$this->assertVersionGreaterThen('4.2.3')) {
            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens die Shopware Version 4.2.3'
            );
        }

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Jtlconnector',
            'onGetControllerPathFrontend'
        );

        /*
        $form = $this->Form();
 
        $form->setElement('text', 'auth_token', 
            array(
                'label' => 'Auth Token',
                'value' => '',
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            )
        );
        */

        return array(
    		'success' => true,
    		'invalidateCache' => array('backend', 'proxy')
        );
	}

    public function enable()
    {
        return true;
    }

    public function disable()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public static function onGetControllerPathFrontend(Enlight_Event_EventArgs $args)
    {
        return dirname(__FILE__) . '/Controllers/Frontend/Connector.php';
    }
}