<?php declare(strict_types=1);

namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_PAGE;
use const CleanUrl\SLUG_SITE;
use const CleanUrl\SLUG_SITE_DEFAULT;
use const CleanUrl\SLUGS_CORE;
use const CleanUrl\SLUGS_RESERVED;
use const CleanUrl\SLUGS_SITE;

use CleanUrl\View\Helper\GetMediaFromPosition;
use CleanUrl\View\Helper\GetResourceFromIdentifier;
use Doctrine\ORM\EntityManager;
use Laminas\Router\Exception;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface as Request;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Traversable;

/**
 * Manage clean urls for all Omeka resources and pages according to the config.
 *
 * @todo Store all routes of all resources and pages in the database?
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
     * @var \CleanUrl\View\Helper\GetResourceFromIdentifier
     */
    protected $getResourceFromIdentifier;

    /**
     * @var \CleanUrl\View\Helper\GetMediaFromPosition
     */
    protected $getMediaFromPosition;

    /**
     * @var EntityManager
     */
    protected $entityManager;

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
     * @param ApiManager $api
     * @param GetResourceFromIdentifier $getResourceFromIdentifier
     * @param GetMediaFromPosition $getMediaFromPosition
     * @param EntityManager $entityManager
     * @param string $basePath
     * @param array $settings
     * @param array $helpers Needed only to assemble an url.
     */
    public function __construct(
        ApiManager $api = null,
        GetResourceFromIdentifier $getResourceFromIdentifier = null,
        GetMediaFromPosition $getMediaFromPosition = null,
        EntityManager $entityManager = null,
        string $basePath = '',
        array $settings = [],
        array $helpers = []
    ) {
        $this->api = $api;
        $this->getResourceFromIdentifier = $getResourceFromIdentifier;
        $this->getMediaFromPosition = $getMediaFromPosition;
        $this->entityManager = $entityManager;
        $this->basePath = $basePath;
        $this->helpers = $helpers;
        $this->settings = $settings + [
            'default_site' => 0,
            'site_skip_main' => false,
            'site_slug' => 's/',
            'page_slug' => 'page/',
            'identifier_property' => 10,
            'identifier_prefix' => '',
            'identifier_short' => '',
            'identifier_prefix_part_of' => false,
            'identifier_case_sensitive' => false,
            'item_set_paths' => [],
            'item_set_default' => '',
            'item_set_pattern' => '',
            'item_set_pattern_short' => '',
            'item_paths' => [],
            'item_default' => '',
            'item_pattern' => '',
            'item_pattern_short' => '',
            'media_paths' => [],
            'media_default' => '',
            'media_pattern' => '',
            'media_pattern_short' => '',
            'admin_use' => true,
            'admin_reserved' => [],
            'regex' => [],
        ];
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
            'getResourceFromIdentifier' => null,
            'getMediaFromPosition' => null,
            'entityManager' => null,
            'base_path' => '',
            'settings' => [],
            'helpers' => [],
        ];

        return new static(
            $options['api'],
            $options['getResourceFromIdentifier'],
            $options['getMediaFromPosition'],
            $options['entityManager'],
            $options['base_path'],
            $options['settings'],
            $options['helpers']
        );
    }

    public function match(Request $request, $pathOffset = null)
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        // Avoid an issue when not configured.
        if (empty($this->settings['regex'])) {
            return null;
        }

        $uri = $request->getUri();
        $path = $uri->getPath();

        $matches = [];

        // The path offset is currently not managed: no action.
        // So the check all the remaining paths. Routes will be reordered.
        $path = mb_substr($path, (int) $pathOffset);

        // Check if it is a top url first or if there is a base path.
        if (mb_stripos('|' . SLUGS_SITE . '|', '|' . trim(mb_substr($path, mb_strlen(SLUG_SITE)), '/') . '|') !== false) {
            return null;
        }

        foreach ($this->settings['regex'] as $routeName => $data) {
            $regex = $data['regex'];

            // if (is_null($pathOffset)) {
            $result = preg_match('(^' . $regex . '$)', $path, $matches);
            // } else {
            //     $result = preg_match('(\G' . $regex . ')', $path, $matches, null, (int) $pathOffset);
            // }

            if (!$result) {
                continue;
            }

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

            // Regex pattern forbids "-" in matched name.
            if (isset($params['site_slug'])) {
                $params['site-slug'] = $params['site_slug'];
                unset($params['site_slug']);
            }

            // The generic resource identifier simplifies next step.
            $params['resource_identifier'] = $params[$data['resource_identifier']];

            // Check if the resource exists. Doctrine will cache it anyway.

            // Manage the case where the same route is used for different
            // resource, but the generic "resource_identifier" is not used.
            // Furthermore, the resource may be reserved.

            // Ideally, the check of the resource should be done in the final
            // controller, but a clean url is mainly a redirector.

            $resourceId = $this->getResourceId($params, $data);
            if (!$resourceId) {
                continue;
            }

            $params['id'] = $resourceId;

            // Omeka doesn't check if the resource belongs to the site, as it
            // allows to display linked resources. So the site is not checked.
            // But check other identifiers if any.
            if ($data['resource_type'] === 'item_sets') {
                $params['id'] = null;
                $data['defaults']['forward']['item-set-id'] = $resourceId;
            } elseif ($data['resource_type'] === 'items') {
                if (!empty($data['item_set_identifier'])) {
                    $itemSetId = $this->getItemSetId($params, $data['item_set_identifier']);
                    if (!$itemSetId) {
                        continue;
                    }
                    if (!$this->itemBelongsToItemSet($resourceId, $itemSetId)) {
                        continue;
                    }
                    $params['item_set_id'] = $itemSetId;
                }
            } elseif ($data['resource_type'] === 'media') {
                if (!empty($data['item_identifier'])) {
                    $itemId = $this->getItemId($params, $data['item_identifier']);
                    if (!$itemId) {
                        continue;
                    }
                    if (!$this->mediaBelongsToItem($resourceId, $itemId)) {
                        continue;
                    }
                    $params['item_id'] = $itemId;
                }
                if (!empty($data['item_set_identifier'])) {
                    $itemSetId = $this->getItemSetId($params, $data['item_set_identifier']);
                    if (!$itemSetId) {
                        continue;
                    }
                    if ($itemId) {
                        if (!$this->itemBelongsToItemSet($itemId, $itemSetId)) {
                            continue;
                        }
                    } elseif (!$this->mediaBelongsToItemSet($resourceId, $itemSetId)) {
                        continue;
                    }
                    $params['item_set_id'] = $itemSetId;
                }
            }

            // Updated the data directly to avoid a recursive merge.
            $data['defaults']['forward']['id'] = $params['id'];
            if (empty($data['defaults']['forward']['site_slug']) && !empty($params['site-slug'])) {
                $data['defaults']['forward']['site_slug'] = $params['site-slug'];
            }

            $matchedLength = mb_strlen($matches[0]);
            return new RouteMatch(array_merge($data['defaults'], $params), $matchedLength);
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
        return $controllers[$name]
            ?? null;
    }

    protected function getResourceId(array $params, array $data): ?int
    {
        if (in_array($data['resource_identifier'], ['resource_id', 'item_set_id', 'item_id', 'media_id'])) {
            $resourceIds = $this->api->search($data['resource_type'], ['id' => $params['resource_identifier'], 'limit' => 1], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
            return count($resourceIds) ? (int) reset($resourceIds) : null;
        } elseif ($data['resource_identifier'] === 'media_position') {
            return $this->getMediaIdFromPosition($params, $data);
        } else {
            $resource = $this->getResourceFromIdentifier->__invoke($params['resource_identifier'], $data['resource_type']);
            return $resource ? $resource->id() : null;
        }
    }

    protected function getItemSetId(array $params, $identifierName): ?int
    {
        switch ($identifierName) {
            case 'item_set_id':
                $resourceIds = $this->api->search('item_sets', ['id' => $params['item_set_id'], 'limit' => 1], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
                return count($resourceIds) ? (int) reset($resourceIds) : null;
            case 'item_set_identifier':
                $resource = $this->getResourceFromIdentifier->__invoke($params['item_set_identifier'], 'item_sets');
                return $resource ? $resource->id() : null;
            case 'item_set_identifier_short':
                $resource = $this->getResourceFromIdentifier->__invoke($params['item_set_identifier_short'], 'item_sets');
                return $resource ? $resource->id() : null;
            default:
                return null;
        }
    }

    protected function getItemId(array $params, $identifierName): ?int
    {
        switch ($identifierName) {
            case 'item_id':
                $resourceIds = $this->api->search('items', ['id' => $params['item_id'], 'limit' => 1], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
                return count($resourceIds) ? (int) reset($resourceIds) : null;
            case 'item_identifier':
                $resource = $this->getResourceFromIdentifier->__invoke($params['item_identifier'], 'items');
                return $resource ? $resource->id() : null;
            case 'item_identifier_short':
                $resource = $this->getResourceFromIdentifier->__invoke($params['item_identifier_short'], 'items');
                return $resource ? $resource->id() : null;
            default:
                return null;
        }
    }

    protected function getMediaId(array $params, $identifierName): ?int
    {
        switch ($identifierName) {
            case 'media_id':
                $resourceIds = $this->api->search('media', ['id' => $params['media_id'], 'limit' => 1], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
                return count($resourceIds) ? (int) reset($resourceIds) : null;
            case 'media_identifier':
                $resource = $this->getResourceFromIdentifier->__invoke($params['media_identifier'], 'items');
                return $resource ? $resource->id() : null;
            case 'media_identifier_short':
                $resource = $this->getResourceFromIdentifier->__invoke($params['media_identifier_short'], 'items');
                return $resource ? $resource->id() : null;
            default:
                return null;
        }
    }

    protected function getMediaIdFromPosition(array $params, array $data): ?int
    {
        if (!in_array($data['item_identifier'], ['item_id', 'item_identifier', 'item_identifier_short'])) {
            throw new \Omeka\Mvc\Exception\RuntimeException('The item identifier is missing in the route for media.'); // @translate
        }
        $itemId = $this->getItemId($params, $data['item_identifier']);
        return $itemId
            ? $this->getMediaFromPosition->__invoke($itemId, (int) $params['media_position'])
            : null;
    }

    protected function itemBelongsToItemSet(int $itemId, int $itemSetId): bool
    {
        return $this->entityManager
            ->getRepository(\Omeka\Entity\Item::class)
            ->findOneBy(['id' => $itemId])
            ->getItemSets()
            ->offsetExists($itemSetId);
    }

    protected function mediaBelongsToItem(int $mediaId, int $itemId): bool
    {
        return (bool) $this->entityManager
            ->getRepository(\Omeka\Entity\Media::class)
            ->findOneBy(['id' => $mediaId, 'item' => $itemId]);
    }

    protected function mediaBelongsToItemSet(int $mediaId, int $itemSetId): bool
    {
        $media = $this->entityManager
            ->getRepository(\Omeka\Entity\Media::class)
            ->find($mediaId);
        return $this->itemBelongsToItemSet($media->getItem()->getId(), $itemSetId);
    }
}
