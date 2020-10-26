<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\GetResourceTypeIdentifiers;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory for the api view helper.
 */
class GetResourceTypeIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $helper = new GetResourceTypeIdentifiers(
            $services->get('Omeka\Connection')
        );
        if (isset($options['propertyId'])) {
            $helper->setPropertyId($options['propertyId']);
            $helper->setPrefix($options['prefix']);
        }
        return $helper;
    }
}
