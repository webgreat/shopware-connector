<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\Specific as SpecificModel;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;

class Specific extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Option')->find($id);
    }

    public function findValue($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Value')->find($id);
    }

    public function findAll($offset = 0, $limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'option',
                'values'
            )
            ->from('Shopware\Models\Property\Option', 'option')
            ->leftJoin('option.values', 'values')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $options = iterator_to_array($paginator);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationReader = new \Shopware_Components_Translation();
            for ($i = 0; $i < count($options); $i++) {
                foreach ($shops as $shop) {
                    $translationOption = $translationReader->read($shop['locale']['id'], 'propertyoption', $options[$i]['id']);
                    if (!empty($translationOption)) {
                        $translationOption['shopId'] = $shop['id'];
                        $translationOption['name'] = $translationOption['optionName'];
                        $options[$i]['translations'][$shop['locale']['locale']] = $translationOption;
                    }

                    for ($j = 0; $j < count($options[$i]['values']); $j++) {
                        foreach ($shops as $shop) {
                            $translationValue = $translationReader->read($shop['locale']['id'], 'propertyvalue', $options[$i]['values'][$j]['id']);
                            if (!empty($translationValue)) {
                                $translationValue['shopId'] = $shop['id'];
                                $translationValue['name'] = $translationValue['optionValue'];
                                $options[$i]['values'][$j]['translations'][$shop['locale']['locale']] = $translationValue;
                            }
                        }
                    }
                }
            }

            return $options;
        }
    }

    public function fetchCount($offset = 0, $limit = 100)
    {
        return $this->findAll($offset, $limit, true);
    }

    public function save(SpecificModel $specific)
    {
        $result = new SpecificModel;
        $optionSW = null;

        if (strlen($specific->getId()->getEndpoint()) > 0) {
            $optionSW = $this->find(intval($specific->getId()->getEndpoint()));
        }

        if ($optionSW === null) {
            $optionSW = new \Shopware\Models\Property\Option;
        }

        // SpecificI18n
        foreach ($specific->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $optionSW->setName($i18n->getName());
            }
        }

        // SpecificValues
        foreach ($specific->getValues() as $specificValue) {
            $valueSW = null;

            if (strlen($specificValue->getId()->getEndpoint()) > 0) {
                $valueSW = $this->findValue(intval($specificValue->getId()->getEndpoint()));
            }

            if ($valueSW === null) {
                $valueSW = new \Shopware\Models\Property\Value;
            }

            $valueSW->setPosition($specificValue->getSort())
                ->setOption($optionSW);

            // SpecificValueI18n
            foreach ($specificValue->getI18ns() as $i18n) {
                if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                    $valueSW->setValue($i18n->getValue());
                }
            }

            $this->Manager()->persist($valueSW);
        }

        $violations = $this->Manager()->validate($optionSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        // Save
        $this->Manager()->persist($optionSW);
        $this->Manager()->flush();

        $this->saveTranslationData($specific, $optionSW);

        // Result
        $result->setId(new Identity($optionSW->getId(), $specific->getId()->getHost()));

        return $result;
    }

    protected function saveTranslationData(DataModel &$specific, \Shopware\Models\Property\Option &$optionSW)
    {
        // SpecificI18n
        $translation = new \Shopware_Components_Translation;
        foreach ($specific->getI18ns() as $i18n) {
            $locale = LocaleUtil::getByKey($i18n->getLocaleName());
            if ($locale && $i18n->getLocaleName() != Shopware()->Shop()->getLocale()->getLocale()) {
                $translation->write(
                    $locale->getId(),
                    'propertyoption',
                    $optionSW->getId(),
                    array(
                        'optionName' => $i18n->getName()
                    )
                );
            } else {
                Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLocaleName()), Logger::WARNING, 'database');
            }
        }

        foreach ($specific->getValues() as $value) {
            foreach ($optionSW->getValues() as $valueSW) {
                foreach ($value->getI18ns() as $i18n) {
                    if ($valueSW->getValue() == $i18n->getValue()) {
                        $locale = LocaleUtil::getByKey($i18n->getLocaleName());
                        if ($locale && $i18n->getLocaleName() != Shopware()->Shop()->getLocale()->getLocale()) {
                            $translation->write(
                                $locale->getId(),
                                'propertyvalue',
                                $valueSW->getId(),
                                array(
                                    'optionValue' => $i18n->getName()
                                )
                            );
                        } else {
                            Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLocaleName()), Logger::WARNING, 'database');
                        }
                    }
                }
            }
        }
    }
}