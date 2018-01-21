<?php

namespace CleanUrl\Service\Controller\Site;

use CleanUrl\Controller\Site\CleanUrlController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CleanUrlControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CleanUrlController(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\ApiAdapterManager')
        );
    }
}
