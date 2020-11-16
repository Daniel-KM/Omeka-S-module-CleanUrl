<?php declare(strict_types=1);

namespace CleanUrl\View\Helper;

use Laminas\Mvc\ModuleRouteListener;
use Laminas\Router\RouteMatch;
use Laminas\View\Exception;
use Laminas\View\Helper\Url;
use Traversable;

/**
 * Create a clean url if possible, else return the standard url.
 *
 * Note: The helper "Url" is overridden and no factory is used currently.
 *
 * @see Laminas\View\Helper\Url
 */
class CleanUrl extends Url
{
    /**
     * @var \CleanUrl\Router\Http\CleanRoute
     */
    protected $cleanRoute;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * Generate a clean or a standard url given the name of a route (clean-url).
     *
     * {@inheritDoc}
     * @see \Laminas\View\Helper\Url::__invoke()
     */
    public function __invoke($name = null, $params = [], $options = [], $reuseMatchedParams = false)
    {
        // TODO Is the preparation of the router still needed?
        $this->prepareRouter();

        /* Copy of Laminas\View\Helper\Url::__invoke(). */

        if (null === $this->router) {
            throw new Exception\RuntimeException('No RouteStackInterface instance provided');
        }

        if (3 == func_num_args() && is_bool($options)) {
            $reuseMatchedParams = $options;
            $options = [];
        }

        if ($name === null) {
            if ($this->routeMatch === null) {
                throw new Exception\RuntimeException('No RouteMatch instance provided');
            }

            $name = $this->routeMatch->getMatchedRouteName();

            if ($name === null) {
                throw new Exception\RuntimeException('RouteMatch does not contain a matched route name');
            }
        }

        if (! is_array($params)) {
            if (! $params instanceof Traversable) {
                throw new Exception\InvalidArgumentException(
                    'Params is expected to be an array or a Traversable object'
                );
            }
            $params = iterator_to_array($params);
        }

        if ($reuseMatchedParams && $this->routeMatch !== null) {
            $routeMatchParams = $this->routeMatch->getParams();

            if (isset($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER])) {
                $routeMatchParams['controller'] = $routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER];
                unset($routeMatchParams[ModuleRouteListener::ORIGINAL_CONTROLLER]);
            }

