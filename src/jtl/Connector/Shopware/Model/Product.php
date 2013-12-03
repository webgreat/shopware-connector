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
        "id" => "_id",
        "" => "_masterProductId",
        "supplierId" => "_manufacturerId",
        "kLieferstatus" => "_deliveryStatusId",
        array("mainDetail" => "unitId") => "_unitId",
        "kVPEEinheit" => "_basePriceUnitId",
        "kVersandklasse" => "_shippingClassId",
        array("tax" => "id") => "_taxClassId",
        array("mainDetail" => "number") => "_sku",
        "cAnmerkung" => "_note",
        array("mainDetail" => "inStock") => "_stockLevel",
        array("tax", "tax") => "_vat",
        "fMindestbestellmenge" => "_minimumOrderQuantity",
        array("mainDetail" => "ean") => "_ean",
        "cTopArtikel" => "_isTopProduct",
        array("mainDetail" => "weight") => "_productWeight",
        "" => "_shippingWeight",
        "" => "_isNew",
        array("mainDetail" => array("prices" => "")) => "_recommendedRetailPrice",
        "cLagerBeachten" => "_considerStock",
        "cLagerKleinerNull" => "_permitNegativeStock",
        "cLagerVariation" => "_considerVariationStock",
        "" => "_isDivisible",
        "" => "_considerBasePrice",
        "" => "_basePriceDivisor",
        "keywords" => "_keywords",
        "" => "_sort",
        "added" => "_created",
        "availableFrom" => "_availableFrom",
        "" => "_manufacturerNumber",
        "" => "_serialNumber",
        "" => "_isbn",
        "" => "_asin",
        "" => "_unNumber",
        "" => "_hazardIdNumber",
        "" => "_taric",
        "" => "_isMasterProduct",
        array("mainDetail", "purchaseSteps") => "_takeOffQuantity",
        "" => "_setArticleId",
        "" => "_upc",
        "" => "_originCountry",
        "" => "_epid",
        "" => "_productTypeId",
        "" => "_inflowQuantity",
        "" => "_inflowDate",
        "" => "_supplierStockLevel",
        "" => "_supplierDeliveryTime",
        "" => "_bestBefore"
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