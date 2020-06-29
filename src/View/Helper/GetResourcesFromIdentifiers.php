<?php

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Omeka\Api\Exception\NotFoundException;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\View\Helper
 */
class GetResourcesFromIdentifiers extends AbstractHelper
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param bool
     */
    protected $supportAnyValue;

    /**
     * @param Connection $connection
     * @param bool $supportAnyValue
     */
    public function __construct(Connection $connection, $supportAnyValue)
    {
        $this->connection = $connection;
        $this->supportAnyValue = $supportAnyValue;
    }

    /**
     * Get a list of resources from identifiers.
     *
     * @todo Use entity manager, not connection (does not seem to manage collation).
     * @todo Merge or wrap for FindResourcesFromIdentifiers from BulkImport (and older from CsvImport), and Reference.
     *
     * @param array $identifiers Identifiers to find. May be numeric Omeka ids.
     *   Identifiers are raw-url-decoded.
     * @param bool $withPrefix Optional. If identifiers start with the prefix.
     * @param string $resourceName Optional. Search a specific resource type if any.
     * @return \Omeka\Api\Representation\AbstractResourceRepresentation[]
     *   Associative array of resources by identifier. The resource is null if
     *   not found. Note: the number of found resources may be lower than the
     *   identifiers in case of duplicate identifiers.
     */
    public function __invoke(array $identifiers, $withPrefix = false, $resourceName = null)
    {
        $identifiers = array_fill_keys(array_filter(array_map([$this, 'trimUnicode'], array_map('rawurldecode', $identifiers))), null);
        if (!count($identifiers)) {
            return [];
        }

        $resourceType = $this->convertResourceNameToResourceType($resourceName);
        if ($resourceName && is_null($resourceType)) {
            return $identifiers;
        }
        $resourceName = $this->convertResourceTypeToResourceName($resourceType);

        $propertyId = (int) $this->view->setting('cleanurl_identifier_property');
        if (!$propertyId) {
            return $identifiers;
        }

        $parameters = [];

        $caseSensitiveIdentifier = (bool) $this->view->setting('cleanurl_identifier_case_sensitive');
        $collation = $caseSensitiveIdentifier ? 'COLLATE utf8mb4_bin' : '';

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        if ($this->supportAnyValue) {
            $qb
                ->select([
                    $caseSensitiveIdentifier ? 'ANY_VALUE(value.value) AS "identifier"' : "LOWER(ANY_VALUE(value.value)) AS 'identifier'",
                    'ANY_VALUE(value.resource_id) AS "id"',
                ])
                ->from('value', 'value')
                ->leftJoin('value', 'resource', 'resource', 'value.resource_id = resource.id')
                ->addGroupBy("value.value $collation")
                ->addOrderBy('"id"', 'ASC');
        // TODO Add order by value.id (for duplicate identifiers)?
        } else {
            $qb
                ->select([
                    $caseSensitiveIdentifier ? 'value.value AS "identifier"' : 'LOWER(value.value) AS "identifier"',
                    'value.resource_id AS "id"',
                ])
                ->from('value', 'value')
                ->leftJoin('value', 'resource', 'resource', 'value.resource_id = resource.id')
                ->addGroupBy("value.value $collation")
                ->addOrderBy('"id"', 'ASC')
                ->addOrderBy('value.id', 'ASC');
        }

        $qb
            ->andWhere($expr->eq('value.property_id', ':property_id'));
        $parameters['property_id'] = $propertyId;

        if ($resourceType) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':resource_type'));
            $parameters['resource_type'] = $resourceType;
        }

        // Quicker process for anonymous people.
        // Rights are more complex for logged users (fixed below via api).
        $user = $this->view->identity();
        if (empty($user)) {
            $qb
                ->andWhere($expr->eq('resource.is_public', 1));
        }

        $prefix = $this->view->setting('cleanurl_identifier_prefix');

        if ($withPrefix || !strlen($prefix)) {
            // A quick check for performance.
            if (count($identifiers) === 1) {
                $qb
                    ->andWhere($expr->eq("value.value $collation", ':identifier'));
                $parameters['identifier'] = key($identifiers);
            } else {
                // Warning: there is a difference between qb / dbal and qb / orm for
                // "in" in qb, when a placeholder is used, there should be one
                // placeholder for each value for expr->in().
                $placeholders = [];
                foreach (array_keys($identifiers) as $key => $value) {
                    $placeholder = 'identifier_' . $key;
                    $parameters[$placeholder] = $value;
                    $placeholders[] = ':' . $placeholder;
                }
                $qb
                    ->andWhere($expr->in("value.value $collation", $placeholders));
            }
        } else {
            $allIdentifiers = [];
            foreach (array_keys($identifiers) as $identifier) {
                $allIdentifiers[] = $prefix . $identifier;
                // Check with a space between prefix and identifier too.
                $allIdentifiers[] = $prefix . ' ' . $identifier;
            }
            // Check prefix with a space and a no-break space.
            if ($this->view->setting('cleanurl_identifier_unspace')) {
                $unspace = str_replace([' ', ' '], '', $prefix);
                if ($prefix != $unspace) {
                    // Check with a space between prefix and identifier too.
                    $allIdentifiers[] = $unspace . $identifier;
                    $allIdentifiers[] = $unspace . ' ' . $identifier;
                }
            }
            // Warning: there is a difference between qb / dbal and qb / orm for
            // "in" in qb, when a placeholder is used, there should be one
            // placeholder for each value for expr->in().
            $placeholders = [];
            foreach ($allIdentifiers as $key => $value) {
                $placeholder = 'identifier_' . $key;
                $parameters[$placeholder] = $value;
                $placeholders[] = ':' . $placeholder;
            }
            $qb
                ->andWhere($expr->in("value.value $collation", $placeholders));
        }

        $qb
            ->setParameters($parameters);

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $api = $this->view->api();
        if ($caseSensitiveIdentifier) {
            foreach (array_keys($identifiers) as $identifier) {
                try {
                    if (isset($result[$identifier])) {
                        $identifiers[$identifier] = $api->read($resourceName, $result[$identifier])->getContent();
                    } elseif (is_numeric($identifier) && $id = (int) $identifier) {
                        $identifiers[$identifier] = $api->read($resourceName, $id)->getContent();
                    }
                } catch (NotFoundException $e) {
                    // Nothing to do.
                }
            }
        } else {
            $lowerIdentifiers = array_combine($identifiers, array_map('mb_strtolower', array_flip($identifiers)));
            foreach ($lowerIdentifiers as $identifier => $lowerIdentifier) {
                try {
                    if (isset($result[$lowerIdentifier])) {
                        $identifiers[$identifier] = $api->read($resourceName, $result[$lowerIdentifier])->getContent();
                    } elseif (is_numeric($identifier) && $id = (int) $identifier) {
                        $identifiers[$identifier] = $api->read($resourceName, $id)->getContent();
                    }
                } catch (NotFoundException $e) {
                    // Nothing to do.
                }
            }
        }

        return $identifiers;
    }

    protected function convertResourceNameToResourceType($resourceName)
    {
        $resourceTypes = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'resources' => '',
            'resource' => '',
            'resource:item' => \Omeka\Entity\Item::class,
            'resource:itemset' => \Omeka\Entity\ItemSet::class,
            'resource:media' => \Omeka\Entity\Media::class,
            // Avoid a check and make the plugin more flexible.
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
            \Omeka\Entity\Resource::class => '',
            'o:item' => \Omeka\Entity\Item::class,
            'o:item_set' => \Omeka\Entity\ItemSet::class,
            'o:media' => \Omeka\Entity\Media::class,
            // Other resource types.
            'item' => \Omeka\Entity\Item::class,
            'item_set' => \Omeka\Entity\ItemSet::class,
            'item-set' => \Omeka\Entity\ItemSet::class,
            'itemset' => \Omeka\Entity\ItemSet::class,
            'resource:item_set' => \Omeka\Entity\ItemSet::class,
            'resource:item-set' => \Omeka\Entity\ItemSet::class,
        ];
        return isset($resourceTypes[$resourceName])
        ? $resourceTypes[$resourceName]
        : null;
    }

    protected function convertResourceTypeToResourceName($resourceType)
    {
        $resourceNames = [
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\Media::class => 'media',
        ];
        return isset($resourceNames[$resourceType])
            ? $resourceNames[$resourceType]
            : 'resources';
    }

    /**
     * Trim all whitespaces.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string)
    {
        return preg_replace('/^[\s\h\v[:blank:][:space:]]+|[\s\h\v[:blank:][:space:]]+$/u', '', $string);
    }
}
