<?php
namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_SITE;

use Traversable;
use Zend\I18n\Translator\TranslatorInterface as Translator;
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
 * Partially derived from route \Zend\Router\Http\Segment.
 * Each route is not made regex on construct, but on first check.
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
     * @var array
     */
    protected $defaults;

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
     * Cache for the encode output.
     *
     * @var array
     */
    protected static $cacheEncode = [];

    /**
     * Map of allowed special chars in path segments.
     *
     * http://tools.ietf.org/html/rfc3986#appendix-A
     * segement      = *pchar
     * pchar         = unreserved / pct-encoded / sub-delims / ":" / "@"
     * unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
     * sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
     *               / "*" / "+" / "," / ";" / "="
     *
     * @var array
     */
    protected static $urlencodeCorrectionMap = [
        '%21' => "!", // sub-delims
        '%24' => "$", // sub-delims
        '%26' => "&", // sub-delims
        '%27' => "'", // sub-delims
        '%28' => "(", // sub-delims
        '%29' => ")", // sub-delims
        '%2A' => "*", // sub-delims
        '%2B' => "+", // sub-delims
        '%2C' => ",", // sub-delims
//      '%2D' => "-", // unreserved - not touched by rawurlencode
//      '%2E' => ".", // unreserved - not touched by rawurlencode
        '%3A' => ":", // pchar
        '%3B' => ";", // sub-delims
        '%3D' => "=", // sub-delims
        '%40' => "@", // pchar
//      '%5F' => "_", // unreserved - not touched by rawurlencode
//      '%7E' => "~", // unreserved - not touched by rawurlencode
    ];

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    public function __construct($basePath = '', array $settings = [], array $defaults = [])
    {
        $this->basePath = $basePath;
        $this->settings = $settings + [
            'main_path' => null,
            'item_set_generic' => null,
            'item_generic' => null,
            'media_generic' => null,
            'item_allowed' => null,
            'media_allowed' => null,
            'use_admin' => null,
            'item_set_regex' => null,
        ];
        $this->defaults = $defaults;
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
            'defaults' => [],
        ];

        return new static($options['base_path'], $options['settings'], $options['defaults']);
    }

    protected function prepareCleanRoutes()
    {
        $this->routes = [];

        $mainPath = $this->settings['main_path'];

        $itemSetGeneric = $this->settings['item_set_generic'];
        $itemGeneric = $this->settings['item_generic'];
        $mediaGeneric = $this->settings['media_generic'];

        $allowedForItems = $this->settings['item_allowed'];
        $allowedForMedia = $this->settings['media_allowed'];

        $itemSetsRegex = $this->settings['item_set_regex'];

        $baseRoutes = [];
        $baseRoutes['_public'] = [
            '/' . SLUG_SITE . ':site-slug/',
            '__SITE__',
            'CleanUrl\Controller\Site',
            null,
        ];
        if ($this->settings['use_admin']) {
            $baseRoutes['_admin'] = [
                '/admin/',
                '__ADMIN__',
                'CleanUrl\Controller\Admin',
                null,
            ];
        }
        if (SLUG_MAIN_SITE) {
            $baseRoutes['_top'] = [
                '/',
                '__SITE__',
                'CleanUrl\Controller\Site',
                SLUG_MAIN_SITE,
            ];
        }

        foreach ($baseRoutes as $routeExt => $array) {
            list($baseRoute, $space, $namespaceController, $siteSlug) = $array;

            // TODO Move some item set routes under item routes.
            if (!empty($itemSetsRegex)) {
                $route = $baseRoute . $mainPath . $itemSetGeneric;

                // Match item set / item route for media.
                if (in_array('item_set_item_media', $allowedForMedia)) {
                    $routeName = 'cleanurl_item_set_item_media' . $routeExt;
                    $this->routes[$routeName] = [
                        'route' => $route . ':item_set_identifier/:item_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
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
                if (in_array('item_set_item', $allowedForItems)) {
                    $routeName = 'cleanurl_item_set_item' . $routeExt;
                    $this->routes[$routeName] = [
                        'route' => $route . ':item_set_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
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

                // This clean url is same than the one above, but it's a choice
                // of the admin.
                // Match item set route for media.
                if (in_array('item_set_media', $allowedForMedia)) {
                    $routeName = 'cleanurl_item_set_media' . $routeExt;
                    $this->routes[$routeName] = [
                        'route' => $route . ':item_set_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
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

                // Match item set route.
                $routeName = 'cleanurl_item_set' . $routeExt;
                $this->routes[$routeName] = [
                    'route' => $route . ':resource_identifier',
                    'constraints' => [
                        'resource_identifier' => $itemSetsRegex,
                    ],
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

            // Match generic route for items.
            if (in_array('generic_item', $allowedForItems)) {
                $route = $baseRoute . $mainPath . $itemGeneric;
                $routeName = 'cleanurl_generic_item' . $routeExt;
                $this->routes[$routeName] = [
                    'route' => $route . ':resource_identifier',
                    'constraints' => [
                    ],
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

                $route = $baseRoute . $mainPath . trim($itemGeneric, '/');
                $routeName = 'cleanurl_generic_items_browse' . $routeExt;
                $this->routes[$routeName] = [
                    'route' => $route,
                    'constraints' => [
                    ],
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

            // Match generic / item route for media.
            if (in_array('generic_item_media', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $routeName = 'cleanurl_generic_item_media' . $routeExt;
                $this->routes[$routeName] = [
                    'route' => $route . ':item_identifier/:resource_identifier',
                    'constraints' => [
                    ],
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
            if (in_array('generic_media', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $routeName = 'cleanurl_generic_media' . $routeExt;
                $this->routes[$routeName] = [
                    'route' => $route . ':resource_identifier',
                    'constraints' => [
                    ],
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
        }
    }

    protected function prepareRoute($routeName)
    {
        $this->routes[$routeName]['parts'] = $this->parseRouteDefinition($this->routes[$routeName]['route']);
        $this->routes[$routeName]['regex'] = $this->buildRegex($this->routes[$routeName]['parts'], $this->routes[$routeName]['constraints'], $routeName);
    }

    /* Adapted from \Zend\Router\Http\Segment */

    /**
     * Parse a route definition.
     *
     * @param  string $def
     * @return array
     * @throws Exception\RuntimeException
     */
    protected function parseRouteDefinition($def)
    {
        $currentPos = 0;
        $length = strlen($def);
        $parts = [];
        $levelParts = [&$parts];
        $level = 0;

        $matches = [];
        while ($currentPos < $length) {
            preg_match('(\G(?P<literal>[^:{\[\]]*)(?P<token>[:{\[\]]|$))', $def, $matches, 0, $currentPos);

            $currentPos += strlen($matches[0]);

            if (! empty($matches['literal'])) {
                $levelParts[$level][] = ['literal', $matches['literal']];
            }

            if ($matches['token'] === ':') {
                if (! preg_match(
                    '(\G(?P<name>[^:/{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)',
                    $def,
                    $matches,
                    0,
                    $currentPos
                )) {
                    throw new Exception\RuntimeException('Found empty parameter name');
                }

                $levelParts[$level][] = [
                    'parameter',
                    $matches['name'],
                    isset($matches['delimiters']) ? $matches['delimiters'] : null,
                ];

                $currentPos += strlen($matches[0]);
            } elseif ($matches['token'] === '{') {
                if (! preg_match('(\G(?P<literal>[^}]+)\})', $def, $matches, 0, $currentPos)) {
                    throw new Exception\RuntimeException('Translated literal missing closing bracket');
                }

                $currentPos += strlen($matches[0]);

                $levelParts[$level][] = ['translated-literal', $matches['literal']];
            } elseif ($matches['token'] === '[') {
                $levelParts[$level][] = ['optional', []];
                $levelParts[$level + 1] = &$levelParts[$level][count($levelParts[$level]) - 1][1];

                $level++;
            } elseif ($matches['token'] === ']') {
                unset($levelParts[$level]);
                $level--;

                if ($level < 0) {
                    throw new Exception\RuntimeException('Found closing bracket without matching opening bracket');
                }
            } else {
                break;
            }
        }

        if ($level > 0) {
            throw new Exception\RuntimeException('Found unbalanced brackets');
        }

        return $parts;
    }

    /**
     * Build the matching regex from parsed parts.
     *
     * @param  array   $parts
     * @param  array   $constraints
     * @param  string   $routeName
     * @param  int $groupIndex
     * @return string
     */
    protected function buildRegex($parts, array $constraints, $routeName, &$groupIndex = 1)
    {
        $regex = '';

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $regex .= preg_quote($part[1]);
                    break;

                case 'parameter':
                    $groupName = '?P<param' . $groupIndex . '>';

                    if (isset($constraints[$part[1]])) {
                        $regex .= '(' . $groupName . $constraints[$part[1]] . ')';
                    } elseif ($part[2] === null) {
                        $regex .= '(' . $groupName . '[^/]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->routes[$routeName]['paramMap']['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->buildRegex($part[1], $constraints, $routeName, $groupIndex) . ')?';
                    break;

                case 'translated-literal':
                    $regex .= '#' . $part[1] . '#';
                    $this->routes[$routeName]['translationKeys'][] = $part[1];
                    break;
            }
        }

        return $regex;
    }

    /**
     * Build a path.
     *
     * @param  array   $parts
     * @param  array   $mergedParams
     * @param  bool    $isOptional
     * @param  bool    $hasChild
     * @param  array   $options
     * @param  string  $routeName
     * @return string
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    protected function buildPath(array $parts, array $mergedParams, $isOptional, $hasChild, array $options, $routeName)
    {
        if (!empty($this->routes[$routeName]['translationKeys'])) {
            if (! isset($options['translator']) || ! $options['translator'] instanceof Translator) {
                throw new Exception\RuntimeException('No translator provided');
            }

            $translator = $options['translator'];
            $textDomain = (isset($options['text_domain']) ? $options['text_domain'] : 'default');
            $locale = (isset($options['locale']) ? $options['locale'] : null);
        }

        $path = '';
        $skip = true;
        $skippable = false;

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $path .= $part[1];
                    break;

                case 'parameter':
                    $skippable = true;

                    if (! isset($mergedParams[$part[1]])) {
                        if (! $isOptional || $hasChild) {
                            throw new Exception\InvalidArgumentException(sprintf('Missing parameter "%s"', $part[1]));
                        }

                        return '';
                    } elseif (! $isOptional
                        || $hasChild
                        || ! isset($this->defaults[$part[1]])
                        || $this->defaults[$part[1]] !== $mergedParams[$part[1]]
                    ) {
                        $skip = false;
                    }

                    $path .= $this->encode($mergedParams[$part[1]]);

                    $this->assembledParams[] = $part[1];
                    break;

                case 'optional':
                    $skippable = true;
                    $optionalPart = $this->buildPath($part[1], $mergedParams, true, $hasChild, $options, $routeName);

                    if ($optionalPart !== '') {
                        $path .= $optionalPart;
                        $skip = false;
                    }
                    break;

                case 'translated-literal':
                    $path .= $translator->translate($part[1], $textDomain, $locale);
                    break;
            }
        }

        if ($isOptional && $skippable && $skip) {
            return '';
        }

        return $path;
    }

    public function match(Request $request, $pathOffset = null, array $options = [])
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        $uri = $request->getUri();
        $path = $uri->getPath();

        $matches = [];

        foreach ($this->routes as $routeName => $data) {
            if (!isset($this->routes[$routeName]['regex'])) {
                $this->prepareRoute($routeName);
            }

            $regex = $this->routes[$routeName]['regex'];

            if (!empty($this->routes[$routeName]['translationKeys'])) {
                if (! isset($options['translator']) || ! $options['translator'] instanceof Translator) {
                    throw new Exception\RuntimeException('No translator provided');
                }

                $translator = $options['translator'];
                $textDomain = (isset($options['text_domain']) ? $options['text_domain'] : 'default');
                $locale = (isset($options['locale']) ? $options['locale'] : null);

                foreach ($this->routes[$routeName]['translationKeys'] as $key) {
                    $regex = str_replace('#' . $key . '#', $translator->translate($key, $textDomain, $locale), $regex);
                }
            }

            if (is_null($pathOffset)) {
                $result = preg_match('(^' . $regex . '$)', $path, $matches);
            } else {
                $result = preg_match('(\G' . $regex . ')', $path, $matches, null, $pathOffset);
            }

            if ($result) {
                $matchedLength = strlen($matches[0]);
                $params = [];

                foreach ($this->routes[$routeName]['paramMap'] as $index => $name) {
                    if (isset($matches[$index]) && $matches[$index] !== '') {
                        $params[$name] = $this->decode($matches[$index]);
                    }
                }

                return new RouteMatch(array_merge($data['defaults'], $params), $matchedLength);
            }
        }
    }

    public function assemble(array $params = [], array $options = [])
    {
        if (empty($params['route_name'])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The params "route_name" is required to assemble params currently.'); // @translate
        }

        $routeName = $params['route_name'];
        if (!isset($this->routes[$routeName])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The params "route_name" is not managed.'); // @translate
        }

        $this->assembledParams = [];

        return $this->buildPath(
            $this->routes[$routeName]['parts'],
            array_merge($this->routes[$routeName]['defaults'], $params),
            false,
            (isset($options['has_child']) ? $options['has_child'] : false),
            $options,
            $routeName
        );
    }

    public function getAssembledParams()
    {
        return $this->assembledParams;
    }

    /**
     * Encode a path segment.
     *
     * @param  string $value
     * @return string
     */
    protected function encode($value)
    {
        $key = (string) $value;
        if (! isset(static::$cacheEncode[$key])) {
            static::$cacheEncode[$key] = rawurlencode($value);
            static::$cacheEncode[$key] = strtr(static::$cacheEncode[$key], static::$urlencodeCorrectionMap);
        }
        return static::$cacheEncode[$key];
    }

    /**
     * Decode a path segment.
     *
     * @param  string $value
     * @return string
     */
    protected function decode($value)
    {
        return rawurldecode($value);
    }
}
