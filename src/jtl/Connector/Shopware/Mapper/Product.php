<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Product as ProductModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Model\DataModel;
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use \jtl\Core\Logger\Logger;
use \jtl\Core\Utilities\Money;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \Doctrine\Common\Collections\ArrayCollection;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use \Shopware\Models\Article\Detail as DetailSW;
use \Shopware\Models\Article\Article as ArticleSW;

class Product extends DataMapper
{
    public function getRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\Models\Article\Article');
    }

    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Article', $id);
    }

    public function findDetail($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Detail', $id);
    }

    public function findDetails($productId, $offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'detail',
                'article',
                'tax',
                'categories',
                'detailprices',
                'links',
                'attribute',
                'downloads',
                'supplier',
                'related',
                'pricegroup',
                'discounts',
                'customergroups',
                'configuratorOptions',
                'propertygroup',
                'propertyoptions',
                'propertyvalues'
            )
            ->from('Shopware\Models\Article\Detail', 'detail')
            ->leftJoin('detail.article', 'article')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.categories', 'categories')
            ->leftJoin('detail.prices', 'detailprices')
            ->leftJoin('article.links', 'links')
            ->leftJoin('article.attribute', 'attribute')
            ->leftJoin('article.downloads', 'downloads')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('article.related', 'related')
            ->leftJoin('article.priceGroup', 'pricegroup')
            ->leftJoin('pricegroup.discounts', 'discounts')
            ->leftJoin('article.customerGroups', 'customergroups')
            ->leftJoin('detail.configuratorOptions', 'configuratorOptions')
            ->leftJoin('article.propertyGroup', 'propertygroup')
            ->leftJoin('propertygroup.options', 'propertyoptions')
            ->leftJoin('propertyoptions.values', 'propertyvalues')
            ->where('detail.articleId = :productId')
            ->andWhere('detail.kind = 2')
            ->setParameter('productId', $productId)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $products = iterator_to_array($paginator);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationUtil = new TranslationUtil;
            for ($i = 0; $i < count($products); $i++) {
                foreach ($shops as $shop) {
                    $translation = $translationUtil->read($shop['locale']['id'], 'article', $products[$i]['id']);
                    if (!empty($translation)) {
                        $translation['shopId'] = $shop['id'];
                        $products[$i]['translations'][$shop['locale']['locale']] = $translation;
                    }
                }
            }

            return $products;
        }
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'article',
                'tax',
                'categories',
                'details',
                'maindetail',
                'detailprices',
                'prices',
                'links',
                'attribute',
                'downloads',
                'supplier',
                'related',
                'pricegroup',
                'discounts',
                'customergroups',
                'propertygroup',
                'propertyoptions',
                'propertyvalues'
            )
            ->from('Shopware\Models\Article\Article', 'article')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.categories', 'categories')
            ->leftJoin('article.details', 'details')
            ->leftJoin('article.mainDetail', 'maindetail')
            ->leftJoin('details.prices', 'detailprices')
            ->leftJoin('maindetail.prices', 'prices')
            ->leftJoin('article.links', 'links')
            ->leftJoin('article.attribute', 'attribute')
            ->leftJoin('article.downloads', 'downloads')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('article.related', 'related')
            ->leftJoin('article.priceGroup', 'pricegroup')
            ->leftJoin('pricegroup.discounts', 'discounts')
            ->leftJoin('article.customerGroups', 'customergroups')
            ->leftJoin('article.propertyGroup', 'propertygroup')
            ->leftJoin('propertygroup.options', 'propertyoptions')
            ->leftJoin('propertyoptions.values', 'propertyvalues')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $products = iterator_to_array($paginator);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationUtil = new TranslationUtil();
            for ($i = 0; $i < count($products); $i++) {
                foreach ($shops as $shop) {
                    $translation = $translationUtil->read($shop['locale']['id'], 'article', $products[$i]['id']);
                    if (!empty($translation)) {
                        $translation['shopId'] = $shop['id'];
                        $products[$i]['translations'][$shop['locale']['locale']] = $translation;
                    }
                }
            }

            return $products;
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function save(DataModel $product)
    {
        $productSW = null;
        $detailSW = null;
        $result = new ProductModel;

        if ($product->getAction() == DataModel::ACTION_DELETE) { // DELETE
            $this->deleteProductData($product, $productSW);

            return $result;
        } else { // UPDATE or INSERT
            if ($this->isChild($product)) {
                $this->prepareChildAssociatedData($product, $productSW, $detailSW);
                $this->prepareDetailAssociatedData($product, $productSW, $detailSW, true);
                $this->prepareAttributeAssociatedData($productSW, $detailSW);
                $this->preparePriceAssociatedData($product, $productSW, $detailSW);

                $this->Manager()->persist($detailSW);
                $this->Manager()->flush();

                $this->saveChildRelation($detailSW);
            } else {
                $this->prepareProductAssociatedData($product, $productSW, $detailSW);
                $this->prepareCategoryAssociatedData($product, $productSW);
                $this->prepareInvisibilityAssociatedData($product, $productSW);
                $this->prepareTaxAssociatedData($product, $productSW);
                $this->prepareManufacturerAssociatedData($product, $productSW);
                $this->prepareSpecialPriceAssociatedData($product, $productSW);
                $this->prepareDetailAssociatedData($product, $productSW, $detailSW);
                $this->prepareAttributeAssociatedData($productSW, $detailSW);
                $this->prepareVariationAssociatedData($product, $productSW, $detailSW, $result);
                $this->preparePriceAssociatedData($product, $productSW, $detailSW);

                $productSW->setMainDetail($detailSW);

                $violations = $this->Manager()->validate($productSW);
                if ($violations->count() > 0) {
                    throw new ApiException\ValidationException($violations);
                }

                // Save Product
                $this->Manager()->persist($productSW);
                $this->Manager()->flush();

                $this->deleteTranslationData($productSW);
                $this->saveTranslationData($product, $productSW);
            }
        }

        /*
        $confiSet = $productSW->getConfiguratorSet();
        if ($confiSet) {
            foreach ($confiSet->getGroups() as $group) {
                foreach ($result->getVariations() as &$variation) {
                    foreach ($variation->getI18ns() as $variationI18n) {
                        if ($variationI18n->getName() == $group->getName()) {
                            $variation->getId()->setEndpoint(sprintf('%_%', $productSW->getId(), $group->getId()));

                            break;
                        }
                    }
                }
            }

            foreach ($confiSet->getOptions() as $option) {
                foreach ($result->getVariations() as &$variation) {
                    foreach ($variation->getValues() as &$variationValue) {
                        foreach ($variationValue->getI18ns() as &$variationValueI81n) {
                            
                        }
                    }
                }
            }
        }
        */
       
        // Result
        $result->setId(new Identity($productSW->getId(), $product->getId()->getHost()));

        return $result;
    }

    protected function prepareChildAssociatedData(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW)
    {
        $productId = (strlen($product->getId()->getEndpoint()) > 0) ? $product->getId()->getEndpoint() : null;
        $masterProductId = (strlen($product->getMasterProductId()->getEndpoint()) > 0) ? $product->getMasterProductId()->getEndpoint() : null;

        if ($masterProductId === null) {
            throw new \Exception('Master product id is empty');
        }

        $productSW = $this->find($masterProductId);
        if ($productSW === null) {
            throw new \Exception(sprintf('Cannot find parent product with id (%s)', $masterProductId));
        }

        if ($productId !== null && $productId > 0) {
            list($detailId, $id) = explode('_', $productId);
            $detailSW = $this->findDetail((int)$detailId);
        }
    }

    protected function prepareProductAssociatedData(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW)
    {
        $productId = (strlen($product->getId()->getEndpoint()) > 0) ? $product->getId()->getEndpoint() : null;

        if ($productId !== null && $productId > 0) {
            if ($this->isChild($product)) {
                list($detailId, $id) = explode('_', $productId);
                $detailSW = $this->findDetail((int)$detailId);

                if ($detailSW === null) {
                    throw new \Exception(sprintf('Child product with id (%s) not found', $productId));
                }

                $productId = (int)$id;
            }

            $productSW = $this->find($productId);
            if ($productSW && $detailSW === null) {
                $detailSW = $productSW->getMainDetail();
            }
        } elseif (strlen($product->getSku()) > 0) {
            $detailSW = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(array('number' => $product->getSku()));
            if ($detailSW) {
                $productSW = $detailSW->getArticle();
            }
        }

        if ($productSW === null) {
            $productSW = new ArticleSW;
        }

        $productSW->setAdded($product->getCreated())
            ->setAvailableFrom($product->getAvailableFrom())
            ->setHighlight(intval($product->getIsTopProduct()))
            ->setActive(true);

        // I18n
        foreach ($product->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $productSW->setName($i18n->getName())
                    ->setDescription($i18n->getShortDescription())
                    ->setDescriptionLong($i18n->getDescription())
                    ->setKeywords($i18n->getMetaKeywords())
                    ->setMetaTitle($i18n->getTitleTag());
            }
        }
    }

    protected function prepareCategoryAssociatedData(&$product, &$productSW)
    {
        $collection = new ArrayCollection;
        $categoryMapper = Mmc::getMapper('Category');
        foreach ($product->getCategories() as $category) {
            if (strlen($category->getCategoryId()->getEndpoint()) > 0) {
                $categorySW = $categoryMapper->find(intval($category->getCategoryId()->getEndpoint()));
                if ($categorySW) {
                    $collection->add($categorySW);
                }
            }
        }

        $productSW->setCategories($collection);
    }

    protected function prepareInvisibilityAssociatedData(DataModel &$product, ArticleSW &$productSW)
    {
        // Invisibility
        $collection = new ArrayCollection;
        foreach ($product->getInvisibilities() as $invisibility) {
            $customerGroupSW = CustomerGroupUtil::get(intval($invisibility->getCustomerGroupId()->getEndpoint()));
            if ($customerGroupSW) {
                $collection->add($customerGroupSW);
            }
        }

        $productSW->setCustomerGroups($collection);
    }

    protected function prepareTaxAssociatedData(DataModel &$product, ArticleSW &$productSW)
    {
        // Tax
        $taxSW = Shopware()->Models()->getRepository('Shopware\Models\Tax\Tax')->findOneBy(array('tax' => $product->getVat()));
        if ($taxSW) {
            $productSW->setTax($taxSW);
        } else {
            throw new ApiException\ValidationException(sprintf('Could not find any Tax entity for value (%s)', $product->getVat()));
        }
    }

    protected function prepareManufacturerAssociatedData(DataModel &$product, ArticleSW &$productSW)
    {
        // Manufacturer
        $manufacturerMapper = Mmc::getMapper('Manufacturer');
        $manufacturerSW = $manufacturerMapper->find(intval($product->getManufacturerId()->getEndpoint()));
        if ($manufacturerSW) {
            $productSW->setSupplier($manufacturerSW);
        }
    }

    protected function prepareSpecialPriceAssociatedData(DataModel &$product, ArticleSW &$productSW) 
    {
        // ProductSpecialPrice
        foreach ($product->getSpecialPrices() as $i => $productSpecialPrice) {
            $collection = new ArrayCollection;
            $priceGroupSW = Shopware()->Models()->getRepository('Shopware\Models\Price\Group')->find(intval($productSpecialPrice->getId()->getEndpoint()));
            if ($priceGroupSW === null) {
                $priceGroupSW = new \Shopware\Models\Price\Group;
            }

            // SpecialPrice
            foreach ($productSpecialPrice->getSpecialPrices() as $specialPrice) {
                $customerGroupSW = CustomerGroupUtil::get(intval($specialPrice->getCustomerGroupId()->getEndpoint()));

                $price = null;
                $priceCount = count($product->getPrices());
                if ($priceCount == 1) {
                    $price = reset($product->getPrices());
                } elseif ($priceCount > 1) {
                    foreach ($product->getPrices() as $productPrice) {
                        if ($customerGroupSW->getId() == intval($productPrice->getCustomerGroupId()->getEndpoint())) {
                            $price = $productPrice->getNetPrice();

                            break;
                        }
                    }
                }

                if ($price === null) {
                    Logger::write(sprintf('Could not find any price for customer group (%s)', $specialPrice->getCustomerGroupId()->getEndpoint()), Logger::WARNING, 'database');

                    continue;
                }
                
                $priceDiscountSW = Shopware()->Models()->getRepository('Shopware\Models\Price\Discount')->findOneBy(array('groupId' => $specialPrice->getProductSpecialPriceId()->getEndpoint()));
                if ($priceDiscountSW === null) {
                    $priceDiscountSW = new \Shopware\Models\Price\Discount;
                }

                $discountValue = 100 - (($specialPrice->getPriceNet() / $price) * 100);

                $priceDiscountSW->setCustomerGroup($customerGroupSW)
                    ->setDiscount($discountValue)
                    ->setStart(1);

                $collection->add($priceDiscountSW);
            }

            $priceGroupSW->setName("Standard_{$i}")
                ->setDiscounts($collection);

            $productSW->setPriceGroup($priceGroupSW)
                ->setPriceGroupActive(1);
        }
    }

    protected function prepareDetailAssociatedData(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW, $isChild = false)
    {
        // Detail
        if ($detailSW === null) {
            $detailSW = new DetailSW;
        }

        foreach ($product->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $detailSW->setAdditionalText($i18n->getName());
            }
        }

        $kind = $isChild ? 2 : 1;
        $detailSW->setNumber($product->getSku())
            ->setSupplierNumber($product->getManufacturerNumber())
            ->setActive(1)
            ->setKind($kind)
            ->setStockMin(0)
            ->setInStock($product->getStockLevel())
            ->setMinPurchase($product->getMinimumOrderQuantity())
            ->setEan($product->getEan());

        $detailSW->setWeight($product->getProductWeight())
            ->setPurchaseSteps($product->getTakeOffQuantity())
            ->setArticle($productSW);
    }

    protected function prepareAttributeAssociatedData(ArticleSW &$productSW, DetailSW &$detailSW)
    {
        // Attribute
        // @todo: waiting for connector attribute entity
        $attributeSW = $productSW->getAttribute();
        if ($attributeSW) {
            // @todo: set values
        } else {
            $attributeSW = new \Shopware\Models\Attribute\Article;
            $attributeSW->setArticle($productSW)
                ->setArticleDetail($detailSW);

            // @todo: set values
        }

        $detailSW->setAttribute($attributeSW);
        $productSW->setAttribute($attributeSW);
    }

    protected function prepareVariationAssociatedData(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW, ProductModel &$result)
    {
        // Variations
        $confiSet = null;
        if (count($product->getVariations()) > 0) {
            $confiSet = $productSW->getConfiguratorSet();

            $groups = new ArrayCollection;
            $options = new ArrayCollection;

            if (!$confiSet) {
                $confiSet = new \Shopware\Models\Article\Configurator\Set;
                $confiSet->setName('Set-' . $detailSW->getNumber());
            }

            $groupMapper = Mmc::getMapper('ConfiguratorGroup');
            $optionMapper = Mmc::getMapper('ConfiguratorOption');

            foreach ($product->getVariations() as $variation) {
                $groupId = 0;
                $group = null;
                if (strlen($variation->getId()->getEndpoint()) > 0) {
                    list($productId, $groupId) = explode('_', $variation->getId()->getEndpoint());
                    $groupId = intval($groupId);
                }

                if ($groupId > 0) {
                    $group = $groupMapper->find($groupId);
                }

                if (!$group) {
                    $group = new \Shopware\Models\Article\Configurator\Group;
                }

                foreach ($variation->getI18ns() as $variationI18n) {
                    if ($variationI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                        $group->setName($variationI18n->getName());
                    }
                }

                $variationResult = Mmc::getModel('ProductVariation');
                $variationResult->setId(new Identity('', $variation->getId()->getHost()));

                // VariationValue
                foreach ($variation->getValues() as $variationValue) {
                    $optionId = 0;
                    $option = null;

                    if (strlen($variationValue->getId()->getEndpoint()) > 0) {
                        list($productId, $groupId, $optionId) = explode('_', $variationValue->getId()->getEndpoint());
                        $optionId = intval($optionId);
                    }

                    if ($optionId > 0) {
                        $option = $optionMapper->find($optionId);
                    }

                    if (!$option) {
                        $option = new \Shopware\Models\Article\Configurator\Option;
                    }

                    foreach ($variationValue->getI18ns() as $variationValueI18n) {
                        if ($variationValueI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                            $option->setName($variationValueI18n->getName());
                        }
                    }

                    $option->setGroup($group);
                    $options->add($option);

                    $variationValueResult = Mmc::getModel('ProductVariationValue');
                    $variationValueResult->setId(new Identity('', $variationValue->getId()->getHost()));

                    $variationResult->addValue($variationValueResult);
                }

                $groups->add($group);

                $result->addVariation($variationResult);
            }

            $confiSet->setOptions($options)
                ->setGroups($groups);
        }

        $productSW->setConfiguratorSet($confiSet);
    }

    protected function preparePriceAssociatedData(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW)
    {
        // Price
        $collection = new ArrayCollection;
        foreach ($product->getPrices() as $price) {
            $priceSW = null;
            $customerGroupSW = CustomerGroupUtil::get(intval($price->getCustomerGroupId()->getEndpoint()));
            if (strlen($price->getProductId()->getEndpoint()) > 0) {
                $priceSW = Shopware()->Models()->getRepository('Shopware\Models\Article\Price')->findOneBy(array('articleId' => intval($price->getProductId()->getEndpoint())));
            }

            if ($priceSW === null) {
                $priceSW = new \Shopware\Models\Article\Price;
            }

            $priceSW->setArticle($productSW)
                ->setCustomerGroup($customerGroupSW)
                ->setFrom($price->getQuantity())
                ->setPrice($price->getNetPrice())
                ->setDetail($detailSW);

            $this->Manager()->persist($priceSW);

            $collection->add($priceSW);
        }

        $detailSW->setPrices($collection);
    }

    protected function saveTranslationData(DataModel &$product, ArticleSW &$productSW)
    {
        // ProductI18n
        $translationUtil = new TranslationUtil;
        foreach ($product->getI18ns() as $i18n) {
            $locale = LocaleUtil::getByKey($i18n->getLocaleName());
            if ($locale && $i18n->getLocaleName() != Shopware()->Shop()->getLocale()->getLocale()) {
                $translationUtil->write(
                    $locale->getId(),
                    'article',
                    $productSW->getId(),
                    array(
                        'name' => $i18n->getName(),
                        'descriptionLong' => $i18n->getDescription(),
                        'metaTitle' => $i18n->getTitleTag(),
                        'description' => $i18n->getShortDescription(),
                        'keywords' => $i18n->getMetaKeywords(),
                        'packUnit' => '',
                        'attr1' => '',
                        'attr2' => '',
                        'attr3' => ''
                    )
                );
            } else {
                Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLocaleName()), Logger::WARNING, 'database');
            }
        }
    }

    protected function saveChildRelation(DetailSW &$detailSW)
    {
        $sql = "DELETE FROM s_article_configurator_option_relations WHERE article_id = ? AND option_id = ?";
        Shopware()->Db()->query($sql, array($detailSW->getArticle()->getId(), $detailSW->getId()));

        $sql = "INSERT INTO s_article_configurator_option_relations (id, article_id, option_id) VALUES (NULL, ?, ?)";
        return Shopware()->Db()->query($sql, array($detailSW->getArticle()->getId(), $detailSW->getId()));
    }

    protected function deleteTranslationData(ArticleSW &$productSW)
    {
        $translationUtil = new TranslationUtil;
        $translationUtil->delete('article', $productSW->getId());
    }

    protected function deleteProductData(DataModel &$product, ArticleSW &$productSW)
    {
        $productId = (strlen($product->getId()->getEndpoint()) > 0) ? $product->getId()->getEndpoint() : null;

        if ($productId !== null && $productId > 0) {
            if ($this->isChild($product)) {
                list($detailId, $id) = explode('_', $productId);
                $detailSW = $this->findDetail((int)$detailId);
                if ($detailSW === null) {
                    throw new \Exception(sprintf('Child product with id (%s) not found', $productId));
                }

                $this->removeArticleDetail((int)$detailId);
            } else {
                $productSW = $this->find((int)$productId);
                if ($productSW !== null) {
                    $this->removePrices($productSW->getId());
                    $this->removeArticleEsd($productSW->getId());
                    $this->removeAttributes($productSW->getId());
                    $this->removeArticleDetails($productSW);

                    $this->deleteTranslationData($productSW);

                    $this->Manager()->remove($productSW);
                    $this->Manager()->flush();
                }
            }
        }
    }

    protected function removePrices($productId)
    {
        $priceSW = $this->Manager()->getRepository('Shopware\Models\Article\Price')->findOneBy(array('articleId', (int)$productId));
        if ($priceSW !== null) {
            $this->Manager()->remove($priceSW);
            $this->Manager()->flush();
        }
    }

    protected function removeAttributes($productId)
    {
        $attrSW = $this->Manager()->getRepository('Shopware\Models\Attribute\Article')->findOneBy(array('articleId', (int)$productId));
        if ($attrSW !== null) {
            $this->Manager()->remove($attrSW);
            $this->Manager()->flush();
        }
    }

    protected function removeArticleEsd($productId)
    {
        $attrSW = $this->Manager()->getRepository('Shopware\Models\Attribute\Esd')->findOneBy(array('articleId', (int)$productId));
        if ($attrSW !== null) {
            $this->Manager()->remove($attrSW);
            $this->Manager()->flush();
        }
    }

    protected function removeArticleDetails($productSW)
    {
        $sql = "SELECT id FROM s_articles_details WHERE articleID = ? AND kind != 1";
        $detailSWs = Shopware()->Db()->fetchAll($sql, array($productSW->getId()));

        foreach ($detailSWs as $detailSW) {
            $this->removeArticleDetail($detailSW['id']);
        }
    }

    protected function removeArticleDetail($detailId)
    {
        // Price
        $this->Manager()->createQueryBuilder()
            ->delete('Shopware\Models\Article\Price', 'price')
            ->where('price.articleDetailsId = :id')
            ->setParameter('id', $detailId)
            ->getQuery()
            ->execute();

        // Image
        $query = $this->getRepository()->getRemoveImageQuery($detailId);
        $query->execute();
        
        // Option relations
        $sql = "DELETE FROM s_article_configurator_option_relations WHERE article_id = ?";
        Shopware()->Db()->query($sql, array($detailId));

        // Detail
        $detailSW = $this->findDetail((int)$detailId);
        $this->Manager()->remove($detailSW);
        $this->Manager()->flush();
    }

    protected function isChild(DataModel &$product)
    {
        return (strlen($product->getId()->getEndpoint()) > 0 && strpos($product->getId()->getEndpoint(), '_') !== false);
    }

    /*
    protected function savePrice(DataModel &$product, ArticleSW &$productSW, DetailSW &$detailSW)
    {
        foreach ($detailSW->getPrices() as $priceSW) {
            $this->Manager()->remove($priceSW);
        }

        // Price
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        foreach ($product->getPrices() as $price) {
            try {
                $customerGroupSW = CustomerGroupUtil::get(intval($price->getCustomerGroupId()->getEndpoint()));

                $priceSW = new \Shopware\Models\Article\Price;
                $priceSW->setArticle($productSW)
                    ->setCustomerGroup($customerGroupSW)
                    ->setFrom($price->getQuantity())
                    ->setPrice($price->getNetPrice())
                    ->setDetail($detailSW);

                $violations = $this->Manager()->validate($priceSW);
                if ($violations->count() > 0) {
                    throw new ApiException\ValidationException($violations);
                }

                $this->Manager()->persist($priceSW);
            } catch (ApiException\ValidationException $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'database');
            }
        }
    }

    protected function saveCategory(DataModel &$product, ArticleSW &$productSW, ProductModel &$result)
    {
        // Category
        try {
            $categoryMapper = Mmc::getMapper('Category');
            Shopware()->Db()->query('DELETE FROM s_articles_categories
                                        WHERE articleID = ?', array($productSW->getId()));

            Shopware()->Db()->query('DELETE FROM s_articles_categories_ro
                                        WHERE articleID = ?', array($productSW->getId()));

            foreach ($product->getCategories() as $category) {
                $categorySW = $categoryMapper->find(intval($category->getCategoryId()->getEndpoint()));
                if ($categorySW) {
                    $stmt = Shopware()->Db()->query('INSERT INTO s_articles_categories VALUES (NULL, ?, ?)', array($productSW->getId(), $categorySW->getId()));
                    $categoryId = $stmt->getAdapter()->lastInsertId();

                    Shopware()->Db()->query('INSERT INTO s_articles_categories_ro VALUES (NULL, ?, ?, ?)', array($productSW->getId(), $categorySW->getId(), $categorySW->getParentId()));

                    $product2Category = Mmc::getModel('Product2Category');

                    $product2Category->setId(new Identity($categoryId, $category->getId()->getHost()))
                        ->setCategoryId(new Identity($categorySW->getId(), $category->getId()->getHost()))
                        ->setProductId(new Identity($productSW->getId(), $product->getId()->getHost()));

                    $result->addCategory($product2Category);
                }
            }
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'database');
        }
    }
    */
}