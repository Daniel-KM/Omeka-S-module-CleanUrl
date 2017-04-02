<?php

namespace CleanUrl\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CleanUrl\View\Helper\GetIdentifiersFromResources;

/**
 * Service factory for the api view helper.
 */
class GetIdentifiersFromResourcesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetIdentifiersFromResources(
            $services->get('Omeka\Connection')
        );
    }
}
