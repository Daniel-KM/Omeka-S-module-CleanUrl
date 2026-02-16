<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use CleanUrl\ResourceNameTrait;
use Doctrine\DBAL\Connection;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;

class GetResourceTypeIdentifiers extends AbstractHelper
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
                    ->select(
                        // $qb->expr()->trim($qb->expr()->substring('value.text', $lengthPrefix + 1))
                        '(TRIM(SUBSTR(value.value, ' . ($lengthPrefix + 1) . ')))'
                    );
            } else {
                $qb
                    ->select(
                        'value.value'
                    );
            }
            $qb
                ->andWhere('value.value LIKE :value_value')
                ->setParameter('value_value', addcslashes($prefix, '%_') . '%');
        } else {
            $qb
                ->select(
                    'value.value'
                );
        }

        $result = $this->connection->executeQuery($qb, $qb->getParameters())->fetchFirstColumn();

        if ($encode) {
            $keepSlash = $this->options[$resourceName]['keep_slash'];
            return array_map(function ($v) use ($keepSlash) {
                return $this->encode($v, $keepSlash);
            }, $result);
        }

        return $result;
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