            if (isset($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE])) {
                unset($routeMatchParams[ModuleRouteListener::MODULE_NAMESPACE]);
            }

            $params = array_merge($routeMatchParams, $params);
        }

        $options['name'] = $name;

        /* End of the copy. */

        // Check if there is a clean url for pages of resources.
        switch ($name) {
            case 'site/resource-id':
                if (empty($params['action']) || $params['action'] === 'show') {
                    $params = $this->appendSiteSlug($params);
                    $cleanOptions = $options;
                    $cleanOptions['route_name'] = $name;
                    $cleanOptions['name'] = 'clean-url';
                    $cleanUrl = $this->router->assemble($params, $cleanOptions);
                    if ($cleanUrl && $cleanUrl !== $this->getBasePath()) {
                        return $cleanUrl;
                    }
                }
                // TODO Check if it is still needed.
                // Manage the case where the function url() is used with
                // different existing params.
                if (isset($params['resource'])) {
                    $params['controller'] = $this->controllerName($params['resource']);
                }
                $actions = [
                    'route-item-browse' => 'browse',
                    'route-item' => 'show',
                    'route-item-set' => 'show',
                    'route-item-set-item' => 'show',
                    'route-item-set-item-media' => 'show',
                    'route-item-set-media' => 'show',
                    'route-media' => 'show',
                    'route-item-media' => 'show',
                ];
                if (isset($params['action']) && isset($actions[$params['action']])) {
                    $params['action'] = $actions[$params['action']];
                }
                break;

            // The representation of item sets uses a specific route and there
            // is no show page.
            case 'site/item-set':
                if (!empty($params['item-set-id'])) {
                    $params = $this->appendSiteSlug($params);
                    $cleanParams = $params;
                    $cleanParams['item_set_id'] = $params['item-set-id'];
                    $cleanParams['__CONTROLLER__'] = 'item-set';
                    $cleanOptions = $options;
                    $cleanOptions['route_name'] = $name;
                    $cleanOptions['name'] = 'clean-url';
                    $cleanUrl = $this->router->assemble($cleanParams, $cleanOptions);
                    if ($cleanUrl && $cleanUrl !== $this->getBasePath()) {
                        return $cleanUrl;
                    }
                }
                $params['controller'] = 'item';
                $params['action'] = 'browse';
                break;

            case 'site/resource':
                // TODO Check if it is still needed.
                $controller = $this->getControllerBrowse($params);
                if ($controller) {
                    $params['controller'] = $controller;
                }
                break;

            case 'admin/id':
                if ($this->view->setting('cleanurl_admin_use')
                    && (empty($params['action']) || $params['action'] === 'show')
                ) {
                    $cleanOptions = $options;
                    $cleanOptions['route_name'] = $name;
                    $cleanOptions['name'] = 'clean-url';
                    $cleanUrl = $this->router->assemble($params, $cleanOptions);
                    if ($cleanUrl && $cleanUrl !== $this->getBasePath()) {
                        return $cleanUrl;
                    }
                }
                break;

            case 'admin/default':
                if ($this->view->setting('cleanurl_admin_use')) {
                    $controller = $this->getControllerBrowse($params);
                    if ($controller) {
                        $params['controller'] = $controller;
                    }
                }
                break;

            default:
                break;
        }

        // Use the standard url when no identifier exists (copy of Laminas Url).
        return $this->router->assemble($params, $options);
    }

    /**
     * Append the site slug to the params for a url path when it is missing.
     *
     * @param array $params
     * @return array
     */
    protected function appendSiteSlug(array $params): array
    {
        if (empty($params['site-slug'])) {
            $params['site-slug'] = @$this->view->getHelperPluginManager()->getServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        return $params;
    }

    /**
     * @see \Laminas\Mvc\Service\ViewHelperManagerFactory::injectOverrideFactories()
     */
    protected function prepareRouter(): void
    {
        if (empty($this->router)) {
            $services = @$this->view->getHelperPluginManager()->getServiceLocator();
            $this->setRouter($services->get('HttpRouter'));
            $match = $services->get('Application')
                ->getMvcEvent()
                ->getRouteMatch();
            if ($match instanceof RouteMatch) {
                $this->setRouteMatch($match);
            }
        }
    }

    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        if (is_null($this->basePath)) {
            $this->basePath = $this->getView()->basePath();
        }
        return $this->basePath;
    }

    /**
     * Get the controller from the params, in all cases.
     *
     * @todo Check if it is still needed.
     *
     * @param array $params
     * @return string Controller value.
     */
    protected function getControllerBrowse(array $params): string
    {
        if (!isset($params['controller'])
            || (!empty($params['action']) && $params['action'] !== 'browse')
        ) {
            return '';
        }

        $controller = $this->controllerName($params['controller']);
        if (in_array($controller, ['item-set', 'item', 'media'])) {
            return $controller;
        }

        if ($params['controller'] === 'CleanUrlController'
            && !empty($params['resource_identifier'])
        ) {
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
            $resource = $this->view->getResourceFromIdentifier($params['resource_identifier']);
            if (!$resource) {
                $resource = $this->view->api()->read('resources', $params['resource_identifier'])->getContent();
            }
            if ($resource) {
                return $resource->getControllerName();
            }
        }

        return '';
    }

    /**
     * Normalize the controller name.
     *
     * @param string $name
     * @return string|null
     */
    protected function controllerName(string $name): ?string
    {
        $controllers = [
            'item-set' => 'item-set',
            'item' => 'item',
            'media' => 'media',
            'item_sets' => 'item-set',
            'items' => 'item',
            'media' => 'media',
            'Omeka\Controller\Admin\ItemSet' => 'item-set',
            'Omeka\Controller\Admin\Item' => 'item',
            'Omeka\Controller\Admin\Media' => 'media',
            'Omeka\Controller\Site\ItemSet' => 'item-set',
            'Omeka\Controller\Site\Item' => 'item',
            'Omeka\Controller\Site\Media' => 'media',
            \Omeka\Entity\ItemSet::class => 'item-set',
            \Omeka\Entity\Item::class => 'item',
            \Omeka\Entity\Media::class => 'media',
        ];
        return $controllers[$name] ?? null;
    }
}
