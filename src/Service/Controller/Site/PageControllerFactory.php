<?php declare(strict_types=1);

namespace CleanUrl\Service\Controller\Site;

use CleanUrl\Controller\Site\PageController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PageControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PageController(
            $services->get('Omeka\Site\ThemeManager')->getCurrentTheme()
        );
    }
}
