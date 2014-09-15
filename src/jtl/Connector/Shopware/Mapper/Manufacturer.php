<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\Manufacturer as ManufacturerModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Core\Utilities\DataConverter;
use \jtl\Connector\Model\DataModel;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \Shopware\Models\Article\Supplier as ManufacturerSW;

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

    public function save(DataModel $manufacturer)
    {
        $manufacturerSW = null;
        $result = new ManufacturerModel;

        if ($manufacturer->getAction() == DataModel::ACTION_DELETE) {   // Delete
            $this->deleteManufacturerData($manufacturer);
        } else {    // Update or Insert
            $this->prepareManufacturerAssociatedData($manufacturer, $manufacturerSW);
            $this->prepareI18nAssociatedData($manufacturer, $manufacturerSW);

            $violations = $this->Manager()->validate($manufacturerSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->Manager()->persist($manufacturerSW);
            $this->flush();
        }

        // Result
        $result->setId(new Identity($manufacturerSW->getId(), $manufacturer->getId()->getHost()));

        return $result;
    }

    protected function deleteManufacturerData(DataModel &$manufacturer)
    {
        $manufacturerId = (strlen($manufacturer->getId()->getEndpoint()) > 0) ? (int)$manufacturer->getId()->getEndpoint() : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW = $this->find($manufacturerId);
            if ($manufacturerSW !== null) {
                $this->Manager()->remove($manufacturerSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function prepareManufacturerAssociatedData(DataModel &$manufacturer, ManufacturerSW &$manufacturerSW)
    {
        $manufacturerId = (strlen($manufacturer->getId()->getEndpoint()) > 0) ? (int)$manufacturer->getId()->getEndpoint() : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW = $this->find($manufacturerId);
        }

        if ($manufacturerSW === null) {
            $manufacturerSW = new ManufacturerSW;
        }

        $manufacturerSW->setName($manufacturer->getName())
            ->setLink($manufacturer->getWww());
    }

    protected function prepareI18nAssociatedData(DataModel &$manufacturer, ManufacturerSW &$manufacturerSW)
    {
        foreach ($manufacturer->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $manufacturerSW->setDescription($i18n->getDescription());
                $manufacturerSW->setMetaTitle($i18n->getTitleTag());
                $manufacturerSW->setMetaDescription($i18n->getMetaDescription());
                $manufacturerSW->setMetaKeywords($i18n->getMetaKeywords());
            }
        }
    }
}