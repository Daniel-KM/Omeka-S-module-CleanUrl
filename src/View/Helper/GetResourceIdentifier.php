<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use CleanUrl\ResourceNameTrait;
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
    use ResourceNameTrait;
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $options;

    /**
     * Identifiers already fetched, by resource type and id.
     *
     * @var array
     */
    protected $identifiers = [];

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
        if (class_exists('DigitalObject\Module', false)) {
            $resourceClassesToTypes[\DigitalObject\Api\Representation\DigitalObjectRepresentation::class] = 'digital_objects';
            $resourceClassesToTypes[\DigitalObject\Entity\DigitalObject::class] = 'digital_objects';
            $resourceClassesToTypes[\DoctrineProxies\__CG__\DigitalObject\Entity\DigitalObject::class] = 'digital_objects';
        }
        $resourceClass = get_class($resource);
        if (!isset($resourceClassesToTypes[$resourceClass])) {
            return '';
        }
        $resourceType = $resourceClassesToTypes[$resourceClass];
        if (empty($this->options[$resourceType]['property'])) {
            return '';
        }

        // Use a direct query in order to improve speed.
        // The resource type is already verified above, so the join on
        // `resource` table is not needed: resource_id identifies exactly
        // one resource of a known type.
        $resourceId = $resource instanceof Resource ? $resource->getId() : $resource->id();
        $prefix = $this->options[$resourceType]['prefix'];
        $lengthPrefix = mb_strlen($prefix);
        // The same resource may be linked many times in a page.
        $key = $resourceType . ':' . $resourceId;
        // Limit the memory used by a job that processes many resources.
        if (count($this->identifiers) > 1000) {
            $this->identifiers = [];
        }
        if (array_key_exists($key, $this->identifiers)) {
            $identifier = $this->identifiers[$key];
        } else {
            $qb = $this->connection->createQueryBuilder()
                ->select('value.value')
                ->from('value', 'value')
                // An identifier is always literal: it identifies a resource
                // inside the base. It can't be an external uri or a linked
                // resource.
                ->where('value.type = "literal"')
                ->andWhere('value.property_id = :property_id')
                ->setParameter('property_id', $this->options[$resourceType]['property'])
                ->andWhere('value.resource_id = :resource_id')
                ->setParameter('resource_id', $resourceId)
                ->addOrderBy('value.id', 'ASC')
                ->setMaxResults(1);
            if ($lengthPrefix) {
                $qb
                    ->andWhere('value.value LIKE :prefix')
                    ->setParameter('prefix', addcslashes($prefix, '%_') . '%');
            }
            $identifier = $this->connection->executeQuery($qb->getSQL(), $qb->getParameters())->fetchOne();
            $this->identifiers[$key] = $identifier;
        }

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
}
