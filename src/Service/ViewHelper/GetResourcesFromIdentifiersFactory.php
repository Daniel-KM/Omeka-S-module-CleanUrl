<?php

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetResourcesFromIdentifiers;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the api view helper.
 */
class GetResourcesFromIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        return new GetResourcesFromIdentifiers(
            $services->get('Omeka\Connection'),
            $this->supportAnyValue($services),
            (int) $settings->get('cleanurl_identifier_property'),
            $settings->get('cleanurl_identifier_prefix'),
            (bool) $settings->get('cleanurl_identifier_unspace'),
            (bool) $settings->get('cleanurl_identifier_case_sensitive'),
            (bool) $settings->get('cleanurl_identifier_prefix_part_of')
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
