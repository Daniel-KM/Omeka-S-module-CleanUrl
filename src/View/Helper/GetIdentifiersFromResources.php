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

    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get identifiers from resources.
     *
     * @todo Return public files when public is checked.
     *
     * @param array|AbstractResourceEntityRepresentation|Resource $resources
     * A list of resources as object or as array of ids. Types shouldn't be
     * mixed. If object, it should be a resource.
     * @param string $resourceType The resource type or the resouce name if
     * $resources is an array.
     * @param bool $checkPublic Filter results by public (default).
     * @return array|string List of strings with id as key and identifier as value.
     * Duplicates are not returned. If a single resource is provided, return a
     * single string. Order is not kept.
     */
    public function __invoke($resources, $resourceType = null, $checkPublic = true)
    {
        // Check the list of resources.
        if (empty($resources)) {
            return;
        }

        $one = is_object($resources);
        if ($one) {
            $resources = [$resources];
        }

        // Extract internal ids from the list of resources.
        $first = reset($resources);
        if (is_object($first)) {
            if ($first instanceof AbstractResourceEntityRepresentation) {
                $resourceType = $first->resourceName();
                $resources = array_map(function ($v) {
                    return $v->id();
                }, $resources);
            } elseif ($first instanceof Resource) {
                $resourceType = $first->getResourceName();
                $resources = array_map(function ($v) {
                    return $v->getId();
                }, $resources);
            } else {
                return;
            }
        }
        // Cast to integer the resources in an array.
        else {
            $resources = array_map('intval', $resources);
        }

        $resources = array_filter($resources);
        if (empty($resources)) {
            return;
        }

        // Check and normalize the resource type.
        $resourceTypes = [
            'item_sets' => 'Omeka\Entity\ItemSet',
            'items' => 'Omeka\Entity\Item',
            'media' => 'Omeka\Entity\Media',
            // Avoid a check.
            'Omeka\Entity\ItemSet' => 'Omeka\Entity\ItemSet',
            'Omeka\Entity\Item' => 'Omeka\Entity\Item',
            'Omeka\Entity\Media' => 'Omeka\Entity\Media',
        ];
        if (!isset($resourceTypes[$resourceType])) {
            return;
        }

        $resourceType = $resourceTypes[$resourceType];
        $propertyId = (integer) $this->view->setting('clean_url_identifier_property');
        $prefix = $this->view->setting('clean_url_identifier_prefix');

        // Get the list of identifiers.
        $connection = $this->connection;
        $qb = $connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $propertyId)
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceType)
            // Only one identifier by resource.
            ->groupBy(['value.resource_id'])
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC');

        if ($prefix) {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    // 'identifier' => $qb->expr()->trim($qb->expr()->substring('value.text', strlen($prefix) + 1)),
                    'identifier' => '(TRIM(SUBSTR(value.value, ' . (strlen($prefix) + 1) . ')))',
                ])
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $prefix . '%');
        } else {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    'identifier' => 'value.value',
                ]);
        }

        // TODO Don't filter result in admin theme or identified users.
        if ($checkPublic) {
            $qb
                ->andWhere('resource.is_public = 1');
        }

        if ($one) {
            $qb->setMaxResults(1);
        }

        // Create a temporary table when the number of resources is very big.
        $tempTable = count($resources) > self::CHUNK_RECORDS;
        if ($tempTable) {
            $query = 'DROP TABLE IF EXISTS temp_resources;';
            $stmt = $connection->query($query);
            $query = 'CREATE TEMPORARY TABLE temp_resources (id INT UNSIGNED NOT NULL);';
            $stmt = $connection->query($query);
            foreach (array_chunk($resources, self::CHUNK_RECORDS) as $chunk) {
                $query = 'INSERT INTO temp_resources VALUES(' . implode('),(', $chunk) . ');';
                $stmt = $connection->query($query);
            }
            $qb
                ->innerJoin(
                    'value',
                    'temp_resources',
                    'temp_resources',
                    'temp_resources.id = value.resource_id'
                );
            // No where condition.
        }
        // The number of resources is reasonable.
        else {
            $qb
                // ->andWhere('value.resource_id IN (:resource_ids)')
                // ->setParameter('resource_ids', $resources, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
                ->andWhere('value.resource_id IN (' . implode(',', $resources) . ')');
        }

        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $one
            ? array_shift($result)
            : $result;
    }
}
