<?php

namespace CleanUrl\View\Helper;

/*
 * Clean Url Get Record Type Identifiers
 */

use Doctrine\DBAL\Connection;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceTypeIdentifiers extends AbstractHelper
{
    protected $connection;
    protected $propertyId;
    protected $prefix;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return identifiers for a record type, if any. It can be sanitized.
     *
     * @param string $resourceName Should be "item_sets", "items" or "media"
     * or equivalent resource type.
     * @param bool $rawUrlEncode Sanitize the identifiers for http or not.
     * @param bool $skipPrefix Keep the prefix or not.
     * @return array List of identifiers.
     */
    public function __invoke($resourceName, $rawUrlEncode = false, $skipPrefix = false)
    {
        $resourceTypes = [
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'item' => \Omeka\Entity\Item::class,
            'media' => \Omeka\Entity\Media::class,
            // Be more flexible.
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
            \DoctrineProxies\__CG__\Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \DoctrineProxies\__CG__\Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \DoctrineProxies\__CG__\Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
        ];
        if (!isset($resourceTypes[$resourceName])) {
            return [];
        }

        $resourceType = $resourceTypes[$resourceName];

        if (empty($this->propertyId)) {
            $this->propertyId = (int) $this->view->setting('cleanurl_identifier_property');
            $this->prefix = $this->view->setting('cleanurl_identifier_prefix');
        }

        // Use a direct query in order to improve speed.
        $qb = $this->connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $this->propertyId)
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceType)
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC');

        if ($this->prefix) {
            if ($skipPrefix) {
                $qb
                    ->select([
                        // $qb->expr()->trim($qb->expr()->substring('value.text', mb_strlen($this->prefix) + 1)),
                        '(TRIM(SUBSTR(value.value, ' . (mb_strlen($this->prefix) + 1) . ')))',
                    ]);
            } else {
                $qb
                    ->select([
                        'value.value',
                    ]);
            }
            $qb
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $this->prefix . '%');
        } else {
            $qb
                ->select([
                    'value.value',
                ]);
        }

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $rawUrlEncode
            ? array_map('rawurlencode', $result)
            : $result;
    }

    public function setPropertyId($propertyId)
    {
        $this->propertyId = $propertyId;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}
