<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetMediaFromPosition;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GetMediaFromPositionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GetMediaFromPosition(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager')->get('media')
        );
    }
}
