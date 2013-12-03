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
        "kArtikel" => "_id",
        "kVaterArtikel" => "_masterProductId",
        "kHersteller" => "_manufacturerId",
        "kLieferstatus" => "_deliveryStatusId",
        "kEinheit" => "_unitId",
        "kVPEEinheit" => "_basePriceUnitId",
        "kVersandklasse" => "_shippingClassId",
        "kSteuerklasse" => "_taxClassId",
        "cArtNr" => "_sku",
        "cAnmerkung" => "_note",
        "fLagerbestand" => "_stockLevel",
        "fMwSt" => "_vat",
        "fMindestbestellmenge" => "_minimumOrderQuantity",
        "cBarcode" => "_ean",
        "cTopArtikel" => "_isTopProduct",
        "fArtikelgewicht" => "_productWeight",
        "fGewicht" => "_shippingWeight",
        "cNeu" => "_isNew",
        "fUVP" => "_recommendedRetailPrice",
        "cLagerBeachten" => "_considerStock",
        "cLagerKleinerNull" => "_permitNegativeStock",
        "cLagerVariation" => "_considerVariationStock",
        "cTeilbar" => "_isDivisible",
        "cVPE" => "_considerBasePrice",
        "fVPEWert" => "_basePriceDivisor",
        "cSuchbegriffe" => "_keywords",
        "nSort" => "_sort",
        "dErstellt" => "_created",
        "dErscheinungsdatum" => "_availableFrom",
        "cHAN" => "_manufacturerNumber",
        "cSerie" => "_serialNumber",
        "cISBN" => "_isbn",
        "cASIN" => "_asin",
        "cUNNummer" => "_unNumber",
        "cGefahrnr" => "_hazardIdNumber",
        "cTaric" => "_taric",
        "nIstVater" => "_isMasterProduct",
        "fAbnahmeintervall" => "_takeOffQuantity",
        "kStueckliste" => "_setArticleId",
        "cUPC" => "_upc",
        "cHerkunftsland" => "_originCountry",
        "cEPID" => "_epid",
        "kWarengruppe" => "_productTypeId",
        "fZulauf" => "_inflowQuantity",
        "dZulaufDatum" => "_inflowDate",
        "fLieferantenlagerbestand" => "_supplierStockLevel",
        "fLieferzeit" => "_supplierDeliveryTime",
        "dMHD" => "_bestBefore"
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