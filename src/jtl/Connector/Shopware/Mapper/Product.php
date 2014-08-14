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

        return array();
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    protected function prepareProduct(ProductContainer $container)
    {
        $product = $container->getMainModel();

        $data = DataConverter::toArray(DataModel::map(false, null, $product));

        if (intval($data['id']) == 0) {
            $data['active'] = 1;
        }

        // ProductI18n
        foreach ($container->getProductI18ns() as $productI18n) {

            // Main language
            if ($productI18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $productI18n)));
            }
        }

        // ProductPrice
        $data['mainDetail']['prices'] = array();
        foreach ($container->getProductPrices() as $productPrice) {
            $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $product)));
            $priceSW = DataConverter::toArray(DataModel::map(false, null, $productPrice));
            $priceSW['price'] = Money::AsGross($priceSW['price'], $data['tax']['tax']);

            $data['mainDetail']['prices'][] = $priceSW;
        }

        // ProductSpecialPrice
        foreach ($container->getProductSpecialPrices() as $productSpecialPrice) {
            $data['priceGroupActive'] = true;
            $data['priceGroupId'] = $productSpecialPrice->getId()->getEndpoint();
        }

        // Product2Categories
        foreach ($container->getProduct2Categories() as $product2Category) {
            $data['categories'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        $productSW = null;
        if (empty($product->getId()->getEndpoint())) {
            $productSW = $this->save($data);
        }

        return array(
            'productSW' => $productSW,
            'data' => $data
        );
    }

    public function prepareData(ProductContainer $container)
    {
        Logger::write(print_r($container, 1), Logger::DEBUG, 'database');

        $product = $container->getMainModel();
        
        $result = $this->prepareProduct($container);
        $data = $result['data'];
        $productSW = $result['productSW'];
        $productId = ($productSW !== null) ? $productSW->getId() : $product->getId()->getEndpoint();

        // Attributes
        foreach ($container->getProductAttrs() as $productAttr) {
            //$data['attribute'][] = DataConverter::toArray(DataModel::map(false, null, $product2Category));
        }

        // ProductInvisibility

        // ProductVariation
        $configuratorGroupMapper = Mmc::getMapper('ConfiguratorGroup');
        $configuratorGroupMapper->prepareData($container, $productId, $data);

        return $data;
    }

    public function save(DataModel $product)
    {
        $productSW = null;
        $detailSW = null;
        $result = new ProductModel;

        $id = (strlen($product->getId()->getEndpoint()) > 0) ? (int)$product->getId()->getEndpoint() : null;

        if ($id !== null && $id > 0) {
            $productSW = $this->find($id);
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
        $exists = false;
        foreach ($product->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $productSW->setName($i18n->getName())
                    ->setDescription($i18n->getShortDescription())
                    ->setDescriptionLong($i18n->getDescription())
                    ->setKeywords($i18n->getMetaKeywords())
                    ->setMetaTitle($i18n->getTitleTag());
            }
        }

        // Invisibility
        $collection = new \Doctrine\Common\Collections\ArrayCollection;
        foreach ($product->getInvisibilities() as $invisibility) {
            $customerGroupSW = CustomerGroupUtil::get(intval($invisibility->getCustomerGroupId()->getEndpoint()));
            if ($customerGroupSW) {
                $collection->add($customerGroupSW);
            }
        }

        $productSW->setCustomerGroups($collection);

        // Tax
        $taxSW = Shopware()->Models()->getRepository('Shopware\Models\Tax\Tax')->findOneBy(array('tax' => $product->getVat()));
        if ($taxSW) {
            $productSW->setTax($taxSW);
        }

        // Manufacturer
        $manufacturerMapper = Mmc::getMapper('Manufacturer');
        $manufacturerSW = $manufacturerMapper->find(intval($product->getManufacturerId()->getEndpoint()));
        if ($manufacturerSW) {
            $productSW->setSupplier($manufacturerSW);
            $result->setManufacturerId(new Identity($manufacturerSW->getId(), $product->getManufacturerId()->getEndpoint()));
        }

        // ProductSpecialPrice
        foreach ($product->getSpecialPrices() as $i => $productSpecialPrice) {
            $collection = new \Doctrine\Common\Collections\ArrayCollection;
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

        $productSW->setMainDetail($detailSW);

        $violations = $this->Manager()->validate($productSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        // Save Product
        $this->Manager()->persist($productSW);

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

        $this->Manager()->flush();

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

        // Result
        $result->setId(new Identity($productSW->getId(), $product->getId()->getHost()));

        return $result;
    }
}