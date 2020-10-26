<?php
namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_PAGE;
use const CleanUrl\SLUG_SITE;
use const CleanUrl\SLUG_SITE_DEFAULT;
use const CleanUrl\SLUGS_CORE;
use const CleanUrl\SLUGS_RESERVED;
use const CleanUrl\SLUGS_SITE;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Traversable;
use Laminas\Router\Exception;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface as Request;

/**
 * Manage clean urls for all Omeka resources and pages according to the config.
 *
 * @todo Store all routes of all resources and pages in the database? Or use a regex route?
 *
 * Partially derived from route \Laminas\Router\Http\Regex and \Laminas\Router\Http\Segment.
 */
class CleanRoute implements RouteInterface
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $helpers;

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

    /**
     * @param \Omeka\Api\Manager $api
     * @param string $basePath
     * @param array $settings
     * @param array $helpers Needed only to assemble an url.
     */
    public function __construct($api = null, $basePath = '', array $settings = [], array $helpers = [])
    {
        $this->api = $api;
        $this->basePath = $basePath;
        $this->helpers = $helpers;
        $this->settings = $settings + [
            'default_site' => '',
            'main_path_full' => '',
            'main_path_full_encoded' => '',
            'main_short' => '',
            'main_short_path_full' => '',
            'main_short_path_full_encoded' => '',
            'main_short_path_full_regex' => '',
            'item_set_generic' => '',
            'item_generic' => '',
            'media_generic' => '',
            'item_allowed' => [],
            'media_allowed' => [],
            'admin_use' => false,
            'item_set_regex' => '',
            'regex' => [
                'main_path_full' => '',
                'item_set_generic' => '',
                'item_generic' => '',
                'media_generic' => '',
            ],
            'admin_reserved' => [],
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
            'api' => null,
            'base_path' => '',
            'settings' => [],
            'helpers' => [],
        ];

        return new static($options['api'], $options['base_path'], $options['settings'], $options['helpers']);
    }

    protected function prepareCleanRoutes()
    {
        $this->routes = [];

        $this->loopRoutes(false);

        $mainShort = $this->settings['main_short'];
        if (in_array($mainShort, ['main', 'main_sub', 'main_sub_sub'])) {
            // Set specific settings temporary to create routes.
            $savedSettings = $this->settings;
            $this->settings['main_path_full'] = $this->settings['main_short_path_full'];
            $this->settings['main_path_full_encoded'] = $this->settings['main_short_path_full_encoded'];
            $this->settings['regex']['main_path_full'] = $this->settings['main_short_path_full_regex'];
            $this->loopRoutes(true);
            // Reset to original settings.
            $this->settings = $savedSettings;
        }
    }

    protected function loopRoutes($short)
    {
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

        // Prepare only needed routes, but sometime status is not yet known.
        // $isUnknown = !$this->settings['is_public'] && !$this->settings['is_admin'];
        // $isPublic = $this->settings['is_public'] || $isUnknown;
        // $isAdmin = ($this->settings['admin_use'] && $this->settings['is_admin']) || $isUnknown;
        $isPublic = true;
        $isAdmin = $this->settings['admin_use'];

        $baseRoutes = [];
        if ($isPublic) {
            $baseRoutes['_public'] = [
                '/' . SLUG_SITE . ':site-slug/',
                '__SITE__',
                'CleanUrl\Controller\Site',
                null,
                '/' . SLUG_SITE . '(?P<site_slug>' . SLUGS_SITE . ')/',
                '/' . SLUG_SITE . '%site-slug%/',
            ];
        }
        if ($isAdmin) {
            $baseRoutes['_admin'] = [
                '/admin/',
                '__ADMIN__',
                'CleanUrl\Controller\Admin',
                null,
                '/admin/',
                '/admin/',
            ];
        }
        if ($isPublic && SLUG_MAIN_SITE) {
            $baseRoutes['_top'] = [
                '/',
                '__SITE__',
                'CleanUrl\Controller\Site',
                SLUG_MAIN_SITE,
                '/',
                '/',
            ];
        }

        $routeShort = $short ? '_short' : '';

        foreach ($baseRoutes as $routeExt => $array) {
            list($baseRoute, $space, $namespaceController, $siteSlug, $regexBaseRoute, $specBaseRoute) = $array;

            if ($hasItemSets) {
                // Match item set / item route for media.
                if (array_intersect(
                    ['item_set_item_media', 'item_set_item_full_media', 'item_set_item_media_full', 'item_set_item_full_media_full'],
                    $allowedForMedia
                )) {
                    $routeName = 'cleanurl_item_set_item_media' . $routeExt . $routeShort;
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
                    $routeName = 'cleanurl_item_set_item' . $routeExt . $routeShort;
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
                    $routeName = 'cleanurl_item_set_media' . $routeExt . $routeShort;
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
                $routeName = 'cleanurl_generic_item' . $routeExt . $routeShort;
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
                    $routeName = 'cleanurl_generic_items_browse' . $routeExt . $routeShort;
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
                            'action' => 'route-item-browse',
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
                $routeName = 'cleanurl_generic_item_media' . $routeExt . $routeShort;
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
                $routeName = 'cleanurl_generic_media' . $routeExt . $routeShort;
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
                $routeName = 'cleanurl_item_set' . $routeExt . $routeShort;
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
                        'action' => 'route-item-set',
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

        $uri = $request->getUri();
        $path = $uri->getPath();

        $matches = [];

        // The path offset is currently not managed: no action.
        // So the check all the remaining path. Routes will be reordered.
        $path = mb_substr($path, $pathOffset);

        // Check if it is a top url first.
        if (mb_stripos('|' . SLUGS_SITE . '|', '|' . trim(mb_substr($path, mb_strlen(SLUG_SITE)), '/') . '|') !== false) {
            return null;
        }

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
                $reserved = '|' . SLUGS_CORE . SLUGS_RESERVED . '|';
                foreach ($params as $key => $value) {
                    if (mb_stripos($reserved, '|' . $value . '|') !== false) {
                        continue 2;
                    }
                }

                if (isset($params['site_slug'])) {
                    $params['site-slug'] = $params['site_slug'];
                    unset($params['site_slug']);
                }

                // Check for page when there is no page prefix and no main path.
                $siteSlug = isset($params['site-slug']) ? $params['site-slug'] : SLUG_MAIN_SITE;
                $noPath = in_array($this->settings['main_short'], ['main', 'main_sub', 'main_sub_sub'])
                    || !mb_strlen($this->settings['main_path_full']);
                $checkPage = $siteSlug
                    && !mb_strlen(SLUG_PAGE)
                    && $noPath;
                if ($checkPage) {
                    $siteId = $this->api->read('sites', ['slug' => $siteSlug])->getContent()->id();
                    // Only check the first params, next ones are useless.
                    // The first may be a resource identifier or a slug.
                    foreach ($params as $key => $value) {
                        if (in_array($key, ['item_set_identifier', 'item_identifier', 'resource_identifier'])) {
                            break;
                        }
                    }
                    $identifier = $value;
                    // Api doesn't allow to search page by slug, so read it.
                    try {
                        $result = $this->api->read('site_pages', ['site' => $siteId, 'slug' => $identifier])->getContent();
                        // Use the default routing.
                        // TODO Redirect directly to the page.
                        return null;
                    } catch (\Omeka\Api\Exception\NotFoundException $e) {
                        // It is not a page.
                    }
                }

                // Check for first part when there is no main path, and it is
                // not in the reserved list checked above (unknown modules).
                if ($noPath && $this->settings['admin_reserved'] && strpos($routeName, '_admin')) {
                    $firstIdentifier = array_intersect(['item_set_identifier', 'item_identifier', 'resource_identifier'], array_keys($params));
                    if ($firstIdentifier
                        && in_array($params[reset($firstIdentifier)], $this->settings['admin_reserved'])
                    ) {
                        return null;
                    }
                }

                $matchedLength = mb_strlen($matches[0]);

                return new RouteMatch(array_merge($data['defaults'], $params), $matchedLength);
            }
        }

        return null;
    }

    public function assemble(array $params = [], array $options = [])
    {
        // TODO Rebuild the method getCleanUrl in order to use spec + params.
        return $this->getCleanUrl($params, $options);

        if (empty($options['route_name'])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The option "route_name" is required to assemble params to get a clean url.'); // @translate
        }

        $routeName = $options['route_name'];
        if (!isset($this->routes[$routeName])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The option "route_name" is not managed by module Clean Url.'); // @translate
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
     * @see \Laminas\Router\Http\Segment::encode()
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

    protected function getCleanUrl(array $params = [], array $options = [])
    {
        $params += [
            'controller' => null,
            'action' => null,
            'id' => null,
            'site-slug' => null,
            'resource' => null,
        ];

        $controller = $this->controllerName($params['controller']);
        if (!$params['id']
            || !$controller
            || ($params['action'] && $params['action'] !== 'show')
        ) {
            return '';
        }

        $resource = $params['resource'];
        if (!$resource) {
            $resourceNames = [
                'item-set' => 'item_sets',
                'item' => 'items',
                'media' => 'media',
            ];
            try {
                $resource = $this->api->read($resourceNames[$controller], ['id' => $params['id']])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return '';
            }
        }

        $options += [
            'base_path' => 'current',
            'main_path' => true,
            'force_canonical' => false,
            'format' => null,
        ];

        $getResourceIdentifier = $this->helpers['getResourceIdentifier'];
        $setting = $this->helpers['setting'];

        $siteSlug = $params['site-slug'];

        $absolute = $options['force_canonical'];
        $format = $options['format'];
        $withBasePath = $options['base_path'];
        $withMainPath = $options['main_path'];

        switch ($resource->resourceName()) {
            case 'item_sets':
                $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                $identifier = $getResourceIdentifier($resource, $urlEncode, true);
                if (!$identifier) {
                    return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                }

                $generic = $setting('cleanurl_item_set_generic');
                return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $generic . $identifier;

            case 'items':
                if (empty($format)) {
                    $format = $setting('cleanurl_item_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'items')) {
                    return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                }

                $urlEncode = !$setting('cleanurl_item_keep_raw');
                $skipPrefixItem = !strpos($format, 'item_full');
                $identifier = $getResourceIdentifier($resource, $urlEncode, $skipPrefixItem);
                if (!$identifier) {
                    return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                }

                switch ($format) {
                    case 'generic_item':
                    case 'generic_item_full':
                        $generic = $setting('cleanurl_item_generic');
                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $generic . $identifier;

                    case 'item_set_item':
                    case 'item_set_item_full':
                        $itemSets = $resource->itemSets();
                        if (empty($itemSets)) {
                            $format = $this->_getGenericFormat('item');
                            return $format
                                ? $this->getCleanUrl(['resource' => $resource] + $params, ['format' => $format] + $options)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $getResourceIdentifier($itemSet, $urlEncode, true);
                        if (!$itemSetIdentifier) {
                            $itemSetUndefined = $setting('cleanurl_item_item_set_undefined');
                            if ($itemSetUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemSetIdentifier = $itemSet->id();
                        }

                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $itemSetIdentifier . '/' . $identifier;

                    default:
                        break;
                }

                // Unmanaged format.
                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);

            case 'media':
                if (empty($format)) {
                    $format = $setting('cleanurl_media_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'media')) {
                    return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                }

                $urlEncode = !$setting('cleanurl_media_keep_raw');
                $skipPrefixMedia = !strpos($format, 'media_full');
                $identifier = $getResourceIdentifier($resource, $urlEncode, $skipPrefixMedia);
                $requireItemIdentifier = false;
                if (!$identifier) {
                    switch ($setting('cleanurl_media_media_undefined')) {
                        case 'id':
                            $identifier = $resource->id();
                            break;
                        case 'position':
                            // Don't use $item->media() to avoid a different
                            // position for public/private.
                            // $view->api() cannot set a response content.
                            $position = $this->api->read('media', ['id' => $resource->id()], [], ['responseContent' => 'resource'])->getContent()
                                ->getPosition();
                            if ($position) {
                                $requireItemIdentifier = true;
                                $identifier = sprintf($setting('cleanurl_media_format_position') ?: 'p%d', $position);
                                break;
                            }
                            // no break.
                        default:
                            return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                    }
                }

                switch ($format) {
                    case 'generic_media':
                    case 'generic_media_full':
                        if ($requireItemIdentifier) {
                            $allowedForMedia = $setting('cleanurl_media_allowed', []);
                            $result = array_intersect([
                                'generic_item_media',
                                'generic_item_full_media',
                                'generic_item_media_full',
                                'generic_item_full_media_full',
                            ], $allowedForMedia);
                            return $result
                            ? $this->getCleanUrl(['resource' => $resource] + $params, ['format' => reset($result)] + $options)
                            : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }
                        $generic = $setting('cleanurl_media_generic');
                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $generic . $identifier;

                    case 'generic_item_media':
                    case 'generic_item_full_media':
                    case 'generic_item_media_full':
                    case 'generic_item_full_media_full':
                        $item = $resource->item();
                        $urlEncode = !$setting('cleanurl_item_keep_raw');
                        $skipPrefixItem = !strpos($format, 'item_full');
                        $itemIdentifier = $getResourceIdentifier($item, $urlEncode, $skipPrefixItem);
                        if (empty($itemIdentifier)) {
                            $itemUndefined = $setting('cleanurl_media_item_undefined');
                            if ($itemUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemIdentifier = $item->id();
                        }

                        $generic = $setting('cleanurl_media_generic');
                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $generic . $itemIdentifier . '/' . $identifier;

                    case 'item_set_media':
                    case 'item_set_media_full':
                        if ($requireItemIdentifier) {
                            $allowedForMedia = $setting('cleanurl_media_allowed', []);
                            $result = array_intersect([
                                'item_set_item_media',
                                'item_set_item_full_media',
                                'item_set_item_media_full',
                                'item_set_item_full_media_full',
                            ], $allowedForMedia);
                            return $result
                                ? $this->getCleanUrl(['resource' => $resource] + $params, ['format' => reset($result)] + $options)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        if (empty($itemSets)) {
                            $format = $this->_getGenericFormat('media');
                            return $format
                                ? $this->getCleanUrl(['resource' => $resource] + $params, ['format' => reset($result)] + $options)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $getResourceIdentifier($itemSet, $urlEncode, true);
                        if (empty($itemSetIdentifier)) {
                            $itemSetUndefined = $setting('cleanurl_media_item_set_undefined');
                            if ($itemSetUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemSetIdentifier = $itemSet->id();
                        }
                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $itemSetIdentifier . '/' . $identifier;

                    case 'item_set_item_media':
                    case 'item_set_item_full_media':
                    case 'item_set_item_media_full':
                    case 'item_set_item_full_media_full':
                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        if (empty($itemSets)) {
                            $format = $this->_getGenericFormat('media');
                            return $format
                                ? $this->getCleanUrl(['resource' => $resource] + $params, ['format' => $format] + $options)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $getResourceIdentifier($itemSet, $urlEncode, true);
                        if (empty($itemSetIdentifier)) {
                            $itemSetUndefined = $setting('cleanurl_media_item_set_undefined');
                            if ($itemSetUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemSetIdentifier = $itemSet->id();
                        }

                        $urlEncode = !$setting('cleanurl_item_keep_raw');
                        $skipPrefixItem = !strpos($format, 'item_full');
                        $itemIdentifier = $getResourceIdentifier($item, $urlEncode, $skipPrefixItem);
                        if (!$itemIdentifier) {
                            $itemUndefined = $setting('cleanurl_media_item_undefined');
                            if ($itemUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemIdentifier = $item->id();
                        }
                        return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $itemSetIdentifier . '/' . $itemIdentifier . '/' . $identifier;

                    default:
                        break;
                }

                // Unmanaged format.
                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);

            default:
                break;
        }

        // This resource doesn't have a clean url.
        return '';
    }

    /**
     * Return beginning of the resource name if needed.
     *
     * @param string $siteSlug
     * @param bool $withBasePath
     * @param bool $withMainPath
     * @return string The string ends with '/'.
     */
    protected function _getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath)
    {
        if ($absolute) {
            $withBasePath = empty($withBasePath) ? 'current' : $withBasePath;
        }
        if ($withBasePath == 'current') {
            $withBasePath = $this->helpers['status']->isAdminRequest() ? 'admin' : 'public';
        }

        $basePath = $this->helpers['basePath'];
        $serverUrl = $this->helpers['serverUrl'];
        $setting = $this->helpers['setting'];

        switch ($withBasePath) {
            case 'public':
                if (strlen($siteSlug)) {
                    if (SLUG_MAIN_SITE && $siteSlug === SLUG_MAIN_SITE) {
                        $siteSlug = '';
                    }
                } else {
                    // // TODO Remove this code, since the site slug is defined in view helper CleanUrl.
                    // $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
                    // $siteSlug = $routeMatch->getParam('site-slug');
                    // if (SLUG_MAIN_SITE && $siteSlug === SLUG_MAIN_SITE) {
                    //     $siteSlug = '';
                    // }
                }
                if (mb_strlen($siteSlug)) {
                    // The check of "slugs_site" may avoid an issue when empty,
                    // after install or during/after upgrade.
                    $basePath = $basePath(
                        (mb_strlen(SLUGS_SITE) || mb_strlen(SLUG_SITE) ? SLUG_SITE : SLUG_SITE_DEFAULT) . $siteSlug
                    );
                } else {
                    $basePath = $basePath();
                }
                break;

            case 'admin':
                $basePath = $basePath('admin');
                break;

            default:
                $basePath = '';
        }

        $mainPath = $withMainPath ? $setting('cleanurl_main_path_full') : '';

        return ($absolute ? $serverUrl() : '') . $basePath . '/' . $mainPath;
    }

    /**
     * Check if a format is allowed for a resource type.
     *
     * @param string $format
     * @param string $resourceName
     * @return bool|null True if allowed, false if not, null if no format.
     */
    protected function _isFormatAllowed($format, $resourceName)
    {
        if (empty($format)) {
            return null;
        }

        $setting = $this->helpers['setting'];
        switch ($resourceName) {
            case 'items':
                $allowedForItems = $setting('cleanurl_item_allowed', []);
                return in_array($format, $allowedForItems);

            case 'media':
                $allowedForMedia = $setting('cleanurl_media_allowed', []);
                return in_array($format, $allowedForMedia);

            default:
                return null;
        }
    }

    /**
     * Return the generic format, if exists, for items or media.
     *
     * @param string $resourceName
     * @return string|null
     */
    protected function _getGenericFormat($resourceName)
    {
        $setting = $this->helpers['setting'];
        switch ($resourceName) {
            case 'items':
                $allowedForItems = $setting('cleanurl_item_allowed', []);
                $result = array_intersect([
                    'generic_item',
                    'generic_item_full',
                ], $allowedForItems);
                return $result
                    ? reset($result)
                    : null;

            case 'media':
                $allowedForMedia = $setting('cleanurl_media_allowed', []);
                $result = array_intersect([
                    // With item first and short first.
                    'generic_item_media',
                    'generic_item_full_media',
                    'generic_item_media_full',
                    'generic_item_full_media_full',
                    'generic_media',
                    'generic_media_full',
                ], $allowedForMedia);
                return $result
                    ? reset($result)
                    : null;

            default:
                return null;
        }
    }

    /**
     * Get an identifier when there is no identifier.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $siteSlug
     * @param bool $absolute
     * @param string $withBasePath
     * @param string $withMainPath
     * @throws \Omeka\Mvc\Exception\RuntimeException
     * @return string
     */
    protected function urlNoIdentifier(AbstractResourceEntityRepresentation $resource, $siteSlug, $absolute, $withBasePath, $withMainPath)
    {
        $setting = $this->helpers['setting'];
        switch ($setting('cleanurl_identifier_undefined')) {
            case 'main_generic':
                $genericKeys = [
                    'item' => 'cleanurl_item_generic',
                    'item-set' => 'cleanurl_item_set_generic',
                    'media' => 'cleanurl_media_generic',
                ];
                return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, $withMainPath) . $genericKeys[$resource->getControllerName()] . $resource->id();
            case 'generic':
                $genericKeys = [
                    'item' => 'cleanurl_item_generic',
                    'item-set' => 'cleanurl_item_set_generic',
                    'media' => 'cleanurl_media_generic',
                ];
                return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, false) . $genericKeys[$resource->getControllerName()] . $resource->id();
            case 'exception':
                if (!$this->helpers['status']->isAdminRequest()) {
                    $message = new \Omeka\Stdlib\Message('The "%1$s" #%2$d has no normalized identifier.', $resource->getControllerName(), $resource->id()); // @translate
                    throw new \Omeka\Mvc\Exception\RuntimeException($message);
                }
                // no break.
            case 'default':
            default:
                return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, false) . $resource->getControllerName() . '/' . $resource->id();
        }
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
