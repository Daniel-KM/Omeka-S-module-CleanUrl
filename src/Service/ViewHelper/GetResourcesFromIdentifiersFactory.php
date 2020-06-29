<?php

namespace CleanUrl\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CleanUrl\View\Helper\GetResourcesFromIdentifiers;

/**
 * Service factory for the api view helper.
 */
class GetResourcesFromIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetResourcesFromIdentifiers(
            $services->get('Omeka\Connection'),
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
