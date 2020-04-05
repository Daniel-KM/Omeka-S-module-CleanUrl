<?php

namespace CleanUrl\View\Helper;

/*
 * Clean Url Get Record Identifier
 */

use Doctrine\DBAL\Connection;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceIdentifier extends AbstractHelper
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return the identifier of a record, if any. It can be sanitized.
     *
     * @param AbstractResourceRepresentation|Resource $resource
     * @param bool $rawUrlEncode Sanitize the identifier for http or not.
     * @param bool $skipPrefix Keep the prefix or not.
     * @return string Identifier of the record, if any, else empty string.
     */
    public function __invoke($resource, $rawUrlEncode = false, $skipPrefix = false)
    {
        $resourceTypes = [
            ItemSetRepresentation::class => ItemSet::class,
            ItemRepresentation::class => Item::class,
            MediaRepresentation::class => Media::class,
            ItemSet::class => ItemSet::class,
            Item::class => Item::class,
            Media::class => Media::class,
            \DoctrineProxies\__CG__\Omeka\Entity\ItemSet::class => ItemSet::class,
            \DoctrineProxies\__CG__\Omeka\Entity\Item::class => Item::class,
            \DoctrineProxies\__CG__\Omeka\Entity\Media::class => Media::class,
        ];
        $resourceType = get_class($resource);
        if (!isset($resourceTypes[$resourceType])) {
            return '';
        }

        $propertyId = (int) $this->view->setting('cleanurl_identifier_property');
        $prefix = $this->view->setting('cleanurl_identifier_prefix');

        // Use a direct query in order to improve speed.
        $bind = [
            $resourceTypes[$resourceType],
            $resource instanceof Resource ? $resource->getId() : $resource->id(),
        ];

        $checkUnspace = false;
        if ($prefix) {
            $bind[] = $prefix . '%';
            // Check prefix with a space and a no-break space.
            $unspace = str_replace([' ', ' '], '', $prefix);
            if ($prefix != $unspace && $this->view->setting('cleanurl_identifier_unspace')) {
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
            if ($prefix && $skipPrefix) {
                $length = $checkUnspace && mb_strpos($identifier, $unspace) === 0
                    // May be a prefix with space.
                    ? mb_strlen($unspace)
                    // Normal prefix.
                    : mb_strlen($prefix);
                $identifier = trim(mb_substr($identifier, $length));
            }
            return $rawUrlEncode
                ? rawurlencode($identifier)
                : $identifier;
        }

        return '';
    }
}
