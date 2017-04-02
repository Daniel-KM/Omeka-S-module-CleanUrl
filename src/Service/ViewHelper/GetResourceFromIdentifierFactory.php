<?php

namespace CleanUrl\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CleanUrl\View\Helper\GetResourceFromIdentifier;

/**
 * Service factory for the api view helper.
 */
class GetResourceFromIdentifierFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetResourceFromIdentifier(
            $services->get('Omeka\Connection')
        );
    }
}
