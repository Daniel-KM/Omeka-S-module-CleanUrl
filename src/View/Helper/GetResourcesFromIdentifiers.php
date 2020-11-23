<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

class GetResourcesFromIdentifiers extends AbstractHelper
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param bool
     */
    protected $supportAnyValue;

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param array $options
     * @param bool $supportAnyValue
     */
    public function __construct(Connection $connection, array $options, bool $supportAnyValue)
    {
        $this->connection = $connection;
        $this->options = $options;
        $this->supportAnyValue = $supportAnyValue;
    }

    /**
     * Get a list of resources from identifiers, that may be Omeka internal ids.
     *
     * When resource types of identifiers are mixed, the items options are used.
     *
     * @todo Merge or wrap for FindResourcesFromIdentifiers from BulkImport (and older from CsvImport), and Reference.
     *
     * @param array $identifiers Identifiers to find. May be numeric Omeka ids.
     *   Identifiers are raw-url-decoded.
     * @param string $resourceName Optional. Search a specific resource type if
     *   any. If not set, the used params (property, prefix, prefix is part of,
     *   sensitive, etc.) will be the items one.
     * @return \Omeka\Api\Representation\AbstractResourceRepresentation[]
     *   Associative array of resources by identifier. The resource is null if
     *   not found. Note: the number of found resources may be lower than the
     *   identifiers in case of duplicate identifiers.
     */
    public function __invoke(array $identifiers, $resourceName = null): array
    {
        // Identifiers are flipped to prepare result.
        // Even if keys are strings, they may be integers because of automatic
        // conversion for array keys.
        $identifiers = array_fill_keys(array_filter(array_map([$this, 'trimUnicode'], array_map('rawurldecode', array_map('strval', $identifiers)))), null);

        if (!count($identifiers)) {
            return [];
        }

        $resourceClass = $this->convertNameToResourceClass($resourceName);
        if ($resourceName && is_null($resourceClass)) {
            return $identifiers;
        }
        $resourceName = $this->convertResourceClassToResourceName($resourceClass);

        if (empty($this->options[$resourceName]['property'])) {
            return $identifiers;
        }

        $parameters = [];

        $isCaseSensitive = !empty($this->options[$resourceName]['case_sensitive']);
        $collation = $isCaseSensitive ? ' COLLATE utf8mb4_bin' : '';

        // TODO Use EntityManager to avoid final api checks for rights, but with collation.

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        if ($this->supportAnyValue) {
            $qb
                ->select([
                    $isCaseSensitive
                        ? 'ANY_VALUE(value.value) AS "identifier"'
                        : "LOWER(ANY_VALUE(value.value)) AS 'identifier'",
                    'ANY_VALUE(value.resource_id) AS "id"',
                ]);
        } else {
            $qb
                ->select([
                    $isCaseSensitive
                        ? 'value.value AS "identifier"'
                        : 'LOWER(value.value) AS "identifier"',
                    'value.resource_id AS "id"',
                ]);
        }

        $qb
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'value.resource_id = resource.id')
            ->addGroupBy('value.value' . $collation)
            ->addOrderBy('"id"', 'ASC')
            ->addOrderBy('value.id', 'ASC')
            ->andWhere($expr->eq('value.property_id', ':property_id'));
        $parameters['property_id'] = (int) $this->options[$resourceName]['property'];

        if ($resourceClass) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':resource_type'));
            $parameters['resource_type'] = $resourceClass;
        }

        // Quicker process for anonymous people.
        // Rights are more complex for logged users (fixed below via api).
        $user = $this->view->identity();
        if (empty($user)) {
            $qb
                ->andWhere($expr->eq('resource.is_public', 1));
        }

        $variants = [];

        // Manage cases "identifier", "doc: identifier" and "ark:/xxx/identifier".
        // The case "ark:%2Fxxx%2Fidentifier" is managed via url decode.
        // The identifiers can be searched with or without prefix (so only
        // "identifier" for ark, that has a prefix).

        $prefix = $this->options[$resourceName]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        $noPrefix = !$lengthPrefix;
        $prefixIsPartOfIdentifier = $lengthPrefix
            && $this->options[$resourceName]['prefix_part_of'];

        // Many cases because support sensitive case, with or without prefix,
        // and with or without space. Nevertheless, the check is quick.

        // A quick check for performance.
        if (count($identifiers) === 1 && ($noPrefix)) {
            $identifier = (string) key($identifiers);
            $parameters['identifier'] = $isCaseSensitive ? $identifier : mb_strtolower($identifier);
            $variants[$parameters['identifier']] = $identifier;
            $qb
                ->andWhere($expr->eq('value.value' . $collation, ':identifier'));
        } else {
            if ($noPrefix) {
                if ($isCaseSensitive) {
                    $variants = array_combine(array_keys($identifiers), array_keys($identifiers));
                } else {
                    foreach (array_keys($identifiers) as $identifier) {
                        $variants[mb_strtolower((string) $identifier)] = $identifier;
                    }
                }
            } elseif ($prefixIsPartOfIdentifier) {
                if ($isCaseSensitive) {
                    foreach (array_keys($identifiers) as $identifier) {
                        if (mb_strpos((string) $identifier, $prefix) === 0) {
                            $variants[$identifier] = $identifier;
                        } else {
                            $variants[$prefix . $identifier] = $identifier;
                            // Check with a space between prefix and identifier too.
                            $variants[$prefix . ' ' . $identifier] = $identifier;
                        }
                    }
                }
                // Same as above, but lower keys.
                else {
                    foreach (array_keys($identifiers) as $identifier) {
                        if (mb_strpos((string) $identifier, $prefix) === 0) {
                            $variants[mb_strtolower($identifier)] = $identifier;
                        } else {
                            $variants[mb_strtolower($prefix . $identifier)] = $identifier;
                            $variants[mb_strtolower($prefix . ' ' . $identifier)] = $identifier;
                        }
                    }
                }
            } else {
                if ($isCaseSensitive) {
                    foreach (array_keys($identifiers) as $identifier) {
                        $variants[$prefix . $identifier] = $identifier;
                        $variants[$prefix . ' ' . $identifier] = $identifier;
                    }
                }
                // Same as above, but lower keys.
                else {
                    foreach (array_keys($identifiers) as $identifier) {
                        $variants[mb_strtolower($prefix . $identifier)] = $identifier;
                        $variants[mb_strtolower($prefix . ' ' . $identifier)] = $identifier;
                    }
                }
            }

            // Warning: there is a difference between qb / dbal and qb / orm for
            // "in" in qb, when a placeholder is used, there should be one
            // placeholder for each value for expr->in().
            $placeholders = [];
            foreach (array_keys($variants) as $key => $value) {
                $placeholder = 'identifier_' . $key;
                $parameters[$placeholder] = $value;
                $placeholders[] = ':' . $placeholder;
            }
            $qb
                ->andWhere($expr->in('value.value' . $collation, $placeholders));
        }

        $qb
            ->setParameters($parameters);

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Get representations and check numeric identifiers as resource id.
        // It allows to check rights too (currently, Connection is used, not EntityManager).
        $api = $this->view->api();
        foreach (array_intersect_key($result, $variants) as $identifier => $id) {
            try {
                $identifiers[$variants[$identifier]] = $api->read($resourceName, ['id' => $id])->getContent();
            } catch (NotFoundException $e) {
                // Nothing to do.
            }
        }

        // Check remaining numeric identifiers, for example when some resources
        // don't have an identifier and the id is used instead of.
        $remainingNumeric = array_keys(array_filter($identifiers, function ($v, $k) {
            return is_null($v) && is_numeric($k) && ($k = (int) $k);
        }, ARRAY_FILTER_USE_BOTH));
        if (count($remainingNumeric)) {
            $remainingNumeric = $api->search($resourceName, ['id' => $remainingNumeric])->getContent();
            foreach ($remainingNumeric as $remaining) {
                $identifiers[$remaining->id()] = $remaining;
            }
        }

        return $identifiers;
    }

    protected function convertNameToResourceClass(?string $resourceName): ?string
    {
        $resourceClasses = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'resources' => '',
            'resource' => '',
            'resource:item' => \Omeka\Entity\Item::class,
            'resource:itemset' => \Omeka\Entity\ItemSet::class,
            'resource:media' => \Omeka\Entity\Media::class,
            // Avoid a check and make the plugin more flexible.
            \Omeka\Api\Representation\ItemRepresentation::class => \Omeka\Entity\Item::class,
            \Omeka\Api\Representation\ItemSetRepresentation::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Api\Representation\MediaRepresentation::class => \Omeka\Entity\Media::class,
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
        return $resourceClasses[$resourceName] ?? null;
    }

    protected function convertResourceClassToResourceName(?string $resourceClass): string
    {
        $resourceNames = [
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\Media::class => 'media',
        ];
        return $resourceNames[$resourceClass] ?? 'resources';
    }

    /**
     * Trim all whitespaces.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string): string
    {
        return preg_replace('/^[\s\h\v[:blank:][:space:]]+|[\s\h\v[:blank:][:space:]]+$/u', '', (string) $string);
    }
}
