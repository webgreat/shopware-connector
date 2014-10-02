<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Drawing\ImageRelationType;
use \jtl\Core\Logger\Logger;
use \jtl\Connector\Model\Image as ImageModel;
use \jtl\Connector\Shopware\Model\Image as ImageConModel;
use \jtl\Connector\Model\DataModel;
use \jtl\Connector\Model\Identity;
use \Shopware\Models\Media\Media as MediaSW;
use \Shopware\Models\Article\Image as ArticleImageSW;
use \jtl\Connector\Shopware\Utilities\Mmc;

class Image extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Media\Media')->find((int)$id);
    }

    public function findAll($offset = null, $limit = null, $count = false, $relationType = null)
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();

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
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_articles_img', $rsm);
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_categories WHERE mediaID > 0', $rsm);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $query = Shopware()->Models()->createNativeQuery('SELECT count(*) as count FROM s_articles_supplier WHERE LENGTH(img) > 0', $rsm);
                break;
        }

        if ($query !== null) {
            $result = $query->getResult();
            if (isset($result[0]['count'])) {
                $count = (int)$result[0]['count'];
            }
        }

        return $count;
    }

    protected function buildQuery($offset = null, $limit = null, $relationType)
    {
        $data = array(
            ImageRelationType::TYPE_PRODUCT => array(
                'select' => array(
                    'image',
                    'media',
                    'parent',
                    'pmedia'
                ),
                'from' => array(
                    'model' => 'Shopware\Models\Article\Image',
                    'alias' => 'image'
                ),
                'leftJoin' => array(
                    array(
                        'join' => 'image.media',
                        'alias' => 'media'
                    ),
                    array(
                        'join' => 'image.parent',
                        'alias' => 'parent'
                    ),
                    array(
                        'join' => 'parent.media',
                        'alias' => 'pmedia'
                    )
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
                'join' => array(
                    array(
                        'join' => 'category.media',
                        'alias' => 'media'
                    )
                )
            ),
            ImageRelationType::TYPE_MANUFACTURER => array(
                'select' => 'supplier.id, media.id as mediaId, media.path as path',
                'from' => array(
                    'model' => 'Shopware\Models\Article\Supplier',
                    'alias' => 'supplier'
                ),
                'join' => array(
                    array(
                        'join' => 'Shopware\Models\Media\Media',
                        'alias' => 'media',
                        'conditionType' => \Doctrine\ORM\Query\Expr\Join::WITH,
                        'condition' => 'media.path = supplier.image'
                    )
                ),
                'where' => array(
                    'supplier.image != \'\''
                )
            )
        );

        if ($relationType !== null && !isset($data[$relationType])) {
            throw new \InvalidArgumentException("RelationType '{$relationType}' is not supported");
        }

        $builder = $this->Manager()->createQueryBuilder();

        $builder->select($data[$relationType]['select'])
            ->from($data[$relationType]['from']['model'], $data[$relationType]['from']['alias']);

        if (isset($data[$relationType]['join'])) {
            foreach ($data[$relationType]['join'] as $join) {
                $conditionType = isset($join['conditionType']) ? $join['conditionType'] : null;
                $condition = isset($join['condition']) ? $join['condition'] : null;

                $builder->join($join['join'], $join['alias'], $conditionType, $condition);
            }
        }

        if (isset($data[$relationType]['leftJoin'])) {
            foreach ($data[$relationType]['leftJoin'] as $leftJoin) {
                $conditionType = isset($leftJoin['conditionType']) ? $leftJoin['conditionType'] : null;
                $condition = isset($leftJoin['condition']) ? $leftJoin['condition'] : null;

                $builder->leftJoin($leftJoin['join'], $leftJoin['alias'], $conditionType, $condition);
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

    public function save(DataModel $image)
    {
        $mediaSW = null;
        $imageSW = null;
        $result = new ImageModel;

        if ($image->getAction() == DataModel::ACTION_DELETE) {   // Delete
            $this->deleteImageData($image);

            return $result;
        } else {    // Update or Insert
            $this->prepareImageAssociatedData($image, $mediaSW, $imageSW);

            $violations = $this->Manager()->validate($mediaSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->Manager()->persist($mediaSW);

            if ($imageSW !== null) {
                $this->Manager()->persist($imageSW);
            }

            $this->flush();

            $manager = Shopware()->Container()->get('thumbnail_manager');
            $manager->createMediaThumbnail($mediaSW, array(), true);
        }

        // Result
        $result->setId(new Identity(ImageConModel::generateId($image->getRelationType(), $imageSW->getId(), $mediaSW->getId()), $image->getId()->getHost()))
            ->setForeignKey(new Identity($image->getForeignKey()->getEndpoint(), $image->getForeignKey()->getHost()))
            ->setRelationType($image->getRelationType())
            ->setFilename(sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $mediaSW->getPath()));

        return $result;
    }

    protected function deleteImageData(DataModel &$image)
    {
        list($type, $imageId, $mediaId) = explode('_', $image->getId()->getEndpoint());

        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int)$imageId);
                if ($imageSW !== null) {
                    $this->Manager()->remove($imageSW);
                    $this->Manager()->flush();
                }
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int)$imageId);
                if ($categorySW !== null) {
                    $categorySW->setMedia(null);
                    $this->Manager()->persist($categorySW);
                    $this->Manager()->flush();
                }
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $supplierSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int)$imageId);
                if ($supplierSW !== null) {
                    $supplierSW->setImage('');
                    $this->Manager()->persist($supplierSW);
                    $this->Manager()->flush();
                }
                break;
        }
    }

    protected function prepareImageAssociatedData(DataModel &$image, MediaSW &$mediaSW = null, \Shopware\Components\Model\ModelEntity &$imageSW = null)
    {
        if (!file_exists($image->getFilename())) {
            throw new \Exception(sprintf('File (%s) does not exists', $image->getFilename()));
        }

        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;

        if ($imageId !== null) {
            list($type, $imageId, $mediaId) = explode('_', $image->getId()->getEndpoint());
            $mediaSW = $this->find((int)$mediaId);
        }

        $stats = stat($image->getFilename());
        $infos = pathinfo($image->getFilename());
        $file = new \Symfony\Component\HttpFoundation\File\File($image->getFilename());

        if ($mediaSW === null) {
            $albumId = null;
            switch ($image->getRelationType()) {
                case ImageRelationType::TYPE_PRODUCT:
                    $albumId = -1;
                    break;
                case ImageRelationType::TYPE_CATEGORY:
                    $albumId = -9;
                    break;
                case ImageRelationType::TYPE_MANUFACTURER:
                    $albumId = -12;
                    break;
                default:
                    $albumId = -10;
                    break;
            }

            $albumSW = $this->Manager()->getRepository('Shopware\Models\Media\Album')->find($albumId);
            if ($albumSW === null) {
                throw new \Exception(sprintf('Album with id (%s) not found', $albumId));
            }

            $mediaSW = new MediaSW;
            $mediaSW->setExtension(strtolower($infos['extension']))
                ->setAlbumId($albumId)
                ->setDescription('')
                ->setName($infos['filename'])
                ->setCreated(new \DateTime())
                ->setFileSize($stats['size'])
                ->setFile($file)
                ->setType(MediaSW::TYPE_IMAGE)
                ->setUserId(0)
                ->setAlbum($albumSW);
        } else {
            if ($this->generadeMD5($mediaSW->getPath()) != md5_file($image->getFilename())) {
                $manager = Shopware()->Container()->get('thumbnail_manager');
                $manager->removeMediaThumbnails($mediaSW);
                @unlink(sprintf('%s%s', Shopware()->DocPath(), $mediaSW->getPath()));
                $file = $file->move($this->getUploadDir(), $image->getFilename());
                $mediaSW->setFileSize($stats['size'])
                    ->setExtension(strtolower($infos['extension']))
                    ->setCreated(new \DateTime())
                    ->setFile($file);
            }
        }

        $this->prepareTypeSwitchAssociateData($image, $mediaSW, $imageSW);
    }

    protected function prepareTypeSwitchAssociateData(DataModel &$image, MediaSW &$mediaSW, \Shopware\Components\Model\ModelEntity &$imageSW = null)
    {
        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $this->prepareProductImageAssociateData($image, $mediaSW, $imageSW);
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $this->prepareCategoryImageAssociateData($image, $mediaSW, $imageSW);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $this->prepareManufacturerImageAssociateData($image, $mediaSW, $imageSW);
                break;
        }
    }

    protected function prepareProductImageAssociateData(DataModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        // Child?
        if ($this->isChild($image)) {
            $this->prepareChildImageAssociateData($image, $mediaSW, $imageSW);
        } else {
            $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;
            $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;

            // Try to load Image
            if ($imageId !== null) {
                list($type, $id, $mediaId) = explode('_', $image->getId()->getEndpoint());
                $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int)$id);
            }

            // New Image
            if ($imageSW === null) {
                $imageSW = new ArticleImageSW;
                $imageSW->setHeight(0);
                $imageSW->setDescription('');
                $imageSW->setWidth(0);
                $imageSW->setExtension($mediaSW->getExtension());

                if ($foreignId === null) {
                    throw new \Exception('ForeignKey cannot be null');
                }
                
                $productMapper = Mmc::getMapper('Product');
                $productSW = $productMapper->find((int)$image->getForeignKey()->getEndpoint());
                if ($productSW === null) {
                    throw new \Exception(sprintf('Cannot find product with id (%s)', $image->getForeignKey()->getEndpoint()));
                }

                $imageSW->setArticle($productSW);
            }

            $imageSW->setPosition($image->getSort());
            $main = ($image->getSort() == 1) ? 1 : 2;
            $imageSW->setMain($main);
            $imageSW->setPath($mediaSW->getName());
            $imageSW->setMedia($mediaSW);
        }
    }

    protected function prepareChildImageAssociateData(DataModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;

        // Try to load Image
        if ($imageId !== null) {
            list($type, $id, $mediaId) = explode('_', $image->getId()->getEndpoint());
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int)$id);
        }

        // New Image?
        if ($imageSW === null) {
            $imageSW = new ArticleImageSW;
            $imageSW->setHeight(0);
            $imageSW->setDescription('');
            $imageSW->setWidth(0);

            if ($foreignId === null) {
                throw new \Exception('ForeignKey cannot be null');
            }

            $productMapper = Mmc::getMapper('Product');
            list($detailId, $articleId) = explode('_', $image->getForeignKey()->getEndpoint());
            $detailSW = $productMapper->findDetail((int)$detailId);
            if ($detailSW === null) {
                throw new \Exception(sprintf('Cannot find child with id (%s)', $detailId));
            }

            $imageSW->setArticleDetail($detailSW);

            // Create new Parent
            $this->createParentImageData($image, $mediaSW, $imageSW);
        } else {
            // Update Image
            $parentImageSW = $imageSW->getParent();

            if ($this->generadeMD5($parentImageSW->getMedia()->getPath()) != $this->generadeMD5($mediaSW->getPath())) {
                $this->createParentImageData($image, $mediaSW, $imageSW);
            }
        }

        $imageSW->setExtension($mediaSW->getExtension());
        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);
    }

    protected function createParentImageData(DataModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW)
    {
        $productMapper = Mmc::getMapper('Product');

        // Create new Parent
        $parentImageSW = new ArticleImageSW;
        $parentImageSW->setHeight(0);
        $parentImageSW->setDescription('');
        $parentImageSW->setWidth(0);
        $parentImageSW->setExtension($mediaSW->getExtension());
        $parentImageSW->setMedia($mediaSW);
        $parentImageSW->setPosition($image->getSort());
        $parentImageSW->setPath($mediaSW->getName());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $parentImageSW->setMain($main);

        $productSW = $productMapper->find((int)$image->getForeignKey()->getEndpoint());
        if ($productSW === null) {
            throw new \Exception(sprintf('Cannot find product with id (%s)', $image->getForeignKey()->getEndpoint()));
        }

        $parentImageSW->setArticle($productSW);

        $this->Manager()->persist($parentImageSW);
        $imageSW->setParent($parentImageSW);
    }

    protected function prepareManufacturerImageAssociateData(DataModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int)$image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int)$foreignId);
        } else {
            throw new \Exception('Manufacturer foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find manufacturer with id (%s)', $foreignId));
        }

        $imageSW->setImage($mediaSW->getPath());
    }

    protected function prepareCategoryImageAssociateData(DataModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int)$image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int)$foreignId);
        } else {
            throw new \Exception('Category foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find category with id (%s)', $foreignId));
        }

        $imageSW->setMedia($mediaSW);
    }

    protected function isChild(DataModel &$image)
    {
        return (strlen($image->getForeignKey()->getEndpoint()) > 0 && strpos($image->getForeignKey()->getEndpoint(), '_') !== false);
    }

    protected function getUploadDir()
    {
        // the absolute directory path where uploaded documents should be saved
        return Shopware()->DocPath('media_' . strtolower(MediaSW::TYPE_IMAGE));
    }

    protected function generadeMD5($path)
    {
        return md5_file(sprintf('%s%s', Shopware()->DocPath(), $path));
    }
}