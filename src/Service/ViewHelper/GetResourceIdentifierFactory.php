<?php

namespace CleanUrl\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CleanUrl\View\Helper\GetResourceIdentifier;

/**
 * Service factory for the api view helper.
 */
class GetResourceIdentifierFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetResourceIdentifier(
            $services->get('Omeka\Connection')
        );
    }
}
