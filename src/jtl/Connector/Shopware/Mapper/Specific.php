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
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use \jtl\Connector\Model\DataModel;
use \Shopware\Models\Property\Option as OptionSW;
use \Shopware\Models\Property\Value as ValueSW;

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

            $translation = new TranslationUtil;
            for ($i = 0; $i < count($options); $i++) {
                foreach ($shops as $shop) {
                    $translationOption = $translation->read($shop['locale']['id'], 'propertyoption', $options[$i]['id']);
                    if (!empty($translationOption)) {
                        $translationOption['shopId'] = $shop['id'];
                        $translationOption['name'] = $translationOption['optionName'];
                        $options[$i]['translations'][$shop['locale']['locale']] = $translationOption;
                    }

                    for ($j = 0; $j < count($options[$i]['values']); $j++) {
                        foreach ($shops as $shop) {
                            $translationValue = $translation->read($shop['locale']['id'], 'propertyvalue', $options[$i]['values'][$j]['id']);
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

    public function save(DataModel $specific)
    {
        $optionSW = null;
        $result = new SpecificModel;

        if ($specific->getAction() == DataModel::ACTION_DELETE) { // DELETE
            $this->deleteSpecificData($specific);
        } else { // UPDATE OR INSERT
            $this->prepareSpecificAssociatedData($specific, $optionSW);
            $this->prepareI18nAssociatedData($specific, $optionSW);
            $this->prepareValueAssociatedData($specific, $optionSW);

            $violations = $this->Manager()->validate($optionSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            // Save
            $this->Manager()->persist($optionSW);
            $this->Manager()->flush();

            $this->deleteTranslationData($optionSW);
            $this->saveTranslationData($specific, $optionSW);
        }

        // Result
        $result->setId(new Identity($optionSW->getId(), $specific->getId()->getHost()));

        return $result;
    }

    protected function deleteSpecificData(DataModel &$specific)
    {
        $specificId = (strlen($specific->getId()->getEndpoint()) > 0) ? (int)$specific->getId()->getEndpoint() : null;

        if ($specificId !== null && $specificId > 0) {
            $specificSW = $this->find($specificId);
            if ($specificSW !== null) {
                $this->deleteTranslationData($optionSW);
                $this->Manager()->remove($specificSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function prepareSpecificAssociatedData(DataModel &$specific, OptionSW &$optionSW)
    {
        $specificId = (strlen($specific->getId()->getEndpoint()) > 0) ? (int)$specific->getId()->getEndpoint() : null;

        if ($specificId !== null && $specificId > 0) {
            $optionSW = $this->find($specificId);
        }

        if ($optionSW === null) {
            $optionSW = new OptionSW;
        }

        $optionSW->setFilterable(true);
    }

    protected function prepareI18nAssociatedData(DataModel &$specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        foreach ($specific->getI18ns() as $i18n) {
            if ($i18n->getLocaleName() == Shopware()->Shop()->getLocale()->getLocale()) {
                $optionSW->setName($i18n->getName());
            }
        }
    }

    protected function prepareValueAssociatedData(DataModel &$specific, OptionSW &$optionSW)
    {
        // SpecificValues
        foreach ($specific->getValues() as $specificValue) {
            $valueSW = null;

            if (strlen($specificValue->getId()->getEndpoint()) > 0) {
                $valueSW = $this->findValue(intval($specificValue->getId()->getEndpoint()));
            }

            if ($specificValue->getAction() == DataModel::ACTION_DELETE) {  // Delete
                if ($valueSW !== null) {
                    $this->deleteValueTranslationData($valueSW);
                    $this->Manager()->remove($valueSW);
                }
            } else {    // Update or Insert
                if ($valueSW === null) {
                    $valueSW = new ValueSW;
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
        }
    }

    protected function saveTranslationData(DataModel &$specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        $translation = new TranslationUtil;
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

    protected function deleteTranslationData(OptionSW &$optionSW)
    {
        $translation = new TranslationUtil;
        $translation->delete('propertyoption', $optionSW->getId());

        foreach ($optionSW->getValues() as $valueSW) {
            $translation->delete('propertyvalue', $valueSW->getId());
        }
    }

    protected function deleteValueTranslationData(ValueSW &$valueSW)
    {
        $translation = new TranslationUtil;
        $translation->delete('propertyvalue', $valueSW->getId());
    }
}