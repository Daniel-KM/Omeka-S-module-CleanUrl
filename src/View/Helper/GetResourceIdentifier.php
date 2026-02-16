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

        // Use a direct query in order to improve speed.
        // The resource type is already verified above, so the join on
        // `resource` table is not needed: resource_id identifies exactly
        // one resource of a known type.
        $qb = $this->connection->createQueryBuilder()
            ->select('value.value')
            ->from('value', 'value')
            // An identifier is always literal: it identifies a resource inside
            // the base. It can't be an external uri or a linked resource.
            ->where('value.type = "literal"')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $this->options[$resourceType]['property'])
            ->andWhere('value.resource_id = :resource_id')
            ->setParameter('resource_id', $resource instanceof Resource ? $resource->getId() : $resource->id())
            ->addOrderBy('value.id', 'ASC')
            ->setMaxResults(1);

        $prefix = $this->options[$resourceType]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        if ($lengthPrefix) {
            $qb
                ->andWhere('value.value LIKE :prefix')
                ->setParameter('prefix', addcslashes($prefix, '%_') . '%');
        }

        $identifier = $this->connection->executeQuery($qb, $qb->getParameters())->fetchOne();

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

        if ($urlencodeCorrectionMap === null) {
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
