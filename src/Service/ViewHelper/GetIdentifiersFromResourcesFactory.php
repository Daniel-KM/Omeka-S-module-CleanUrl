<?php

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetIdentifiersFromResources;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the api view helper.
 */
class GetIdentifiersFromResourcesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $prefix = $settings->get('cleanurl_identifier_prefix');
        return new GetIdentifiersFromResources(
            $services->get('Omeka\Connection'),
            (int) $settings->get('cleanurl_identifier_property'),
            $prefix,
            mb_strlen($prefix) && (bool) $settings->get('cleanurl_identifier_prefix_part_of')
        );
    }
}
