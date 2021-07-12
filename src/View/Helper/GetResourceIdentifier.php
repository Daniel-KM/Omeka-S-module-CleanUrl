<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;

class GetResourceIdentifier extends AbstractHelper
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
     * Return the identifier of a record, if any. It can be sanitized.
     *
     * @todo Get identifier without request (representation values)? Check performances.
     * @todo Delegate this feature in the representation.
     *
     * @param AbstractResourceRepresentation|Resource $resource
     * @param bool $encode Sanitize the identifier for http or not.
     * @param bool $skipPrefix Keep the prefix or not.
     * @return string Identifier of the record, if any, else empty string.
     */
    public function __invoke($resource, $encode = false, $skipPrefix = false): string
    {
        $resourceClassesToTypes = [
            ItemSetRepresentation::class => 'item_sets',
            ItemRepresentation::class => 'items',
            MediaRepresentation::class => 'media',
            ItemSet::class => 'item_sets',
            Item::class => 'items',
            Media::class => 'media',
            \DoctrineProxies\__CG__\Omeka\Entity\ItemSet::class => 'item_sets',
            \DoctrineProxies\__CG__\Omeka\Entity\Item::class => 'items',
            \DoctrineProxies\__CG__\Omeka\Entity\Media::class => 'media',
        ];
        $resourceClass = get_class($resource);
        if (!isset($resourceClassesToTypes[$resourceClass])) {
            return '';
        }
        $resourceType = $resourceClassesToTypes[$resourceClass];
        $resourceTypesToClasses = [
            'item_sets' => ItemSet::class,
            'items' => Item::class,
            'media' => Media::class,
        ];
        $resourceClass = $resourceTypesToClasses[$resourceType];

        // Use a direct query in order to improve speed.
        $bind = [
            'property_id' => $this->options[$resourceType]['property'],
            'resource_type' => $resourceClass,
            'resource_id' => $resource instanceof Resource ? $resource->getId() : $resource->id(),
        ];

        $prefix = $this->options[$resourceType]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        if ($lengthPrefix) {
            $bind['prefix'] = $prefix . '%';
            $sqlWhereText = 'AND value.value LIKE :prefix';
        } else {
            $sqlWhereText = '';
        }

        $sql = <<<SQL
SELECT `value`.`value`
FROM `value`
    LEFT JOIN `resource` ON (`value`.`resource_id` = `resource`.`id`)
WHERE `value`.`type` = "literal"
    AND `value`.`property_id` = :property_id
    AND `resource`.`resource_type` = :resource_type
    AND `resource`.`id` = :resource_id
    $sqlWhereText
ORDER BY `value`.`id`
LIMIT 1
;
SQL;
        $identifier = $this->connection->fetchColumn($sql, $bind);

        // Keep only the identifier without the configured prefix.
        if ($identifier) {
            if ($skipPrefix && $lengthPrefix) {
                $identifier = trim(mb_substr($identifier, $lengthPrefix));
            }
            return $encode
                ? $this->encode($identifier, $this->options[$resourceType]['keep_slash'])
                : $identifier;
        }

        return '';
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
