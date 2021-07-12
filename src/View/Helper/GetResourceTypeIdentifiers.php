<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;

class GetResourceTypeIdentifiers extends AbstractHelper
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
     * @param \Doctrine\DBAL\Connection $connection
     * @param array $options
     */
    public function __construct(Connection $connection, array $options)
    {
        $this->connection = $connection;
        $this->options = $options;
    }

    /**
     * Return identifiers for a resource type, if any. It can be sanitized.
     *
     * @param string $resourceName Should be "item_sets", "items" or "media"
     *   or equivalent resource type.
     * @param bool $encode Sanitize the identifiers for http or not.
     * @param bool $skipPrefix Keep the prefix or not.
     * @return array List of identifiers.
     */
    public function __invoke($resourceName, $encode = false, $skipPrefix = false): array
    {
        $resourceClass = $this->convertNameToResourceClass($resourceName);
        if (!$resourceClass) {
            return [];
        }

        $resourceName = $this->convertResourceClassToResourceName($resourceClass);

        // Use a direct query in order to improve speed.
        $qb = $this->connection->createQueryBuilder()
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            // An identifier is always literal: it identifies a resource inside
            // the base. It can't be an external uri or a linked resource.
            ->where('value.type = "literal"')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $this->options[$resourceName]['property'])
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceClass)
            ->addOrderBy('value.resource_id', 'ASC')
            ->addOrderBy('value.id', 'ASC');

        $prefix = $this->options[$resourceName]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        if ($lengthPrefix) {
            if ($skipPrefix) {
                $qb
                    ->select([
                        // $qb->expr()->trim($qb->expr()->substring('value.text', $lengthPrefix + 1)),
                        '(TRIM(SUBSTR(value.value, ' . ($lengthPrefix + 1) . ')))',
                    ]);
            } else {
                $qb
                    ->select([
                        'value.value',
                    ]);
            }
            $qb
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', $prefix . '%');
        } else {
            $qb
                ->select([
                    'value.value',
                ]);
        }

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if ($encode) {
            $keepSlash = $this->options[$resourceName]['keep_slash'];
            return array_map(function ($v) use ($keepSlash) {
                return $this->encode($v, $keepSlash);
            }, $result);
        }

        return $result;
    }

    protected function convertNameToResourceClass(?string $resourceName): ?string
    {
        $resourceClasses = [
            'items' => Item::class,
            'item_sets' => ItemSet::class,
            'media' => Media::class,
            'resources' => '',
            'resource' => '',
            'resource:item' => Item::class,
            'resource:itemset' => ItemSet::class,
            'resource:media' => Media::class,
            // Avoid a check and make the plugin more flexible.
            \Omeka\Api\Representation\ItemRepresentation::class => Item::class,
            \Omeka\Api\Representation\ItemSetRepresentation::class => ItemSet::class,
            \Omeka\Api\Representation\MediaRepresentation::class => Media::class,
            Item::class => Item::class,
            ItemSet::class => ItemSet::class,
            Media::class => Media::class,
            \Omeka\Entity\Resource::class => '',
            'o:item' => Item::class,
            'o:item_set' => ItemSet::class,
            'o:media' => Media::class,
            // Other resource types.
            'item' => Item::class,
            'item_set' => ItemSet::class,
            'item-set' => ItemSet::class,
            'itemset' => ItemSet::class,
            'resource:item_set' => ItemSet::class,
            'resource:item-set' => ItemSet::class,
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
     * Encode a string.
     *
     * This method avoids to raw-urlencode characters that don't need.
     *
     * @see \Laminas\Router\Http\Segment::encode()
     *
     * @param string $value
     * @param bool $keepSlash
     * @return string
     */
    protected function encode($value, $keepSlash = false): string
    {
        static $urlencodeCorrectionMap;

        if (is_null($urlencodeCorrectionMap)) {
            $urlencodeCorrectionMap = [];
            $urlencodeCorrectionMap[false] = [
                '%21' => '!', // sub-delims
                '%24' => '$', // sub-delims
                '%26' => '&', // sub-delims
                '%27' => "'", // sub-delims
                '%28' => '(', // sub-delims
                '%29' => ')', // sub-delims
                '%2A' => '*', // sub-delims
                '%2B' => '+', // sub-delims
                '%2C' => ',', // sub-delims
                // '%2D' => '-', // unreserved - not touched by rawurlencode
                // '%2E' => '.', // unreserved - not touched by rawurlencode
                '%3A' => ':', // pchar
                '%3B' => ';', // sub-delims
                '%3D' => '=', // sub-delims
                '%40' => '@', // pchar
                // '%5F' => '_', // unreserved - not touched by rawurlencode
                // '%7E' => '~', // unreserved - not touched by rawurlencode
            ];
            $urlencodeCorrectionMap[true] = $urlencodeCorrectionMap[false];
            $urlencodeCorrectionMap[true]['%2F'] = '/';
        }

        return strtr(rawurlencode((string) $value), $urlencodeCorrectionMap[$keepSlash]);
    }
}
