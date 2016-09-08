<?php

namespace CleanUrl\View\Helper;

/**
 * Clean Url Get Record Identifier
 */

use Doctrine\DBAL\Connection;
use Zend\View\Helper\AbstractHelper;
use Omeka\Api\Adapter\Manager as ApiAdapterManager;
use Omeka\Api\Representation\AbstractResourceRepresentation;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceIdentifier extends AbstractHelper
{
    protected $apiAdapterManager;
    protected $connection;

    public function __construct(ApiAdapterManager $apiAdapterManager, Connection $connection)
    {
        $this->apiAdapterManager = $apiAdapterManager;
        $this->connection = $connection;
    }

    /**
     * Return the identifier of a record, if any. It can be sanitized.
     *
     * @param AbstractResourceRepresentation $resource
     * @param boolean $rawEncoded Sanitize the identifier for http or not.
     * @return string Identifier of the record, if any, else empty string.
     */
    public function __invoke(AbstractResourceRepresentation $resource, $rawEncoded = true)
    {
        // Use a direct query in order to improve speed.
        $apiAdapter = $this->apiAdapterManager->get($resource->resourceName());
        $resourceType = $apiAdapter->getEntityClass();
        $bind = array(
            $resourceType,
            $resource->id(),
        );

        $prefix = $this->view->setting('clean_url_identifier_prefix');
        $checkUnspace = false;
        if ($prefix) {
            $bind[] = $prefix . '%';
            // Check prefix with a space and a no-break space.
            $unspace = str_replace(array(' ', ' '), '', $prefix);
            if ($prefix != $unspace && $this->view->setting('clean_url_identifier_unspace')) {
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

        $propertyId = (integer) $this->view->setting('clean_url_identifier_property');
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
        $identifier = $this->connection->fetchColumn($sql, $bind);

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
