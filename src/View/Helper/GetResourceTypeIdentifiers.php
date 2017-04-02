<?php

namespace CleanUrl\View\Helper;

/*
 * Clean Url Get Record Type Identifiers
 */

use Doctrine\DBAL\Connection;
use Zend\View\Helper\AbstractHelper;
use Omeka\Api\Adapter\Manager as ApiAdapterManager;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceTypeIdentifiers extends AbstractHelper
{
    protected $apiAdapterManager;
    protected $connection;

    public function __construct(ApiAdapterManager $apiAdapterManager, Connection $connection)
    {
        $this->apiAdapterManager = $apiAdapterManager;
        $this->connection = $connection;
    }

    /**
     * Return identifiers for a record type, if any. It can be sanitized.
     *
     * @param string $resourceName Should be "item_sets", "items" or "media".
     * @param bool $rawEncoded Sanitize the identifier for http or not.
     * @return array Associative array of record id and identifiers.
     */
    public function __invoke($resourceName, $rawEncoded = true)
    {
        if (!in_array($resourceName, ['item_sets', 'items', 'media'])) {
            return [];
        }

        // Use a direct query in order to improve speed.
        $apiAdapter = $this->apiAdapterManager->get($resourceName);
        $resourceType = $apiAdapter->getEntityClass();
        $propertyId = (integer) $this->view->setting('clean_url_identifier_property');

        $bind = [];
        $bind[] = $resourceType;

        $prefix = $this->view->setting('clean_url_identifier_prefix');
        if ($prefix) {
            // Keep only the identifier without the configured prefix.
            $prefixLength = strlen($prefix) + 1;
            $sqlSelect = 'SELECT value.resource_id, TRIM(SUBSTR(value.value, ' . $prefixLength . '))';
            $sqlWereText = 'AND value.value LIKE ?';
            $bind[] = $prefix . '%';
        } else {
            $sqlSelect = 'SELECT value.resource_id, value.value';
            $sqlWereText = '';
        }

        // The "order by id DESC" allows to get automatically the first row in
        // php result and avoids a useless subselect in sql (useless because in
        // almost all cases, there is only one identifier).
        $sql = "
            $sqlSelect
            FROM value
                LEFT JOIN resource ON (value.resource_id = resource.id)
            WHERE value.property_id = '$propertyId'
                AND resource.resource_type = ?
                $sqlWereText
            ORDER BY value.resource_id, value.id DESC
        ";

        $sth = $this->connection->executeQuery($sql, $bind);
        $result = $sth->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $rawEncoded
            ? array_map('rawurlencode', $result)
            : $result;
    }
}
