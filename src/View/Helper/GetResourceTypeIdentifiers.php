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

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return identifiers for a record type, if any. It can be sanitized.
     *
     * @param string $resourceName Should be "item_sets", "items" or "media"
     * or equivalent resource type.
     * @param bool $rawEncoded Sanitize the identifier for http or not.
     * @return array List of identifiers.
     */
    public function __invoke($resourceName, $rawEncoded = true)
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
        $propertyId = (integer) $this->view->setting('clean_url_identifier_property');
        $prefix = $this->view->setting('clean_url_identifier_prefix');

        $bind = [];
        $bind[] = $resourceType;

        if ($prefix) {
            // Keep only the identifier without the configured prefix.
            $prefixLength = strlen($prefix) + 1;
            $sqlSelect = 'SELECT TRIM(SUBSTR(value.value, ' . $prefixLength . '))';
            $sqlWereText = 'AND value.value LIKE ?';
            $bind[] = $prefix . '%';
        } else {
            $sqlSelect = 'SELECT value.value';
            $sqlWereText = '';
        }

        $sql = "
            $sqlSelect
            FROM value
                LEFT JOIN resource ON (value.resource_id = resource.id)
            WHERE value.property_id = '$propertyId'
                AND resource.resource_type = ?
                $sqlWereText
            ORDER BY value.resource_id ASC, value.id ASC
        ";
        $sth = $this->connection->executeQuery($sql, $bind);
        $result = $sth->fetchAll(\PDO::FETCH_COLUMN);

        return $rawEncoded
            ? array_map('rawurlencode', $result)
            : $result;
    }
}
