<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Drawing\ImageRelationType;

class Image extends DataMapper
{
    public function findAll($offset = null, $limit = null, $count = false, $relationType = null)
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();

        switch ($relationType) {
            case ImageRelationType::TYPE_PRODUCT_VARIATION_VALUE:
                return Shopware()->Db()->fetchAll('SELECT r.id, r.option_id as foreignKey, i.img as path, i.parent_id as masterImageId, i.extension, i.articleID, o.group_id
                                                    FROM s_article_img_mapping_rules AS r
                                                    JOIN s_article_img_mappings AS m ON m.id = r.mapping_id
                                                    JOIN s_articles_img AS i ON i.id = m.image_id
                                                    JOIN s_article_configurator_options AS o ON o.id = r.option_id
                                                    GROUP BY r.option_id');
        }

        $query = $this->buildQuery($offset, $limit, $relationType);

        if ($count) {
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

            return $paginator->count();
        }
        else {
            return $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }
    }

    public function fetchCount($offset = 0, $limit = 100, $relationType = null)
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('count', 'count');

        $query = null;
        $count = 0;
        switch ($relationType) {
            case ImageRelationType::TYPE_PRODUCT:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_articles_img WHERE main = 1', $rsm);
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_categories WHERE mediaID > 0', $rsm);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_articles_supplier WHERE LENGTH(img) > 0', $rsm);
                break;
            case ImageRelationType::TYPE_PRODUCT_VARIATION_VALUE:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count
                                                                    FROM
                                                                    (
                                                                        SELECT r.option_id
                                                                        FROM s_article_img_mapping_rules AS r
                                                                        JOIN s_article_img_mappings AS m ON m.id = r.mapping_id
                                                                        JOIN s_articles_img AS i ON i.id = m.image_id
                                                                        GROUP BY r.option_id
                                                                    ) as x', $rsm);
                break;
        }

        if ($query !== null) {
            $result = $query->getResult();
            if (isset($result[0]['count'])) {
                $count = (int)$result[0]['count'];
            }
        }

        //$this->initBuilder();

        return $count;
    }

    protected function buildQuery($offset = null, $limit = null, $relationType)
    {
        $data = array(
            ImageRelationType::TYPE_PRODUCT => array(
                'select' => array(
                    'article',
                    'images',
                    'media'
                ),
                'from' => array(
                    'model' => 'Shopware\Models\Article\Article',
                    'alias' => 'article'
                ),
                'innerJoin' => array(
                    array(
                        'join' => 'article.images',
                        'alias' => 'images'
                    )
                ),
                'leftJoin' => array(
                    array(
                        'join' => 'images.media',
                        'alias' => 'media'
                    )
                ),
                'where' => array(
                    'images.main = 1'
                )
            ),
            ImageRelationType::TYPE_CATEGORY => array(
                'select' => array(
                    'category',
                    'media'
                ),
                'from' => array(
                    'model' => 'Shopware\Models\Category\Category',
                    'alias' => 'category'
                ),
                'innerJoin' => array(
                    array(
                        'join' => 'category.media',
                        'alias' => 'media'
                    )
                )
            ),
            ImageRelationType::TYPE_MANUFACTURER => array(
                'select' => array(
                    'supplier'
                ),
                'from' => array(
                    'model' => 'Shopware\Models\Article\Supplier',
                    'alias' => 'supplier'
                )
            )
        );

        if ($relationType !== null && !isset($data[$relationType])) {
            throw new \InvalidArgumentException("RelationType '{$relationType}' is not supported");
        }

        $builder = $this->Manager()->createQueryBuilder();

        $builder->select($data[$relationType]['select'])
            ->from($data[$relationType]['from']['model'], $data[$relationType]['from']['alias']);

        if (isset($data[$relationType]['innerJoin'])) {
            foreach ($data[$relationType]['innerJoin'] as $innerJoin) {
                $builder->innerJoin($innerJoin['join'], $innerJoin['alias']);
            }
        }

        if (isset($data[$relationType]['leftJoin'])) {
            foreach ($data[$relationType]['leftJoin'] as $leftJoin) {
                $builder->leftJoin($leftJoin['join'], $leftJoin['alias']);
            }
        }

        if (isset($data[$relationType]['where'])) {
            $i = 0;
            foreach ($data[$relationType]['where'] as $i => $where) {
                if ($i > 0) {
                    $builder->andWhere($where);
                } else {
                    $builder->where($where);
                }
            }
        }

        if ($offset !== null) {
            $builder->setFirstResult($offset);
        }
        
        if ($limit !== null) {
            $builder->setMaxResults($limit);
        }

        $query = $builder->getQuery();

        return $query;
    }
}