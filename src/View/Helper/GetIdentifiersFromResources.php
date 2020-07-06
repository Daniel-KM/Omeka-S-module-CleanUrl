<?php

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\Resource;
use Zend\View\Helper\AbstractHelper;

/*
 * Clean Url Get Identifiers From Resources
 */

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetIdentifiersFromResources extends AbstractHelper
{
    // The max number of the resources to create a temporary table.
    const CHUNK_RECORDS = 10000;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var int
     */
    protected $propertyId;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var bool
     */
    protected $keepPrefix;

    /**
     * @param Connection $connection
     * @param int $propertyId
     * @param string $prefix
     * @param bool $keepPrefix
     */
    public function __construct(Connection $connection, $propertyId, $prefix, $keepPrefix)
    {
        $this->connection = $connection;
        $this->propertyId = $propertyId;
        $this->prefix = $prefix;
        $this->keepPrefix = $keepPrefix;
    }

    /**
     * Get identifiers from resources.
     *
     * @todo Return public files when public is checked.
     *
     * @param array|AbstractResourceEntityRepresentation|Resource|int $resources
     * A list of resources as object or as array of ids. Types shouldn't be
     * mixed. If object, it should be a resource.
     * @param string $resourceType The resource type or the resouce name if
     * $resources is an array.
     * @return array|string|null List of strings with id as key and identifier as value.
     * Duplicates are not returned. If a single resource is provided, return a
     * single string. Order is not kept.
     */
    public function __invoke($resources, $resourceType = null)
    {
        $isSingle = !is_array($resources);
        if ($isSingle) {
            $resources = [$resources];
        }

        // Check the list of resources.
        if (empty($resources)) {
            return $isSingle ? null : [];
        }

        // Extract internal ids from the list of resources.
        $first = reset($resources);
        if (is_object($first)) {
            if ($first instanceof AbstractResourceEntityRepresentation) {
                $resources = array_map(function ($v) {
                    return $v->id();
                }, $resources);
            } elseif ($first instanceof Resource) {
                $resources = array_map(function ($v) {
                    return $v->getId();
                }, $resources);
            } else {
                return $isSingle ? null : [];
            }
        }
        // Cast to integer the resources in an array.
        else {
            $resources = array_map('intval', $resources);
        }

        $resources = array_filter($resources);
        if (empty($resources)) {
            return $isSingle ? null : [];
        }

        // Check and normalize the resource type.
        $resourceTypes = [
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'items' => \Omeka\Entity\Item::class,
            'media' => \Omeka\Entity\Media::class,
            // Avoid a check.
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
        ];
        if ($resourceType && !isset($resourceTypes[$resourceType])) {
            return $isSingle ? null : [];
        }

        $resourceType = $resourceType
            ? $resourceTypes[$resourceType]
            : null;

        // Get the list of identifiers.
        $qb = $this->connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $this->propertyId)
            // Only one identifier by resource.
            ->groupBy(['value.resource_id'])
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC');

        if ($resourceType) {
            $qb
                ->andWhere('resource.resource_type = :resource_type')
                ->setParameter('resource_type', $resourceType);
        }

        if ($this->prefix) {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    'identifier' => $this->keepPrefix
                        ? 'value.value'
                        // 'identifier' => $qb->expr()->trim($qb->expr()->substring('value.text', mb_strlen($prefix) + 1)),
                        :'(TRIM(SUBSTR(value.value, ' . (mb_strlen($this->prefix) + 1) . ')))',
                ])
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $this->prefix . '%');
        } else {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    'identifier' => 'value.value',
                ]);
        }

        if ($isSingle) {
            $qb->setMaxResults(1);
        }

        // Create a temporary table when the number of resources is very big.
        $tempTable = count($resources) > self::CHUNK_RECORDS;
        if ($tempTable) {
            $query = 'DROP TABLE IF EXISTS temp_resources;';
            $stmt = $this->connection->query($query);
            // TODO Check if the id may be unique.
            // $query = 'CREATE TEMPORARY TABLE temp_resources (id INT UNSIGNED NOT NULL, PRIMARY KEY(id));';
            $query = 'CREATE TEMPORARY TABLE temp_resources (id INT UNSIGNED NOT NULL);';
            $stmt = $this->connection->query($query);
            foreach (array_chunk($resources, self::CHUNK_RECORDS) as $chunk) {
                $query = 'INSERT INTO temp_resources VALUES(' . implode('),(', $chunk) . ');';
                $stmt = $this->connection->query($query);
            }
            $qb
                // No where condition.
                ->innerJoin(
                    'value',
                    'temp_resources',
                    'temp_resources',
                    'temp_resources.id = value.resource_id'
                );
        }
        // The number of resources is reasonable.
        else {
            $qb
                // ->andWhere('value.resource_id IN (:resource_ids)')
                // ->setParameter('resource_ids', $resources, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
                ->andWhere('value.resource_id IN (' . implode(',', $resources) . ')');
        }

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $isSingle
            ? array_shift($result)
            : $result;
    }
}
