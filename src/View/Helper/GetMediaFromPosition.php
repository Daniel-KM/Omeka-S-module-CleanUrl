<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Doctrine\ORM\EntityManager;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\Item;
use Omeka\Entity\Media;

class GetMediaFromPosition extends AbstractHelper
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var MediaAdapter
     */
    protected $mediaAdapter;

    /**
     * @param EntityManager $entityManager
     * @param MediaAdapter $mediaAdapter
     */
    public function __construct(EntityManager $entityManager, MediaAdapter $mediaAdapter)
    {
        $this->entityManager = $entityManager;
        $this->mediaAdapter = $mediaAdapter;
    }

    /**
     * Get a media for an item with its position.
     *
     * @param ItemRepresentation|int $item
     * @param int $position
     * @return MediaRepresentation|int|null
     */
    public function __invoke($item, $position)
    {
        if (empty($item)) {
            return null;
        }

        if (is_numeric($item)) {
            $itemId = (int) $item;
            $type = 'numeric';
        } elseif ($item instanceof ItemRepresentation) {
            $itemId = $item->id();
            $type = 'representation';
        } elseif ($item instanceof Item) {
            $itemId = $item->getId();
            $type = 'resource';
        } else {
            throw new \Omeka\Api\Exception\InvalidArgumentException('Item should be an Item, an ItemRepresentation or an integer.'); // @translate
        }

        $media = $this->entityManager
            ->getRepository(Media::class)
            ->findOneBy(['item' => $itemId, 'position' => (int) $position], ['id' => 'ASC']);
        if (!$media) {
            return null;
        }

        // The visibility / rights is automatically because EntityManager is
        // used, not Connection.
        if ($type === 'numeric') {
            return $media->getId();
        }

        if ($type === 'resource') {
            return $media;
        }

        return $this->mediaAdapter->getRepresentation($media);
    }
}
