<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetIdentifiersFromResourcesOfType;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GetIdentifiersFromResourcesOfTypeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $optionsResources = [];
        $settings = $services->get('Omeka\Settings');
        foreach (['item_set' => 'item_sets', 'item' => 'items', 'media' => 'media'] as $resourceType => $resourceName) {
            $optionsResources[$resourceName] = $settings->get('cleanurl_' . $resourceType);
        }
        return new GetIdentifiersFromResourcesOfType(
            $services->get('Omeka\Connection'),
            $optionsResources
        );
    }
}
