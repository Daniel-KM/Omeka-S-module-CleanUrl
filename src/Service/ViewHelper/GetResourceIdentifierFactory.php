<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetResourceIdentifier;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

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
