<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetResourcesFromIdentifiers;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GetResourcesFromIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $optionsResources = [];
        $settings = $services->get('Omeka\Settings');
        foreach (['item_set' => 'item_sets', 'item' => 'items', 'media' => 'media'] as $resourceType => $resourceName) {
            $optionsResources[$resourceName] = $settings->get('cleanurl_' . $resourceType);
        }
        $optionsResources['resources'] = $optionsResources['items'];
        return new GetResourcesFromIdentifiers(
            $services->get('Omeka\Connection'),
            $optionsResources,
            $this->supportAnyValue($services)
        );
    }

    protected function supportAnyValue(ContainerInterface $services)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // To do a request is the simpler way to check if the flag ONLY_FULL_GROUP_BY
        // is set in any databases, systems and versions and that it can be
        // bypassed by any_value().
        $sql = 'SELECT ANY_VALUE(id) FROM user LIMIT 1;';
        try {
            $connection->query($sql)->fetchColumn();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
