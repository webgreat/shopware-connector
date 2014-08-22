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

    public function save(CustomerGroupModel $customerGroup)
    {
        $result = new CustomerGroupModel;
        $customerGroupSW = null;
        if (strlen($customerGroup->getId()->getEndoint()) > 0) {
            $customerGroupSW = $this->find(intval($customerGroup->getId()->getEndoint()));
        }

        if ($customerGroupSW === null) {
            $customerGroupSW = new \Shopware\Models\Customer\Group;
            
            // @todo: generate unique key
            $customerGroupSW->setKey();
        }

        $customerGroupSW->setDiscount($customerGroup->getDiscount())
            ->setTaxInput(!$customerGroup->getApplyNetPrice());

        // I18n
        foreach ($customerGroup->getI18n() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $customerGroupSW->setName($i18n->getName());
            }
        }

        $violations = $this->Manager()->validate($customerGroupSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        // Save
        $this->Manager()->persist($customerGroupSW);
        $this->Manager()->flush();

        $result->setId(new Identity($customerGroupSW->getId(), $customerGroup->getId()->getHost()));

        return $result;
    }
}