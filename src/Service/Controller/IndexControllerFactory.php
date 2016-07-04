<?php

namespace CleanUrl\Service\Controller;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use CleanUrl\Controller\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $controllers)
    {
        $services = $controllers->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $apiAdapterManager = $services->get('Omeka\ApiAdapterManager');

        $controller = new IndexController;
        $controller->setConnection($connection);
        $controller->setApiAdapterManager($apiAdapterManager);

        return $controller;
    }
}
