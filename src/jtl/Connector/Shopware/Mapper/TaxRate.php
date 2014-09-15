<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\DataModel;
use \jtl\Connector\Model\TaxRate as TaxRateModel;
use \Shopware\Models\Tax\Tax as TaxRateSW;

class TaxRate extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Tax\Tax')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Tax\Tax')->findOneBy($kv);
    }
    
    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'tax'
            )
            ->from('Shopware\Models\Tax\Tax', 'tax')
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

    public function save(DataModel $taxRate)
    {
        $taxRateSW = null;
        $result = new TaxRateModel;

        if ($taxRate->getAction() == DataModel::ACTION_DELETE) {   // Delete
            $this->deleteTaxRateData($taxRate);
        } else {    // Update or Insert
            $this->prepareTaxRateAssociatedData($taxRate, $taxRateSW);

            $violations = $this->Manager()->validate($taxRateSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->Manager()->persist($taxRateSW);
            $this->flush();
        }

        // Result
        $result->setId(new Identity($taxRateSW->getId(), $taxRate->getId()->getHost()));

        return $result;
    }

    protected function deleteTaxRateData(DataModel &$taxRate)
    {
        $taxRateId = (strlen($taxRate->getId()->getEndpoint()) > 0) ? (int)$taxRate->getId()->getEndpoint() : null;

        if ($taxRateId !== null && $taxRateId > 0) {
            $taxRateSW = $this->find($taxRateId);
            if ($taxRateSW !== null) {
                $this->Manager()->remove($taxRateSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function prepareTaxRateAssociatedData(DataModel &$taxRate, TaxRateSW &$taxRateSW)
    {
        $taxRateId = (strlen($taxRate->getId()->getEndpoint()) > 0) ? (int)$taxRate->getId()->getEndpoint() : null;

        if ($taxRateId !== null && $taxRateId > 0) {
            $taxRateSW = $this->find($taxRateId);
        }

        if ($taxRateSW === null) {
            $taxRateSW = new TaxRateSW;
        }

        $taxRateSW->setTax($taxRate->getRate())
            ->setName($taxRate->getRate());
    }
}