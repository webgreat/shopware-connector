<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \Shopware\Components\Api\Manager as ShopwareManager;

/**
 * Product Controller
 * @access public
 */
abstract class DataController
{
    /**
     * Add Item to Container
     * 
     * @param \jtl\Connector\ModelContainer\CoreContainer $container
     * @param string $type
     * @param multiple: mixed $kvs
     * @param multiple: mixed $members
     */
    protected function addContainerPos(CoreContainer &$container, $type, $swType, array $kvs = null, array $subItems = null)
    {
        if (isset($container->items[$type][0])) {
            $class = $container->items[$type][0];
            $resource = ShopwareManager::getResource($swType);
            $objs = $resource->getList();

            if ($objs !== null && is_array($objs)) {
                $config = $this->getConfig();
                foreach ($objs as $obj) {                    
                    $model = Mmc::getModel($class);
                    $model->map(true, $obj);

                    // Sub Item
                    if ($subItems !== null) {
                        foreach ($subItems as $subType => $members) {
                            $member = $members[0];
                            $subKvs = array($members[1] => $model->{$member});

                            $this->addContainerPos($container, $subType, $subKvs);
                        }
                    }

                    $container->add($type, $model->getPublic(array("_fields", "_isEncrypted")), false);
                }
            }
        }
    }
}