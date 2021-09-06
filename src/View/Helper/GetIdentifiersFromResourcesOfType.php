<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\Resource;

class GetIdentifiersFromResourcesOfType extends AbstractHelper
{
    // The max number of the resources to create a temporary table.
    const CHUNK_RECORDS = 10000;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $options;
    /**
     * @param Connection $connection
     * @param array $options
     */
    public function __construct(Connection $connection, array $options)
    {
        $this->connection = $connection;
        $this->options = $options;
    }

    /**
     * Get identifiers from resources. The resources types should not be mixed.
     *
     * When resources are mixed, the options will be the first resource ones and
     * only this type of resource is returned. Use `GetIdentifiersFromResources()`
     * if needed.
     *
     * @todo Return public files when public is checked.
     *
     * @param array|AbstractResourceEntityRepresentation|Resource|int $resources
     *   A list of resources as object or as array of ids. Types should not be
     *   mixed. If object, it should be a resource or a representation.
     * @param string $resourceName The resource type if resource is an int.
     * @return array|string|null List of strings with id as key and identifier
     *   as value. Duplicates are not returned. If a single resource is
     *   provided, return a single string. Order is not kept.
     */
    public function __invoke($resources, $resourceName = null)
    {
        $isSingle = !is_array($resources);
        $resources = $isSingle ? [$resources] : array_filter($resources);

        // Check the list of resources.
        if (!count($resources)) {
            return $isSingle ? null : [];
        }

        // Extract internal ids from the list of resources.
        $first = reset($resources);
        if (is_object($first)) {
            if ($first instanceof AbstractResourceEntityRepresentation) {
                $resources = array_map(function ($v) {
                    return $v->id();
                }, $resources);
            } elseif ($first instanceof Resource) {
                $resources = array_map(function ($v) {
                    return $v->getId();
                }, $resources);
            } else {
                return $isSingle ? null : [];
            }
        }
        // Cast to integer the resources in an array.
        else {
            $resources = array_map('intval', $resources);
        }

        $resources = array_filter($resources);
        if (!count($resources)) {
            return $isSingle ? null : [];
        }

        // Check and normalize the resource type.
        if ($resourceName) {
            $resourceClass = $this->convertNameToResourceClass($resourceName);
        } else {
            $resourceClass = is_object($first)
                ? $this->convertNameToResourceClass(get_class($first))
                : null;
        }
        if (empty($resourceClass)) {
            return $isSingle ? null : [];
        }
        $resourceName = $this->convertResourceClassToResourceName($resourceClass);

        // Get the list of identifiers.
        $qb = $this->connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            // An identifier is always literal: it identifies a resource inside
            // the base. It can't be an external uri or a linked resource.
            ->where('value.type = "literal"')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $this->options[$resourceName]['property'])
            // Only one identifier by resource.
            ->groupBy(['value.resource_id'])
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC')
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceClass);

        $prefix = $this->options[$resourceName]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        if ($lengthPrefix) {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    'identifier' => $this->options[$resourceName]['prefix_part_of']
                        ? 'value.value'
                        // 'identifier' => $qb->expr()->trim($qb->expr()->substring('value.text', $lengthPrefix + 1)),
                        : '(TRIM(SUBSTR(value.value, ' . ($lengthPrefix + 1) . ')))',
                ])
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $prefix . '%');
        } else {
            $qb
                ->select([
                    // Should be the first column.
                    'id' => 'value.resource_id',
                    'identifier' => 'value.value',
                ]);
        }

        if ($isSingle) {
            $qb->setMaxResults(1);
        }

        // Create a temporary table when the number of resources is very big.
        $tempTable = count($resources) > self::CHUNK_RECORDS;
        if ($tempTable) {
            $query = 'DROP TABLE IF EXISTS `temp_resources`;';
            $stmt = $this->connection->executeQuery($query);
            // TODO Check if the id may be unique.
            // $query = 'CREATE TEMPORARY TABLE `temp_resources` (`id` INT UNSIGNED NOT NULL, PRIMARY KEY(`id`));';
            $query = 'CREATE TEMPORARY TABLE `temp_resources` (`id` INT UNSIGNED NOT NULL);';
            $stmt = $this->connection->executeQuery($query);
            foreach (array_chunk($resources, self::CHUNK_RECORDS) as $chunk) {
                $query = 'INSERT INTO `temp_resources` VALUES(' . implode('),(', $chunk) . ');';
                $stmt = $this->connection->executeQuery($query);
            }
            $qb
                // No where condition.
                ->innerJoin(
                    'value',
                    'temp_resources',
                    'temp_resources',
                    'temp_resources.id = value.resource_id'
                );
        }
        // The number of resources is reasonable.
        else {
            $qb
                // ->andWhere('value.resource_id IN (:resource_ids)')
                // ->setParameter('resource_ids', $resources, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
                ->andWhere('value.resource_id IN (' . implode(',', $resources) . ')');
        }

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $isSingle
            ? array_shift($result)
            : $result;
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
}
