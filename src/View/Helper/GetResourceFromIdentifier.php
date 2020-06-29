<?php

namespace CleanUrl\View\Helper;

use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\View\Helper
 */
class GetResourceFromIdentifier extends AbstractHelper
{
    /**
     * Get a resource from an identifier.
     *
     * @uses \CleanUrl\View\Helper\GetResourcesFromIdentifiers
     *
     * @param string $identifier The identifier of the resource to find.
     * @param bool $withPrefix Optional. If identifier begins with prefix.
     * @param string $resourceName Optional. Search a specific resource type if any.
     * @return \Omeka\Api\Representation\AbstractResourceRepresentation|null
     */
    public function __invoke($identifier, $withPrefix = false, $resourceName = null)
    {
        $result = $this->view->getResourcesFromIdentifiers([$identifier], $withPrefix, $resourceName);
        return $result ? reset($result) : null;
    }
}
