<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\CustomerGroup as CustomerGroupModel;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Model\DataModel;
use \Shopware\Models\Customer\Group as CustomerGroupSW;

class CustomerGroup extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Customer\Group')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Customer\Group')->findOneBy($kv);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'customergroup',
                'attribute'
            )
            ->from('Shopware\Models\Customer\Group', 'customergroup')
            ->leftJoin('customergroup.attribute', 'attribute')
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

    public function save(DataModel $customerGroup)
    {
        $customerGroupSW = null;
        $result = new CustomerGroupModel;

        if ($customerGroup->getAction() == DataModel::ACTION_DELETE) { // DELETE
            $this->deleteCustomerGroupData($customerGroup);
        } else { // UPDATE or INSERT
            $this->prepareCategoryAssociatedData($customerGroup, $customerGroupSW);
            $this->prepareI18nAssociatedData($customerGroup, $customerGroupSW);

            $violations = $this->Manager()->validate($customerGroupSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            // Save
            $this->Manager()->persist($customerGroupSW);
            $this->Manager()->flush();
        }

        $result->setId(new Identity($customerGroupSW->getId(), $customerGroup->getId()->getHost()));

        return $result;
    }

    protected function deleteCustomerGroupData(DataModel &$customerGroup)
    {
        $customerGroupId = (strlen($customerGroup->getId()->getEndpoint()) > 0) ? (int)$customerGroup->getId()->getEndpoint() : null;

        if ($customerGroupId !== null && $customerGroupId > 0) {
            $customerGroupSW = $this->find($customerGroupId);
            if ($customerGroupSW !== null) {
                $this->Manager()->remove($customerGroupSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function prepareCategoryAssociatedData(DataModel &$customerGroup, CustomerGroupSW &$customerGroupSW)
    {
        $customerGroupId = (strlen($customerGroup->getId()->getEndpoint()) > 0) ? (int)$customerGroup->getId()->getEndpoint() : null;

        if ($customerGroupId !== null && $customerGroupId > 0) {
            $customerGroupSW = $this->find($customerGroupId);
        }

        if ($customerGroupSW === null) {
            $customerGroupSW = new CustomerGroupSW;

            // @todo: generate unique key
            $customerGroupSW->setKey();
        }

        $customerGroupSW->setDiscount($customerGroup->getDiscount())
            ->setTaxInput(!$customerGroup->getApplyNetPrice());
    }

    protected function prepareI18nAssociatedData(DataModel &$customerGroup, CustomerGroupSW &$customerGroupSW)
    {
        // I18n
        foreach ($customerGroup->getI18n() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $customerGroupSW->setName($i18n->getName());
            }
        }
    }
}