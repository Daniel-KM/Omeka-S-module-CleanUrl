<?php

namespace CleanUrl\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use CleanUrl\View\Helper\GetResourceFullIdentifier;

/**
 * Service factory for the api view helper.
 */
class GetResourceFullIdentifierFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetResourceFullIdentifier(
            $services->get('Application')
        );
    }
}
