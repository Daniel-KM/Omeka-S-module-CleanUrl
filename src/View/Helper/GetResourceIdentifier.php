<?php

namespace CleanUrl\View\Helper;

/**
 * Clean Url Get Record Identifier
 */

use Omeka\Api\Representation\AbstractResourceRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceIdentifier extends AbstractHelper
{
    /**
     * Return the identifier of a record, if any. It can be sanitized.
     *
     * @param AbstractResourceRepresentation $resource
     * @param boolean $rawEncoded Sanitize the identifier for http or not.
     * @return string Identifier of the record, if any, else empty string.
     */
    public function __invoke(AbstractResourceRepresentation $resource, $rawEncoded = true)
    {
        $serviceLocator = $this->getView()->getHelperPluginManager()->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $apiAdapterManager = $serviceLocator->get('Omeka\ApiAdapterManager');

        // Use a direct query in order to improve speed.
        $db = $serviceLocator->get('Omeka\Connection');
        $apiAdapter = $apiAdapterManager->get($resource->resourceName());
        $resourceType = $apiAdapter->getEntityClass();
        $bind = array(
            $resourceType,
            $resource->id(),
        );

        $prefix = $settings->get('clean_url_identifier_prefix');
        $checkUnspace = false;
        if ($prefix) {
            $bind[] = $prefix . '%';
            // Check prefix with a space and a no-break space.
            $unspace = str_replace(array(' ', ' '), '', $prefix);
            if ($prefix != $unspace && $settings->get('clean_url_identifier_unspace')) {
                $checkUnspace = true;
                $sqlWhereText = 'AND (value.value LIKE ? OR value.value LIKE ?)';
                $bind[] = $unspace . '%';
            }
            // Normal prefix.
            else {
                $sqlWhereText = 'AND value.value LIKE ?';
            }
        }
        // No prefix.
        else {
            $sqlWhereText = '';
        }

        $propertyId = (integer) $settings->get('clean_url_identifier_property');
        $sql = "
            SELECT value.value
            FROM value
                LEFT JOIN resource ON (value.resource_id = resource.id)
            WHERE value.property_id = '$propertyId'
                AND resource.resource_type = ?
                AND resource.id = ?
                $sqlWhereText
            ORDER BY value.id
            LIMIT 1
        ";
        $identifier = $db->fetchColumn($sql, $bind);

        // Keep only the identifier without the configured prefix.
        if ($identifier) {
            if ($prefix) {
                $length = $checkUnspace && strpos($identifier, $unspace) === 0
                    // May be a prefix with space.
                    ? strlen($unspace)
                    // Normal prefix.
                    : strlen($prefix);
                $identifier = trim(substr($identifier, $length));
            }
            return $rawEncoded
                ? rawurlencode($identifier)
                : $identifier;
        }

        return '';
    }
}
