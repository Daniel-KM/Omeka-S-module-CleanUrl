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
     * @return array List of identifiers.
     */
    public function __invoke($resourceName, $rawUrlEncode = true)
    {
        $resourceTypes = [
            'item_sets' => 'Omeka\Entity\ItemSet',
            'item' => 'Omeka\Entity\Item',
            'media' => 'Omeka\Entity\Media',
            // Be more flexible.
            'Omeka\Entity\ItemSet' => 'Omeka\Entity\ItemSet',
            'Omeka\Entity\Item' => 'Omeka\Entity\Item',
            'Omeka\Entity\Media' => 'Omeka\Entity\Media',
        ];
        if (!isset($resourceTypes[$resourceName])) {
            return [];
        }

        $resourceType = $resourceTypes[$resourceName];

        if (empty($this->propertyId)) {
            $this->propertyId = (integer) $this->view->setting('clean_url_identifier_property');
            $this->prefix = $this->view->setting('clean_url_identifier_prefix');
        }
        $propertyId = $this->propertyId;
        $prefix = $this->prefix;

        // Use a direct query in order to improve speed.
        $connection = $this->connection;
        $qb = $connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $propertyId)
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceType)
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC');

        if ($prefix) {
            $qb
                ->select([
                    // $qb->expr()->trim($qb->expr()->substring('value.text', strlen($this->prefix) + 1)),
                    '(TRIM(SUBSTR(value.value, ' . (strlen($prefix) + 1) . ')))',
                ])
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $prefix . '%');
        } else {
            $qb
                ->select([
                    'value.value',
                ]);
        }

        $stmt = $connection->executeQuery($qb, $qb->getParameters());
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
