<?php declare(strict_types=1);

namespace CleanUrl\Service\ViewHelper;

use CleanUrl\View\Helper\CleanUrl;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Service factory for the CleanUrl view helper, that overrides "url".
 *
 * The services are injected here, so the helper does not have to fetch them
 * from the plugin manager, that is a deprecated service locator.
 */
class CleanUrlFactory implements FactoryInterface
{
    /**
     * Create and return the CleanUrl view helper.
     *
     * @return CleanUrl
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // The route match is not available when the helper is built, so the
        // application is injected and the match is fetched when needed.
        return new CleanUrl(
            $services->get('HttpRouter'),
            $services->get('Application'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\ApiManager')
        );
    }
}
