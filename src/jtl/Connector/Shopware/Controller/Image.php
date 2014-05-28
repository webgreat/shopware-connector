<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Core\Result\Transaction as TransactionResult;
use \jtl\Connector\Transaction\Handler as TransactionHandler;
use \jtl\Core\Exception\TransactionException;
use \jtl\Connector\Result\Action;
use \jtl\Core\Rpc\Error;
use \jtl\Core\Exception\DatabaseException;
use \jtl\Connector\Drawing\ImageRelationType;
use \jtl\Core\Model\QueryFilter;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Connector\Formatter\ExceptionFormatter;

/**
 * Image Controller
 * @access public
 */
class Image extends DataController
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

            $mapper = Mmc::getMapper('Image');

            $modelContainer = array();
            if ($filter->getFilter('relationType') !== null) {                
                $modelContainer[$filter->getFilter('relationType')] = $mapper->findAll($offset, $limit, false, $filter->getFilter('relationType'));
            }
            else {
                // Get all images
                $relationTypes = array(
                    ImageRelationType::TYPE_PRODUCT,
                    ImageRelationType::TYPE_CATEGORY,
                    ImageRelationType::TYPE_MANUFACTURER,
                    ImageRelationType::TYPE_PRODUCT_VARIATION_VALUE
                );

                foreach ($relationTypes as $relationType) {
                    $modelContainer[$relationType] = $mapper->findAll($offset, $limit, false, $relationType);
                }
            }

            foreach ($modelContainer as $relationType => $models) {
                foreach ($models as $modelSW) {
                    switch ($relationType) {
                        case ImageRelationType::TYPE_PRODUCT:
                            if (!isset($modelSW['images'])) continue;

                            foreach ($modelSW['images'] as $imageSW) {
                                $model = Mmc::getModel('Image');

                                $model->_id = $imageSW['id'];

                                if (intval($modelSW['parentId']) > 0) {
                                    $model->_masterImageId = $modelSW['parentId'];
                                }

                                $model->_relationType = $relationType;
                                $model->_foreignKey = $imageSW['articleId'];
                                $model->_filename = sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $imageSW['media']['path']);

                                $result[] = $model->getPublic();
                            }
                            break;
                        case ImageRelationType::TYPE_CATEGORY:
                            $model = Mmc::getModel('Image');
                            
                            $model->_id = $modelSW['media']['id'];

                            if (intval($modelSW['parentId']) > 0) {
                                $model->_masterImageId = $modelSW['parentId'];
                            }

                            $model->_relationType = $relationType;
                            $model->_foreignKey = $modelSW['id'];
                            $model->_filename = sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $modelSW['media']['path']);

                            $result[] = $model->getPublic();
                            break;
                        case ImageRelationType::TYPE_MANUFACTURER:
                            if (!isset($modelSW['image']) || strlen(trim($modelSW['image'])) == 0) continue;

                            $model = Mmc::getModel('Image');

                            $model->_id = $modelSW['id'];

                            if (intval($modelSW['parentId']) > 0) {
                                $model->_masterImageId = $modelSW['parentId'];
                            }

                            $model->_relationType = $relationType;
                            $model->_foreignKey = $modelSW['id'];
                            $model->_filename = sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $modelSW['image']);

                            $result[] = $model->getPublic();
                            break;
                        case ImageRelationType::TYPE_PRODUCT_VARIATION_VALUE:
                            $model = Mmc::getModel('Image');

                            // Work Around
                            // id = s_article_img_mapping_rules.id
                            $model->_id = 'option_' . $modelSW['id'];

                            if (intval($modelSW['masterImageId']) > 0) {
                                $model->_masterImageId = $modelSW['masterImageId'];
                            }

                            $model->_relationType = $relationType;
                            $model->_foreignKey = $modelSW['articleID'] . '_' . $modelSW['group_id'] . '_' . $modelSW['foreignKey'];
                            $model->_filename = sprintf('http://%s%s/%s%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), 'media/image/', $modelSW['path'] . '.' . $modelSW['extension']);

                            $result[] = $model->getPublic();
                            break;
                    }
                }
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
            $filter = new QueryFilter();
            $filter->set($params);
            
            $mapper = Mmc::getMapper('Image');

            $statModel = new Statistic();
            $statModel->_available = 0;
            $statModel->_pending = 0;
            $statModel->_controllerName = 'image';

            if ($filter->getFilter('relationType') !== null) {  

                $statModel->_available = $mapper->fetchCount(null, null, $filter->getFilter('relationType'));
            }
            else {
                // Get all images
                $relationTypes = array(
                    ImageRelationType::TYPE_PRODUCT,
                    ImageRelationType::TYPE_CATEGORY,
                    ImageRelationType::TYPE_MANUFACTURER
                );

                foreach ($relationTypes as $relationType) {
                    $statModel->_available += $mapper->fetchCount(null, null, $relationType);
                }
            }

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
            $result = $this->insert($container);

            if ($result !== null) {
                $action->setResult($result->getPublic());
            }
        }
        catch (\Exception $exc) {
            $message = (strlen($exc->getMessage()) > 0) ? $exc->getMessage() : ExceptionFormatter::format($exc);

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($message);
            $action->setError($err);
        }

        return $action;
    }
}