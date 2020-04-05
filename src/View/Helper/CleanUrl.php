<?php
namespace CleanUrl\View\Helper;

use Traversable;
use Zend\Mvc\ModuleRouteListener;
use Zend\Router\RouteMatch;
use Zend\View\Exception;
use Zend\View\Helper\Url;

/**
 * Create a clean url if possible, else return the standard url.
 *
 * @internal The helper "Url" is overridden, so no factory can be used.
 *
 * @see Zend\View\Helper\Url
 */
class CleanUrl extends Url
{
    /**
     * Generates a clean or a standard url given the name of a route.
     *
     * @todo Assemble urls with clean url routes.
     *
     * @uses \Zend\View\Helper\Url
     * @see \Zend\Router\RouteInterface::assemble()
     * @param  string $name Name of the route
     * @param  array $params Parameters for the link
     * @param  array|Traversable $options Options for the route
     * @param  bool $reuseMatchedParams Whether to reuse matched parameters
     * @return string Url
     * @throws Exception\RuntimeException If no RouteStackInterface was
     *     provided
     * @throws Exception\RuntimeException If no RouteMatch was provided
     * @throws Exception\RuntimeException If RouteMatch didn't contain a
     *     matched route name
     * @throws Exception\InvalidArgumentException If the params object was not
     *     an array or Traversable object.
     */
    public function __invoke($name = null, $params = [], $options = [], $reuseMatchedParams = false)
    {
        $this->prepareRouter();

        /* Copy of Zend\View\Helper\Url::__invoke(). */

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
                $cleanUrl = $this->getCleanUrl('public', $params, $options);
                if ($cleanUrl) {
                    return $cleanUrl;
                }
                // Manage the case where the function url() is used with
                // different existing params.
                if (isset($params['resource'])) {
                    $params['controller'] = $this->controllerName($params['resource']);
                }
                $actions = [
                    'item-set-show' => 'show',
                    'route-item-set-media' => 'show',
                    'route-item-set-item-media' => 'show',
                    'route-item-set-item' => 'show',
                    'route-media' => 'show',
                    'route-item-media' => 'show',
                    'items-browse' => 'browse',
                    'route-item' => 'show',
                ];
                if (isset($params['action']) && isset($actions[$params['action']])) {
                    $params['action'] = $actions[$params['action']];
                }
                break;

            // The representation of item sets uses a specific route and there
            // is no show page.
            case 'site/item-set':
                if (!empty($params['item-set-id'])) {
                    $cleanUrl = $this->view->getResourceFullIdentifier(
                        ['type' => 'item_sets', 'id' => $params['item-set-id']],
                        isset($params['site-slug']) ? $params['site-slug'] : null,
                        true,
                        'public',
                        !empty($options['force_canonical'])
                    );
                    if ($cleanUrl) {
                        return $cleanUrl;
                    }
                }
                $params['controller'] = 'item';
                $params['action'] = 'browse';
                break;

            case 'site/resource':
                $controller = $this->getControllerBrowse($params);
                if ($controller) {
                    $params['controller'] = $controller;
                }
                break;

            case 'admin/id':
                if ($this->view->setting('cleanurl_use_admin')) {
                    $cleanUrl = $this->getCleanUrl('admin', $params, $options);
                    if ($cleanUrl) {
                        return $cleanUrl;
                    }
                }
                break;

            case 'admin/default':
                if ($this->view->setting('cleanurl_use_admin')) {
                    $controller = $this->getControllerBrowse($params);
                    if ($controller) {
                        $params['controller'] = $controller;
                    }
                }
                break;
        }

        // Use the standard url when no identifier exists (copy from Zend Url).
        return $this->router->assemble($params, $options);
    }

    /**
     * Get clean url path of a resource.
     *
     * @todo Replace by route assemble.
     *
     * @param string $context "public" or "admin"
     * @param array $params
     * @param array $options
     * @return string Identifier of the resource if any, else empty string.
     */
    protected function getCleanUrl($context, $params, $options)
    {
        if (isset($params['id'])
            && isset($params['controller'])
            && (in_array(
                $params['controller'],
                ['item-set', 'item', 'media', 'Omeka\Controller\Site\ItemSet', 'Omeka\Controller\Site\Item', 'Omeka\Controller\Site\Media']
            ))
            && (empty($params['action']) || $params['action'] === 'show')
        ) {
            $type = $this->controllerName($params['controller']);
            return $this->view->getResourceFullIdentifier(
                ['type' => $type, 'id' => $params['id']],
                isset($params['site-slug']) ? $params['site-slug'] : null,
                true,
                $context,
                !empty($options['force_canonical'])
            );
        }
        return '';
    }

    /**
     * @see \Zend\Mvc\Service\ViewHelperManagerFactory::injectOverrideFactories()
     */
    protected function prepareRouter()
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
     * Get the controller from the params, in all cases.
     *
     * @param array $params
     * @return string Controller value.
     */
    protected function getControllerBrowse($params)
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
     * @return string
     */
    protected function controllerName($name)
    {
        $controllers = [
            'item-set' => 'item-set',
            'item' => 'item',
            'media' => 'media',
            'item_sets' => 'item-set',
            'items' => 'item',
            'media' => 'media',
            'Omeka\Controller\Site\ItemSet' => 'item-set',
            'Omeka\Controller\Site\Item' => 'item',
            'Omeka\Controller\Site\Media' => 'media',
            \Omeka\Entity\ItemSet::class => 'item-set',
            \Omeka\Entity\Item::class => 'item',
            \Omeka\Entity\Media::class => 'media',
        ];
        return isset($controllers[$name])
            ? $controllers[$name]
            : null;
    }
}
