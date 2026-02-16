<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use CleanUrl\ResourceNameTrait;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;

class GetIdentifiersFromResources extends AbstractHelper
{
    use ResourceNameTrait;
    /**
     * Get identifiers from resources.
     *
     * @uses \CleanUrl\View\Helper\GetIdentifiersFromResourcesOfType
     *
     * @param array|AbstractResourceEntityRepresentation|Resource|int $resources
     *   A list of resources as object or as array of ids. If object, it should
     *   be a resource or a representation.
     * @param string|array $resourceName The resource type of the resources when
     *   resources are integer. May be multiple.
     * @return array|string|null List of strings with id as key and identifier
     *   as value. Duplicates are not returned. If a single resource is
     *   provided, return a single string. Order is not kept.
     */
    public function __invoke($resources, $resourceName = null)
    {
        $isSingle = !is_array($resources);

        if (empty($resourceName)) {
            $resourceClasses = [Media::class, Item::class, ItemSet::class];
        } elseif (is_string($resourceName)) {
            $resourceClass = $this->convertNameToResourceClass($resourceName);
            if (empty($resourceClass)) {
                return $isSingle ? null : [];
            }
            $resourceClasses = [$resourceClass];
        } else {
            $resourceClasses = array_filter(array_map([$this, 'convertNameToResourceClass'], $resourceName));
            if (empty($resourceClasses)) {
                return $isSingle ? null : [];
            }
        }

        $resources = $isSingle ? [$resources] : array_filter($resources);

        /** @var \CleanUrl\View\Helper\GetIdentifiersFromResourcesOfType $getIdentifiersFromResourcesOfType */
        $getIdentifiersFromResourcesOfType = $this->view->plugin('getIdentifiersFromResourcesOfType');
        foreach ($resourceClasses as $resourceClass) {
            $result[$resourceClass] = $getIdentifiersFromResourcesOfType($resources, $resourceClass);
        }

        $result = array_replace(...array_values($result));
        if (!count($result)) {
            return $isSingle ? null : [];
        }
        return $isSingle ? reset($result) : $result;
    }

}
