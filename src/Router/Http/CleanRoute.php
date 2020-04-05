<?php
namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_SITE;
use const CleanUrl\SLUGS_CORE;
use const CleanUrl\SLUGS_RESERVED;
use const CleanUrl\SLUGS_SITE;

use Traversable;
use Zend\Router\Exception;
use Zend\Router\Http\RouteInterface;
use Zend\Router\Http\RouteMatch;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\RequestInterface as Request;

/**
 * Manage clean urls for all Omeka resources and pages according to the config.
 *
 * @todo Store all routes of all resources and pages in the database? Or use a regex route?
 *
 * Partially derived from route \Zend\Router\Http\Regex and \Zend\Router\Http\Segment.
 */
class CleanRoute implements RouteInterface
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $settings;

    /**
     * List of routes.
     *
     * Each route is a segment route that contains keys "route", "constraints",
     * "defaults", "parts", "regex", "paramMap" and optionaly "translationKeys".
     *
     * @var array
     */
    protected $routes = [];

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    public function __construct($basePath = '', array $settings = [])
    {
        $this->basePath = $basePath;
        $this->settings = $settings + [
            'main_path_full' => null,
            'main_path_full_encoded' => null,
            'item_set_generic' => null,
            'item_generic' => null,
            'media_generic' => null,
            'item_allowed' => null,
            'media_allowed' => null,
            'admin_use' => null,
            'item_set_regex' => null,
            'regex' => null,
        ];
        $this->prepareCleanRoutes();
    }

    public static function factory($options = [])
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        $options += [
            'base_path' => '',
            'settings' => [],
        ];

        return new static($options['base_path'], $options['settings']);
    }

    protected function prepareCleanRoutes()
    {
        $this->routes = [];

        $mainPathFull = $this->settings['main_path_full'];
        $mainPathFullEncoded = $this->settings['main_path_full_encoded'];

        $genericItemSet = $this->settings['item_set_generic'];
        $genericItem = $this->settings['item_generic'];
        $genericMedia = $this->settings['media_generic'];

        $allowedForItems = $this->settings['item_allowed'];
        $allowedForMedia = $this->settings['media_allowed'];

        // TODO Check if the item set regex is still needed, since a check is done in controller. Quicker?
        $regexItemSets = $this->settings['item_set_regex'];
        $hasItemSets = (bool) mb_strlen($regexItemSets);
        $regexItemSets = '(?P<item_set_identifier>' . $regexItemSets . ')';
        $regexItemSetsResource = '(?P<resource_identifier>' . $regexItemSets . ')';

        $regex = $this->settings['regex'];
        $regexResourceIdentifier = '(?P<resource_identifier>[^/]+)';
        // $regexItemSetIdentifier = '(?P<item_set_identifier>[^/]+)';
        $regexItemIdentifier = '(?P<item_identifier>[^/]+)';
        // $regexMediaIdentifier = '(?P<media_identifier>[^/]+)';

        $baseRoutes = [];
        $baseRoutes['_public'] = [
            '/' . SLUG_SITE . ':site-slug/',
            '__SITE__',
            'CleanUrl\Controller\Site',
            null,
            '/' . SLUG_SITE . '(?P<site_slug>' . SLUGS_SITE . ')/',
            '/' . SLUG_SITE . '/%site-slug%/',
        ];
        if ($this->settings['admin_use']) {
            $baseRoutes['_admin'] = [
                '/admin/',
                '__ADMIN__',
                'CleanUrl\Controller\Admin',
                null,
                '/admin/',
                '/admin/',
            ];
        }
        if (SLUG_MAIN_SITE) {
            $baseRoutes['_top'] = [
                '/',
                '__SITE__',
                'CleanUrl\Controller\Site',
                SLUG_MAIN_SITE,
                '/',
                '/',
            ];
        }

        foreach ($baseRoutes as $routeExt => $array) {
            list($baseRoute, $space, $namespaceController, $siteSlug, $regexBaseRoute, $specBaseRoute) = $array;

            if ($hasItemSets) {
                // Match item set / item route for media.
                if (array_intersect(
                    ['item_set_item_media', 'item_set_item_full_media', 'item_set_item_media_full', 'item_set_item_full_media_full'],
                    $allowedForMedia
                )) {
                    $routeName = 'cleanurl_item_set_item_media' . $routeExt;
                    $this->routes[$routeName] = [
                        'regex' => $regexBaseRoute
                            . $regex['main_path_full']
                            . $regex['item_set_generic']
                            // . $regexItemSetIdentifier . '/'
                            . $regexItemSets . '/'
                            . $regexItemIdentifier . '/'
                            . $regexResourceIdentifier,
                        'spec' => $specBaseRoute . $mainPathFullEncoded . $genericItemSet . '%item_set_identifier%/%item_identifier%/%resource_identifier%',
                        'defaults' => [
                            'route_name' => $routeName,
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item-set-item-media',
                            'site-slug' => $siteSlug,
                        ],
                    ];
                }

                // Match item set route for items.
                if (array_intersect(
                    ['item_set_item', 'item_set_item_full'],
                    $allowedForItems
                )) {
                    $routeName = 'cleanurl_item_set_item' . $routeExt;
                    $this->routes[$routeName] = [
                        'regex' => $regexBaseRoute
                            . $regex['main_path_full']
                            . $regex['item_set_generic']
                            // . $regexItemSetIdentifier . '/'
                            . $regexItemSets . '/'
                            . $regexResourceIdentifier,
                        'spec' => $specBaseRoute . $mainPathFullEncoded . $genericItemSet . '%item_set_identifier%/%resource_identifier%',
                        'defaults' => [
                            'route_name' => $routeName,
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item-set-item',
                            'site-slug' => $siteSlug,
                        ],
                    ];
                }

                // This clean url is same than the one above.
                // Match item set route for media.
                if (array_intersect(
                    ['item_set_media', 'item_set_media_full'],
                    $allowedForMedia
                )) {
                    $routeName = 'cleanurl_item_set_media' . $routeExt;
                    $this->routes[$routeName] = [
                        'regex' => $regexBaseRoute
                            . $regex['main_path_full']
                            . $regex['item_set_generic']
                            // . $regexItemSetIdentifier . '/'
                            . $regexItemSets . '/'
                            . $regexResourceIdentifier,
                        'spec' => $specBaseRoute . $mainPathFullEncoded . $genericItemSet . '%item_set_identifier%/%resource_identifier%',
                        'defaults' => [
                            'route_name' => $routeName,
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item-set-media',
                            'site-slug' => $siteSlug,
                        ],
                    ];
                }
            }

            // Match generic route for items.
            if (array_intersect(
                ['generic_item', 'generic_item_full'],
                $allowedForItems
            )) {
                $routeName = 'cleanurl_generic_item' . $routeExt;
                $this->routes[$routeName] = [
                    'regex' => $regexBaseRoute
                        . $regex['main_path_full']
                        . $regex['item_generic']
                        . $regexResourceIdentifier,
                    'spec' => $specBaseRoute . $mainPathFullEncoded . $genericItem . '%resource_identifier%',
                    'defaults' => [
                        'route_name' => $routeName,
                        '__NAMESPACE__' => $namespaceController,
                        $space => true,
                        'controller' => 'CleanUrlController',
                        'action' => 'route-item',
                        'item_set_id' => null,
                        'site-slug' => $siteSlug,
                    ],
                ];

                $route = $baseRoute . $mainPathFull . rtrim($genericItem, '/');
                if ($route !== '/' && $route !== $baseRoute) {
                    $routeName = 'cleanurl_generic_items_browse' . $routeExt;
                    $this->routes[$routeName] = [
                        'regex' => $regexBaseRoute
                            . $regex['main_path_full']
                            . rtrim($regex['item_generic'], '\/'),
                        'spec' => $specBaseRoute . $mainPathFullEncoded . rtrim($genericItem, '/'),
                        'defaults' => [
                            'route_name' => $routeName,
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'items-browse',
                            'site-slug' => $siteSlug,
                        ],
                    ];
                }
            }

            // Match generic / item route for media.
            if (array_intersect(
                ['generic_item_media', 'generic_item_full_media', 'generic_item_media_full', 'generic_item_full_media_full'],
                $allowedForMedia
            )) {
                $routeName = 'cleanurl_generic_item_media' . $routeExt;
                $this->routes[$routeName] = [
                    'regex' => $regexBaseRoute
                        . $regex['main_path_full']
                        . $regex['media_generic']
                        . $regexItemIdentifier . '/'
                        . $regexResourceIdentifier,
                    'spec' => $specBaseRoute . $mainPathFullEncoded . $genericMedia . '%item_identifier%/%resource_identifier%',
                    'defaults' => [
                        'route_name' => $routeName,
                        '__NAMESPACE__' => $namespaceController,
                        $space => true,
                        'controller' => 'CleanUrlController',
                        'action' => 'route-item-media',
                        'item_set_id' => null,
                        'site-slug' => $siteSlug,
                    ],
                ];
            }

            // Match generic route for media.
            if (array_intersect(
                ['generic_media', 'generic_media_full'],
                $allowedForMedia
            )) {
                $routeName = 'cleanurl_generic_media' . $routeExt;
                $this->routes[$routeName] = [
                    'regex' => $regexBaseRoute
                        . $regex['main_path_full']
                        . $regex['media_generic']
                        . $regexResourceIdentifier,
                    'spec' => $specBaseRoute . $mainPathFullEncoded . $genericMedia . '%resource_identifier%',
                    'defaults' => [
                        'route_name' => $routeName,
                        '__NAMESPACE__' => $namespaceController,
                        $space => true,
                        'controller' => 'CleanUrlController',
                        'action' => 'route-media',
                        'item_set_id' => null,
                        'site-slug' => $siteSlug,
                    ],
                ];
            }

            if ($hasItemSets) {
                // Match item set route.
                // This clean url is the same when the generic path is the same.
                $routeName = 'cleanurl_item_set' . $routeExt;
                $this->routes[$routeName] = [
                    'regex' => $regexBaseRoute
                        . $regex['main_path_full']
                        . $regex['item_set_generic']
                        // . $regexItemSetIdentifier . '/'
                        . $regexItemSetsResource,
                    'spec' => $specBaseRoute . $mainPathFullEncoded . $genericItemSet . '%resource_identifier%',
                    'defaults' => [
                        'route_name' => $routeName,
                        '__NAMESPACE__' => $namespaceController,
                        $space => true,
                        'controller' => 'CleanUrlController',
                        'action' => 'item-set-show',
                        'site-slug' => $siteSlug,
                    ],
                ];
            }
        }
    }

    public function match(Request $request, $pathOffset = null)
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath();

        $matches = [];

        // The path offset is currently not managed: no action.
        // So the check all the remaining path. Routes will be reordered.
        $path = mb_substr($path, $pathOffset);

        foreach ($this->routes as $routeName => $data) {
            $regex = $this->routes[$routeName]['regex'];

            // if (is_null($pathOffset)) {
                $result = preg_match('(^' . $regex . '$)', $path, $matches);
            // } else {
            //     $result = preg_match('(\G' . $regex . ')', $path, $matches, null, $pathOffset);
            // }

            if ($result) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_numeric($key) || is_int($key) || $value === '') {
                        // unset($matches[$key]);
                    } else {
                        $params[$key] = rawurldecode($value);
                    }
                }

                // Check if the resource identifiers is a reserved word.
                // They are managed here currently for simplicity.
                foreach ($params as $key => $value) {
                    if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . $value . '|') !== false) {
                        continue 2;
                    }
                }

                $matchedLength = mb_strlen($matches[0]);

                if (isset($params['site_slug'])) {
                    $params['site-slug'] = $params['site_slug'];
                    unset($params['site_slug']);
                }

                return new RouteMatch(array_merge($data['defaults'], $params), $matchedLength);
            }
        }

        return null;
    }

    public function assemble(array $params = [], array $options = [])
    {
        if (empty($params['route_name'])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The param "route_name" is required to assemble params to get a clean url.'); // @translate
        }

        $routeName = $params['route_name'];
        if (!isset($this->routes[$routeName])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The param "route_name" is not managed by module Clean Url.'); // @translate
        }

        $url = $this->routes[$routeName]['spec'];
        $this->assembledParams = [];

        foreach ($params as $key => $value) {
            $spec = '%' . $key . '%';
            if (strpos($url, $spec) !== false) {
                $url = str_replace($spec, $this->encode($value), $url);
                $this->assembledParams[] = $key;
            }
        }

        return $url;
    }

    public function getAssembledParams()
    {
        return $this->assembledParams;
    }

    /**
     * Encode a path segment.
     *
     * @see \Zend\Router\Http\Segment::encode()
     *
     * @param  string $value
     * @return string
     */
    protected function encode($value)
    {
        $urlencodeCorrectionMap = [
            '%21' => "!", // sub-delims
            '%24' => "$", // sub-delims
            '%26' => "&", // sub-delims
            '%27' => "'", // sub-delims
            '%28' => "(", // sub-delims
            '%29' => ")", // sub-delims
            '%2A' => "*", // sub-delims
            '%2B' => "+", // sub-delims
            '%2C' => ",", // sub-delims
            // '%2D' => "-", // unreserved - not touched by rawurlencode
            // '%2E' => ".", // unreserved - not touched by rawurlencode
            '%3A' => ":", // pchar
            '%3B' => ";", // sub-delims
            '%3D' => "=", // sub-delims
            '%40' => "@", // pchar
            // '%5F' => "_", // unreserved - not touched by rawurlencode
            // '%7E' => "~", // unreserved - not touched by rawurlencode
        ];
        return strtr(rawurlencode($value), $urlencodeCorrectionMap);
    }
}
