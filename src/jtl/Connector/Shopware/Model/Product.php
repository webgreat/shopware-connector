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
    /**
     * @var string
     */
    protected $_taxClassId = "0";

    public function __set($name, $value)
    {
        switch ($name) {
            case "_taxClassId":
                $this->$name = (string)$value;
                break;
        }

        parent::__set($name, $value);
    }

    protected $_fields = array(
        "_id" => "id",
        "_masterProductId" => "",
        "_manufacturerId" => "supplierId",
        "_deliveryStatusId" => "",
        "_unitId" => array("mainDetail", "unitId"),
        "_basePriceUnitId" => "",
        "_shippingClassId" => "",
        "_taxClassId" => array("tax", "id"),
        "_sku" => array("mainDetail", "number"),
        "_note" => "",
        "_stockLevel" => array("mainDetail", "inStock"),
        "_vat" => array("tax", "tax"),
        "_minimumOrderQuantity" => "",
        "_ean" => array("mainDetail", "ean"),
        "_isTopProduct" => "",
        "_productWeight" => array('mainDetail', 'weight'),
        "_shippingWeight" => "",
        "_isNew" => "",
        "_recommendedRetailPrice" => "",
        "_considerStock" => "",
        "_permitNegativeStock" => "",
        "_considerVariationStock" => "",
        "_isDivisible" => "",
        "_considerBasePrice" => "",
        "_basePriceDivisor" => "",
        "_keywords" => "keywords",
        "_sort" => "",
        "_created" => "added",
        "_availableFrom" => "availableFrom",
        "_manufacturerNumber" => "",
        "_serialNumber" => "",
        "_isbn" => "",
        "_asin" => "",
        "_unNumber" => "",
        "_hazardIdNumber" => "",
        "_taric" => "",
        "_isMasterProduct" => "",
        "_takeOffQuantity" => array("mainDetail", "purchaseSteps"),
        "_setArticleId" => "",
        "_upc" => "",
        "_originCountry" => "",
        "_epid" => "",
        "_productTypeId" => "",
        "_inflowQuantity" => "",
        "_inflowDate" => "",
        "_supplierStockLevel" => "",
        "_supplierDeliveryTime" => "",
        "_bestBefore" => ""
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