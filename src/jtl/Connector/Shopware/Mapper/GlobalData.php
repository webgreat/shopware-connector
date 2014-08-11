<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;

class GlobalData extends DataMapper
{
    public function save(\jtl\Connector\Shopware\Model\DataModel $globalData)
    {
        $element = Shopware()->Models()->getRepository('Shopware\Models\Config\Element')->findOneBy(array('name' => 'company'));

        die(var_dump($element));

        // Companies
        foreach ($globalData->getCompanies() as $company) {
            $company->setName(Shopware()->Config()->get('company'))
                ->setStreet(Shopware()->Config()->get('address'))
                ->setEMail(Shopware()->Config()->get('mail'))
                ->setTaxIdNumber(Shopware()->Config()->get('taxNumber'))
                ->setVatNumber(Shopware()->Config()->get('vatcheckadvancednumber'));
        }
    }
}