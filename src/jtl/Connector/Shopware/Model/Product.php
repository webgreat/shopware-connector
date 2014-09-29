<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Product as ProductModel;

/**
 * Product Model
 * @access public
 */
class Product extends ProductModel
{
    protected $fields = array(
        'id' => 'id',
        'masterProductId' => 'masterProductId',
        'manufacturerId' => 'supplierId',
        'deliveryStatusId' => '',
        //'unitId' => array('mainDetail', 'unitId'),
        'basePriceUnitId' => '',
        'shippingClassId' => '',
        //'taxClassId' => array('tax', 'id'),
        'sku' => array('mainDetail', 'number'),
        'note' => '',
        'stockLevel' => array('mainDetail', 'inStock'),
        'vat' => array('tax', 'tax'),
        'minimumOrderQuantity' => array('mainDetail', 'minPurchase'),
        'ean' => array('mainDetail', 'ean'),
        'isTopProduct' => 'highlight',
        'productWeight' => array('mainDetail', 'weight'),
        'shippingWeight' => '',
        'isNew' => '',
        'recommendedRetailPrice' => '',
        'considerStock' => '',
        'permitNegativeStock' => '',
        'considerVariationStock' => '',
        'isDivisible' => '',
        'considerBasePrice' => '',
        'basePriceDivisor' => '',
        //'keywords' => 'keywords',
        'sort' => '',
        'created' => 'added',
        'availableFrom' => 'availableFrom',
        'manufacturerNumber' => array('mainDetail', 'supplierNumber'),
        'serialNumber' => '',
        'isbn' => '',
        'asin' => '',
        'unNumber' => '',
        'hazardIdNumber' => '',
        'taric' => '',
        'isMasterProduct' => '',
        'takeOffQuantity' => array('mainDetail', 'purchaseSteps'),
        'setArticleId' => '',
        'upc' => '',
        'originCountry' => '',
        'epid' => '',
        'productTypeId' => '',
        'inflowQuantity' => '',
        'inflowDate' => '',
        'supplierStockLevel' => '',
        'supplierDeliveryTime' => '',
        'bestBefore' => '',
        'measurementUnitId' => '',
        'measurementQuantity' => '',
        'basePriceQuantity' => '',
        'length' => '',
        'height' => '',
        'width' => '',
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}