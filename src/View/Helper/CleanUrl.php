<?php
namespace CleanUrl\View\Helper;

use Traversable;
use Zend\Mvc\ModuleRouteListener;
use Zend\Router\RouteMatch;
use Zend\Router\RouteStackInterface;
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
     * @uses Zend\View\Helper\Url
     * @see Zend\Mvc\Router\RouteInterface::assemble()
     * @see Zend\Router\RouteInterface::assemble()
     * @param  string $name Name of the route
     * @param  array $params Parameters for the link
     * @param  array|Traversable $options Options for the route
     * @param  bool $reuseMatchedParams Whether to reuse matched parameters
     * @return string Url For the link href attribute
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
                break;

            // The representation of item sets uses a specific route and there
            // is no show page.
            case 'site/item-set':
                if (!empty($params['item-set-id'])) {
                    $cleanUrl = $this->view->getResourceFullIdentifier(
                        ['type' => 'item_sets', 'id' => $params['item-set-id']],
                        true,
                        'public',
                        !empty($options['force_canonical'])
                    );
                    if ($cleanUrl) {
                        return $cleanUrl;
                    }
                }
                break;

            // case 'admin/id':
            //     if ($this->view->setting('clean_url_use_admin')) {
            //         $cleanUrl = $this->getCleanUrl('admin', $params, $options);
            //         if ($cleanUrl) {
            //             return $cleanUrl;
            //         }
            //     }
            //     break;
        }

        // Use the standard url when no identifier exists (copy from Zend Url).
        return $this->router->assemble($params, $options);
    }

    /**
     * Get clean url path of a record.
     *
     * @param AbstractRecord $record
     * @param string|null $action
     * @return string|null  Identifier of the record, if any, else empty string.
     */
    protected function getCleanUrl($context, $params, $options)
    {
        if (isset($params['id'])
            && isset($params['controller'])
            && (in_array($params['controller'], ['item-set', 'item', 'media']))
            && (empty($params['action']) || $params['action'] == 'show')
        ) {
            return $this->view->getResourceFullIdentifier(
                ['type' => $params['controller'], 'id' => $params['id']],
                true,
                $context,
                !empty($options['force_canonical'])
            );
        }
    }

    /**
     * @see Zend\Mvc\Service\ViewHelperManagerFactory::injectOverrideFactories()
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
}
