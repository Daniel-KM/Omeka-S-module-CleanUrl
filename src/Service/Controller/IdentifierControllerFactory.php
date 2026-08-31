<?php declare(strict_types=1);

namespace CleanUrl\Service\Controller;

use CleanUrl\Controller\IdentifierController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class IdentifierControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new IdentifierController(
            $services->get('ViewRenderer'),
            $services->get('Omeka\ApiManager')
        );
    }
}
