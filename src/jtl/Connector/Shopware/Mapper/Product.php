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
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Core\Logger\Logger;
use \jtl\Core\Utilities\Money;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \Doctrine\Common\Collections\ArrayCollection;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;

class Product extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Article', $id);
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
                'configuratorOptions'
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
            ->where('detail.articleId = :productId')
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

            $translationReader = new \Shopware_Components_Translation;
            for ($i = 0; $i < count($products); $i++) {
                foreach ($shops as $shop) {
                    $translation = $translationReader->read($shop['locale']['id'], 'article', $products[$i]['id']);
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
                'customergroups'
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

            $translationReader = new \Shopware_Components_Translation();
            for ($i = 0; $i < count($products); $i++) {
                foreach ($shops as $shop) {
                    $translation = $translationReader->read($shop['locale']['id'], 'article', $products[$i]['id']);
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

        $this->prepareProductAssociatedData($product, $productSW, $detailSW);
        $this->prepareCategoryAssociatedData($product, $productSW);
        $this->prepareInvisibilityAssociatedData($product, $productSW);
        $this->prepareTaxAssociatedData($product, $productSW);
        $this->prepareManufacturerAssociatedData($product, $productSW);
        $this->prepareSpecialPriceAssociatedData($product, $productSW);
        $this->prepareDetailAssociatedData($product, $detailSW);
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

        $this->saveTranslationData($product, $productSW);

        // Result
        $result->setId(new Identity($productSW->getId(), $product->getId()->getHost()));

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

        return $result;
    }

    protected function prepareProductAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW, \Shopware\Models\Article\Detail &$detailSW)
    {
        $productId = (strlen($product->getId()->getEndpoint()) > 0) ? (int)$product->getId()->getEndpoint() : null;

        if ($productId !== null && $productId > 0) {
            $productSW = $this->find($productId);
            if ($productSW) {
                $detailSW = $productSW->getMainDetail();
            }
        } elseif (strlen($product->getSku()) > 0) {
            $detailSW = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(array('number' => $product->getSku()));
            if ($detailSW) {
                $productSW = $detailSW->getArticle();
            }
        }

        if ($productSW === null) {
            $productSW = new \Shopware\Models\Article\Article;
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

    protected function prepareInvisibilityAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW)
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

    protected function prepareTaxAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW)
    {
        // Tax
        $taxSW = Shopware()->Models()->getRepository('Shopware\Models\Tax\Tax')->findOneBy(array('tax' => $product->getVat()));
        if ($taxSW) {
            $productSW->setTax($taxSW);
        } else {
            throw new ApiException\ValidationException(sprintf('Could not find any Tax entity for value (%s)', $product->getVat()));
        }
    }

    protected function prepareManufacturerAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW)
    {
        // Manufacturer
        $manufacturerMapper = Mmc::getMapper('Manufacturer');
        $manufacturerSW = $manufacturerMapper->find(intval($product->getManufacturerId()->getEndpoint()));
        if ($manufacturerSW) {
            $productSW->setSupplier($manufacturerSW);
        }
    }

    protected function prepareSpecialPriceAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW) 
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

    protected function prepareDetailAssociatedData(DataModel &$product, \Shopware\Models\Article\Detail &$detailSW)
    {
        // Detail
        if ($detailSW === null) {
            $detailSW = new \Shopware\Models\Article\Detail;
        }

        $detailSW->setNumber($product->getSku())
            ->setSupplierNumber($product->getManufacturerNumber())
            ->setActive(1)
            ->setKind(1)
            ->setStockMin(0)
            ->setInStock($product->getStockLevel())
            ->setMinPurchase($product->getMinimumOrderQuantity())
            ->setEan($product->getEan());

        $detailSW->setWeight($product->getProductWeight())
            ->setPurchaseSteps($product->getTakeOffQuantity());
    }

    protected function prepareAttributeAssociatedData(\Shopware\Models\Article\Article &$productSW, \Shopware\Models\Article\Detail &$detailSW)
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

    protected function prepareVariationAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW, \Shopware\Models\Article\Detail &$detailSW, ProductModel &$result)
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

    protected function preparePriceAssociatedData(DataModel &$product, \Shopware\Models\Article\Article &$productSW, \Shopware\Models\Article\Detail &$detailSW)
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

    protected function saveTranslationData(DataModel &$product, \Shopware\Models\Article\Article &$productSW)
    {
        // ProductI18n
        $translation = new \Shopware_Components_Translation;
        foreach ($product->getI18ns() as $i18n) {
            $locale = LocaleUtil::getByKey($i18n->getLocaleName());
            if ($locale && $i18n->getLocaleName() != Shopware()->Shop()->getLocale()->getLocale()) {
                $translation->write(
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

    /*
    protected function savePrice(DataModel &$product, \Shopware\Models\Article\Article &$productSW, \Shopware\Models\Article\Detail &$detailSW)
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

    protected function saveCategory(DataModel &$product, \Shopware\Models\Article\Article &$productSW, ProductModel &$result)
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