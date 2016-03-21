<?php

namespace CleanUrl\View\Helper;

/**
 * Clean Url Get Record Type Identifiers
 */

use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceTypeIdentifiers extends AbstractHelper
{
    /**
     * Return identifiers for a record type, if any. It can be sanitized.
     *
     * @param string $resourceName Should be "item_sets", "items" or "media".
     * @param boolean $rawEncoded Sanitize the identifier for http or not.
     * @return array Associative array of record id and identifiers.
     */
    public function __invoke($resourceName, $rawEncoded = true)
    {
        if (!in_array($resourceName, array('item_sets', 'items', 'media'))) {
            return array();
        }

        $serviceLocator = $this->getView()->getHelperPluginManager()->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        // Use a direct query in order to improve speed.
        $db = $serviceLocator->get('Omeka\Connection');
        $propertyId = (integer) $settings->get('clean_url_identifier_property');
        $bind = array();

        $prefix = $settings->get('clean_url_identifier_prefix');
        if ($prefix) {
            // Keep only the identifier without the configured prefix.
            $prefixLenght = strlen($prefix) + 1;
            $sqlSelect = 'SELECT value.resource_id, TRIM(SUBSTR(value.value, ' . $prefixLenght . '))';
            $sqlWereText = 'AND value.value LIKE ?';
            $bind[] = $prefix . '%';
        }
        else {
            $sqlSelect = 'SELECT value.resource_id, value.value';
            $sqlWereText = '';
        }

        $apiAdapterManager = $serviceLocator->get('Omeka\ApiAdapterManager');
        $apiAdapter = $apiAdapterManager->get($resourceName);
        $resourceType = $apiAdapter->getEntityClass();

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
        $bind[] = $resourceType;
        $sth = $db->executeQuery($sql, $bind);
        $result = $sth->fetchAll(\PDO::FETCH_KEY_PAIR);

        return $rawEncoded
            ? array_map('rawurlencode', $result)
            : $result;
    }
}
