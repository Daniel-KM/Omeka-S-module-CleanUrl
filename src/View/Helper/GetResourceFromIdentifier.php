<?php

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Omeka\Api\Exception\NotFoundException;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\View\Helper
 */
class GetResourceFromIdentifier extends AbstractHelper
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get a resource from an identifier.
     *
     * @todo Use entity manager, not connection.
     *
     * @param string $identifier The identifier of the resource to find.
     * @param bool $withPrefix Optional. If identifier begins with prefix.
     * @param string $resourceName Optional. Search a specific resource type if any.
     * @return \Omeka\Api\Representation\AbstractResourceRepresentation|null
     */
    public function __invoke($identifier, $withPrefix = false, $resourceName = null)
    {
        $identifier = rawurldecode($identifier);
        if (empty($identifier)) {
            return null;
        }

        $bind = [];

        $propertyId = (int) $this->view->setting('cleanurl_identifier_property');

        if ($resourceName) {
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
            if (!isset($resourceTypes[$resourceName])) {
                return null;
            }

            $sqlResourceType = "AND resource.resource_type = ?";
            $bind[] = $resourceTypes[$resourceName];
            $sqlOrder = 'ORDER BY value.resource_id, value.id';
        } else {
            $sqlResourceType = '';
            $sqlOrder = "ORDER BY FIELD(resource.resource_type, 'Omeka\Entity\ItemSet', 'Omeka\Entity\Item', 'Omeka\Entity\Media'), value.resource_id, value.id";
        }

        $identity = $this->view->identity();
        $sqlWhereIsPublic = '';
        if (empty($identity)) {
            $sqlWhereIsPublic = 'AND resource.is_public = 1';
        }

        $collation = $this->view->setting('cleanurl_identifier_case_sensitive') ? 'COLLATE utf8mb4_bin' : '';

        if ($withPrefix) {
            $sqlWhereText = "AND value.value $collation = ?";
            $bind[] = $identifier;
        } else {
            $prefix = $this->view->setting('cleanurl_identifier_prefix');
            $identifiers = [
                $prefix . $identifier,
                $prefix . ' ' . $identifier, // Check with a space between prefix and identifier too.
            ];
            // Check prefix with a space and a no-break space.
            if ($this->view->setting('cleanurl_identifier_unspace')) {
                $unspace = str_replace([' ', ' '], '', $prefix);
                if ($prefix != $unspace) {
                    // Check with a space between prefix and identifier too.
                    $identifiers[] = $unspace . $identifier;
                    $identifiers[] = $unspace . ' ' . $identifier;
                }
            }
            $in = implode(',', array_fill(0, count($identifiers), '?'));
            $sqlWhereText = "AND value.value $collation IN ($in)";
            $bind = array_merge($bind, $identifiers);
        }

        $sql = <<<SQL
SELECT resource.resource_type, value.resource_id
FROM value
LEFT JOIN resource ON (value.resource_id = resource.id)
WHERE value.property_id = $propertyId
    $sqlResourceType
    $sqlWhereText
    $sqlWhereIsPublic
$sqlOrder
LIMIT 1;
SQL;

        $result = $this->connection->fetchAssoc($sql, $bind);
        $resource = null;
        if ($result) {
            $resourceTypes = [
                \Omeka\Entity\ItemSet::class => 'item_sets',
                \Omeka\Entity\Item::class => 'items',
                \Omeka\Entity\Media::class => 'media',
            ];
            $resource = $this->view->api()->read($resourceTypes[$result['resource_type']], $result['resource_id'])->getContent();
        } elseif (is_numeric($identifier) && $id = (int) $identifier) {
            // Return the resource via the Omeka id.
            $resourceName = $resourceName ?: 'resources';
            try {
                $resource = $this->view->api()->read($resourceName, $id)->getContent();
            } catch (NotFoundException $e) {
                $resource = null;
            }
        }

        return $resource;
    }
}
