<?php

namespace CleanUrl\Service\Controller\Site;

use CleanUrl\Controller\Site\IndexController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $connection = $services->get('Omeka\Connection');
        $apiAdapterManager = $services->get('Omeka\ApiAdapterManager');

        $controller = new IndexController;
        $controller->setConnection($connection);
        $controller->setApiAdapterManager($apiAdapterManager);

        return $controller;
    }
}
