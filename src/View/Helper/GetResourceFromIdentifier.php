<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class GetResourceFromIdentifier extends AbstractHelper
{
    /**
     * Get a resource from an identifier.
     *
     * @uses \CleanUrl\View\Helper\GetResourcesFromIdentifiers
     *
     * @param string $identifier The identifier of the resource to find.
     * @param string $resourceName Optional. Search a specific resource type if any.
     * @return \Omeka\Api\Representation\AbstractResourceRepresentation|null
     */
    public function __invoke($identifier, $resourceName = null): ?\Omeka\Api\Representation\AbstractResourceRepresentation
    {
        $result = $this->view->getResourcesFromIdentifiers([$identifier], $resourceName);
        return $result ? reset($result) : null;
    }
}
