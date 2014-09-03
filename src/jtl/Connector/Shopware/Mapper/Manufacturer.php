<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\Manufacturer as ManufacturerModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \Shopware\Models\Article\Supplier as SupplierModel;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Mmc;

class Manufacturer extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Supplier', $id);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'supplier',
                'attribute'
            )
            ->from('Shopware\Models\Article\Supplier', 'supplier')
            ->leftJoin('supplier.attribute', 'attribute')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function prepareData(ManufacturerContainer $container)
    {
        $manufacturer = $container->getMainModel();

        // Manufacturer
        $data = DataConverter::toArray(DataModel::map(false, null, $manufacturer));

        // ManufacturerI18n
        foreach ($container->getManufacturerI18ns() as $manufacturerI18n) {
            $data = array_merge($data, DataConverter::toArray(DataModel::map(false, null, $manufacturerI18n)));
        }

        return $data;
    }

    public function save(DataModel $manufacturer)
    {
        $manufacturerSW = null;

        $id = (strlen($manufacturer->getId()->getEndpoint()) > 0) ? (int)$manufacturer->getId()->getEndpoint() : null;

        if ($id > 0) {
            $manufacturerSW = $this->find($id);
        }

        if ($manufacturer->getAction() == DataModel::ACTION_DELETE) {   // Delete
            if ($manufacturerSW !== null) {
                $this->Manager()->remove($manufacturerSW);
                $this->flush();
            }
        } else {    // Update or Insert
            if ($manufacturerSW === null) {
                $manufacturerSW = new SupplierModel;
            }

            $manufacturerSW->setName($manufacturer->getName())
                ->setLink($manufacturer->getWww());

            foreach ($manufacturer->getI18ns() as $i18n) {
                if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                    $manufacturerSW->setDescription($i18n->getDescription());
                    $manufacturerSW->setMetaTitle($i18n->getTitleTag());
                    $manufacturerSW->setMetaDescription($i18n->getMetaDescription());
                    $manufacturerSW->setMetaKeywords($i18n->getMetaKeywords());
                }
            }

            $violations = $this->Manager()->validate($manufacturerSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->Manager()->persist($manufacturerSW);
            $this->flush();
        }

        // Result
        $result = new ManufacturerModel;
        $result->setId(new Identity($manufacturerSW->getId(), $manufacturer->getId()->getHost()));

        return $result;
    }
}