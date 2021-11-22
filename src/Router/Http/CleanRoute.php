<?php declare(strict_types=1);

namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;
// use const CleanUrl\SLUG_PAGE;
use const CleanUrl\SLUG_SITE;
// use const CleanUrl\SLUG_SITE_DEFAULT;
use const CleanUrl\SLUGS_CORE;
use const CleanUrl\SLUGS_RESERVED;
use const CleanUrl\SLUGS_SITE;

use CleanUrl\View\Helper\GetMediaFromPosition;
use CleanUrl\View\Helper\GetResourceFromIdentifier;
use CleanUrl\View\Helper\GetResourceIdentifier;
use Doctrine\ORM\EntityManager;
use Laminas\Router\Exception;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface as Request;
use Omeka\Api\Manager as ApiManager;
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
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \CleanUrl\View\Helper\GetMediaFromPosition
     */
    protected $getMediaFromPosition;

    /**
     * @var \CleanUrl\View\Helper\GetResourceFromIdentifier
     */
    protected $getResourceFromIdentifier;

    /**
     * @var GetResourceIdentifier
     */
    protected $getResourceIdentifier;

    /**
     * @var array
     */
    protected $routes;

    /**
     * @var array
     */
    protected $routeAliases;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    /**
     * @param array $routes
     * @param array $routeAliases
     * @param \Omeka\Api\Manager $api
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param \CleanUrl\View\Helper\GetMediaFromPosition $getMediaFromPosition
     * @param \CleanUrl\View\Helper\GetResourceFromIdentifier $getResourceFromIdentifier
     * @param \CleanUrl\View\Helper\GetResourceIdentifier $getResourceIdentifier
     */
    public function __construct(
        array $routes,
        array $routeAliases,
        ApiManager $api,
        EntityManager $entityManager,
        GetMediaFromPosition $getMediaFromPosition,
        GetResourceFromIdentifier $getResourceFromIdentifier,
        GetResourceIdentifier $getResourceIdentifier
    ) {
        $this->routes = $routes;
        $this->routeAliases = $routeAliases;
        $this->api = $api;
        $this->entityManager = $entityManager;
        $this->getMediaFromPosition = $getMediaFromPosition;
        $this->getResourceFromIdentifier = $getResourceFromIdentifier;
        $this->getResourceIdentifier = $getResourceIdentifier;
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

        return new static(
            $options['routes'],
            $options['route_aliases'],
            $options['api'],
            $options['entityManager'],
            $options['getMediaFromPosition'],
            $options['getResourceFromIdentifier'],
            $options['getResourceIdentifier']
        );
    }

    public function match(Request $request, $pathOffset = null)
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        // Avoid an issue when not configured.
        if (empty($this->routes)) {
            return null;
        }

        $uri = $request->getUri();
        $path = $uri->getPath();

        $matches = [];

        if (!is_null($pathOffset)) {
            $pathOffset = (int) $pathOffset;
        }

        // Check if it is a top url first or if there is a base path.
        if (mb_stripos('|' . SLUGS_SITE . '|', '|' . trim(mb_substr($path, mb_strlen(SLUG_SITE)), '/') . '|') !== false) {
            return null;
        }

        foreach ($this->routes as /* $routeName =>*/ $data) {
            $regex = $data['regex'];

            if (is_null($pathOffset)) {
                $result = preg_match('(^' . $regex . '$)', $path, $matches);
            } else {
                $result = preg_match('(\G' . $regex . '$)', $path, $matches, 0, $pathOffset);
            }

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

            // Check if the resource identifier is a reserved word.
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

            $resourceId = $this->getResourceIdFromParams($params, $data);
            if (!$resourceId) {
                continue;
            }

            // The path is good, so prepare the forward route.
            // Updated some data directly to avoid a recursive merge.

            $params['id'] = $resourceId;

            // Omeka doesn't check if the resource belongs to the site, as it
            // allows to display linked resources. So the site is not checked.
            // But check other identifiers if any.
            if ($data['resource_type'] === 'item_sets') {
                if ($data['context'] === 'site') {
                    $params['id'] = null;
                    $data['defaults']['forward']['item-set-id'] = $resourceId;
                }
            } elseif ($data['resource_type'] === 'items') {
                if (!empty($data['item_set_identifier'])) {
                    $itemSetId = $this->getResourceIdentifierFromParams($params, $data['item_set_identifier'], 'id');
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
                    $itemId = $this->getResourceIdentifierFromParams($params, $data['item_identifier'], 'id');
                    if (!$itemId) {
                        continue;
                    }
                    if (!$this->mediaBelongsToItem($resourceId, $itemId)) {
                        continue;
                    }
                    $params['item_id'] = $itemId;
                }
                if (!empty($data['item_set_identifier'])) {
                    $itemSetId = $this->getResourceIdentifierFromParams($params, $data['item_set_identifier'], 'id');
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

            $data['defaults']['forward']['id'] = $params['id'];
            if (!empty($params['site-slug']) && $data['context'] === 'site') {
                $data['defaults']['forward']['site-slug'] = $params['site-slug'];
            }

            $matchedLength = mb_strlen($matches[0]);

            return new RouteMatch(array_merge($data['defaults'], $params), $matchedLength);
        }

        return null;
    }

    public function assemble(array $params = [], array $options = [])
    {
        $routeName = $this->getCleanRouteName($params, $options);
        if (empty($routeName)) {
            return '';
        }

        $fullParams = $this->prepareRouteParams($params, $options, $routeName);
        if (empty($fullParams)) {
            return '';
        }

        $keepSlash = $this->routes[$routeName]['options']['keep_slash'];

        $replace = [];
        foreach ($fullParams as $key => $value) {
            $replace['%' . $key . '%'] = $this->encode($value, $keepSlash);
        }

        $this->assembledParams = $this->routes[$routeName]['parts'];
        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->routes[$routeName]['spec']
        );
    }

    public function getAssembledParams()
    {
        return $this->assembledParams;
    }

    protected function getCleanRouteName($params, $options): ?string
    {
        if (empty($options['route_name'])) {
            return null;
        }

        // All available routes are prepared one time.

        $routeName = $options['route_name'];
        if (isset($this->routes[$routeName])) {
            return $routeName;
        }

        // Context is "site" or "admin". Context "site" means "public" or "top".
        $context = strtok($routeName, '/');
        if (!in_array($context, ['site', 'admin'])) {
            return null;
        }
        if ($context === 'site') {
            $context = SLUG_MAIN_SITE !== false
                && (empty($params['site-slug']) || $params['site-slug'] === SLUG_MAIN_SITE)
                ? 'top'
                : 'public';
        }

        if (isset($this->routeAliases[$context][$routeName])) {
            return $this->routeAliases[$context][$routeName];
        }

        $controllerName = $this->controllerName($params['__CONTROLLER__'] ?? $params['controller'] ?? '');
        if (empty($controllerName)) {
            return null;
        }

        $map = [
            'site/resource-id' => [
                'item-set' => 'item-set',
                'item' => 'item',
                'media' => 'media',
            ],
            'site/item-set' => [
                'item-set' => 'item-set',
            ],
            'site/resource' => [
                'item-set' => 'item-set-browse',
                'item' => 'item-browse',
                'media' => 'media-browse',
            ],
            'admin/id' => [
                'item-set' => 'item-set',
                'item' => 'item',
                'media' => 'media',
            ],
            'admin/default' => [
                'item-set' => 'item-set-browse',
                'item' => 'item-browse',
                'media' => 'media-browse',
            ],
        ];
        $mapRouteName = $map[$routeName][$controllerName] ?? '';
        if (empty($routeName)) {
            return null;
        }

        if (!empty($this->routeAliases[$context][$mapRouteName])) {
            return reset($this->routeAliases[$context][$mapRouteName]);
        }

        return empty($this->routeAliases[$context][$mapRouteName . '-default'])
            ? null
            : reset($this->routeAliases[$context][$mapRouteName . '-default']);
    }

    /**
     * Check if all parts are available and prepare each identifier.
     *
     * @todo Use a database of all identifiers.
     *
     * @param array $params
     * @param array $options
     * @param string $routeName
     * @return array|null
     */
    protected function prepareRouteParams(array $params, array $options, string $routeName): ?array
    {
        // It is useless to get the identifiers if they are all present.
        $parts = $this->routes[$routeName]['parts'];
        $result = array_fill_keys($parts, null);
        $result = array_replace($result, array_intersect_key($params, $result));
        if (count($result) === count(array_filter($result))) {
            return $result;
        }

        // First, get the main resource, normally provided via an id.
        $resourceType = $this->routes[$routeName]['resource_type'];
        if (empty($params['id'])) {
            $map = [
                'item_sets' => [
                    'item_set_resource' => null,
                    'item_set_id' => null,
                    'item_set_identifier' => null,
                    'item_set_identifier_short' => null,
                ],
                'items' => [
                    'item_resource' => null,
                    'item_id' => null,
                    'item_identifier' => null,
                    'item_identifier_short' => null,
                ],
                'media' => [
                    'media_resource' => null,
                    'media_id' => null,
                    'media_identifier' => null,
                    'media_identifier_short' => null,
                    'media_position' => null,
                ],
            ];
            if (!isset($map[$resourceType])) {
                return [];
            }
            $resourceKeys = array_intersect_key($map[$resourceType], $params);
            if (empty($resourceKeys)) {
                return null;
            }
            $resource = $this->getResourceIdentifierFromParams($params, key($resourceKeys), 'resource');
            if (empty($resource)) {
                return [];
            }
        } else {
            try {
                $resource = $this->api->read($resourceType, ['id' => $params['id']], [], ['initialize' => false])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return [];
            }
        }

        // Second, get identifiers.
        foreach ($result as $part => $value) {
            if (!is_null($value) && $value !== '') {
                continue;
            }
            switch ($part) {
                case 'site-slug':
                    // Should be already filled above.
                    $result[$part] = SLUG_MAIN_SITE;
                    break;
                case 'resource_id':
                case 'resource_identifier':
                case 'resource_identifier_short':
                    // Not managed currently.
                    break;
                case 'item_set_id':
                    switch ($resourceType) {
                        case 'item_sets':
                            $result[$part] = $resource->id();
                            break;
                        case 'items':
                            $itemSets = $resource->itemSets();
                            if (!$itemSets) {
                                return [];
                            }
                            $result[$part] = key($itemSets);
                            break;
                        case 'media':
                            $itemSets = $resource->item()->itemSets();
                            if (!$itemSets) {
                                return [];
                            }
                            $result[$part] = key($itemSets);
                            break;
                    }
                    break;
                case 'item_set_identifier':
                case 'item_set_identifier_short':
                    switch ($resourceType) {
                        case 'item_sets':
                            $result[$part] = $this->getResourceIdentifier->__invoke($resource, false, (bool) strpos($part, '_short'));
                            break;
                        case 'items':
                            $itemSets = $resource->itemSets();
                            if (!$itemSets) {
                                return [];
                            }
                            $itemSet = reset($itemSets);
                            $result[$part] = $this->getResourceIdentifier->__invoke($itemSet, false, (bool) strpos($part, '_short'));
                            break;
                        case 'media':
                            $itemSets = $resource->item()->itemSets();
                            if (!$itemSets) {
                                return [];
                            }
                            $itemSet = reset($itemSets);
                            $result[$part] = $this->getResourceIdentifier->__invoke($itemSet, false, (bool) strpos($part, '_short'));
                            break;
                    }
                    break;
                case 'item_id':
                    switch ($resourceType) {
                        case 'item_sets':
                            return [];
                        case 'items':
                            $result[$part] = $resource->id();
                            break;
                        case 'media':
                            $result[$part] = $resource->item()->id();
                            break;
                    }
                    break;
                case 'item_identifier':
                case 'item_identifier_short':
                    switch ($resourceType) {
                        case 'item_sets':
                            return [];
                        case 'items':
                            $result[$part] = $this->getResourceIdentifier->__invoke($resource, false, (bool) strpos($part, '_short'));
                            break;
                        case 'media':
                            $result[$part] = $this->getResourceIdentifier->__invoke($resource->item(), false, (bool) strpos($part, '_short'));
                            break;
                    }
                    break;
                case 'media_id':
                    switch ($resourceType) {
                        case 'item_sets':
                        case 'items':
                            return [];
                        case 'media':
                            $result[$part] = $resource->id();
                            break;
                    }
                    break;
                case 'media_identifier':
                case 'media_identifier_short':
                    switch ($resourceType) {
                        case 'item_sets':
                        case 'items':
                            return [];
                        case 'media':
                            $result[$part] = $this->getResourceIdentifier->__invoke($resource, false, (bool) strpos($part, '_short'));
                            break;
                    }
                    break;
                case 'media_position':
                    // Don't use $item->media() to avoid a different position
                    // with public and private media.
                    // $view->api() cannot set a response content.
                    $result[$part] = $this->api->read('media', ['id' => $resource->id()], [], ['initialize' => false, 'responseContent' => 'resource'])->getContent()
                        ->getPosition();
                    break;
                default:
                    break;
            }
        }

        $result = array_filter($result);
        return count($result) === count($parts)
            ? $result
            : [];
    }

    /**
     * Normalize the controller name.
     *
     * @param string $name
     * @return string
     */
    protected function controllerName($name): ?string
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

    protected function getResourceIdFromParams(array $params, array $data): ?int
    {
        if (in_array($data['resource_identifier'], ['resource_id', 'item_set_id', 'item_id', 'media_id'])) {
            $resourceIds = $this->api->search($data['resource_type'], ['id' => $params['resource_identifier'], 'limit' => 1], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
            return count($resourceIds) ? (int) reset($resourceIds) : null;
        } elseif ($data['resource_identifier'] === 'media_position') {
            return $this->getResourceIdentifierFromParams($params, 'media_position', 'id');
        } else {
            $resource = $this->getResourceFromIdentifier->__invoke($params['resource_identifier'], $data['resource_type']);
            return $resource ? $resource->id() : null;
        }
    }

    protected function getResourceIdentifierFromParams(array $params, string $identifierName, string $output)
    {
        $mapInput = [
            'id' => [
                'resource' => 'resources',
                'type' => 'id',
            ],
            'resource_id' => [
                'resource' => 'resources',
                'type' => 'id',
            ],
            'item_set_id' => [
                'resource' => 'item_sets',
                'type' => 'id',
            ],
            'item_id' => [
                'resource' => 'items',
                'type' => 'id',
            ],
            'media_id' => [
                'resource' => 'media',
                'type' => 'id',
            ],

            'resource_identifier' => [
                'resource' => 'resources',
                'type' => 'identifier',
            ],
            'item_set_identifier' => [
                'resource' => 'item_sets',
                'type' => 'identifier',
            ],
            'item_identifier' => [
                'resource' => 'items',
                'type' => 'identifier',
            ],
            'media_identifier' => [
                'resource' => 'media',
                'type' => 'identifier',
            ],

            'resource_identifier_short' => [
                'resource' => 'resources',
                'type' => 'identifier_short',
            ],
            'item_set_identifier_short' => [
                'resource' => 'item_sets',
                'type' => 'identifier_short',
            ],
            'item_identifier_short' => [
                'resource' => 'items',
                'type' => 'identifier_short',
            ],
            'media_identifier_short' => [
                'resource' => 'media',
                'type' => 'identifier_short',
            ],

            'media_position' => [
                'resource' => 'media',
                'type' => 'position',
            ],

            'resource_resource' => [
                'resource' => 'resources',
                'type' => 'resource',
            ],
            'item_set_resource' => [
                'resource' => 'item_sets',
                'type' => 'resource',
            ],
            'item_resource' => [
                'resource' => 'items',
                'type' => 'resource',
            ],
            'media_resource' => [
                'resource' => 'media',
                'type' => 'resource',
            ],
        ];

        if (empty($params[$identifierName]) || !isset($mapInput[$identifierName])) {
            return null;
        }

        // No check is done when input is output.
        if ($identifierName === $output) {
            return $params[$identifierName];
        }

        $resourceIdentifier = $params[$identifierName];
        $resourceType = $mapInput[$identifierName]['resource'];
        $identifierType = $mapInput[$identifierName]['type'];
        switch ($identifierType) {
            case 'id':
                $resources = $this->api->search($resourceType, ['id' => $resourceIdentifier, 'limit' => 1], ['initialize' => false])->getContent();
                if (!count($resources)) {
                    return null;
                }
                $resource = reset($resources);
                break;
            case 'identifier':
            case 'identifier_short':
                $resource = $this->getResourceFromIdentifier->__invoke($resourceIdentifier, $resourceType);
                if (!$resource) {
                    return null;
                }
                break;
            case 'position':
                $itemKeys = array_intersect_key(
                    ['item_resource' => null, 'item_id' => null, 'item_identifier' => null, 'item_identifier_short' => null],
                    $params
                );
                if (empty($itemKeys)) {
                    return null;
                }
                $item = $this->getResourceIdentifierFromParams($params, key($itemKeys), 'resource');
                if (!$item) {
                    return null;
                }
                $resource = $this->getMediaFromPosition->__invoke($item, (int) $resourceIdentifier);
                if (!$resource) {
                    return null;
                }
                break;
            case 'resource':
                $resource = $resourceIdentifier;
                break;
            default:
                return null;
        }

        switch ($output) {
            case 'id':
                return $resource->id();
            case 'resource':
                return $resource;
            case 'identifier':
                return $this->getResourceIdentifier->__invoke($resource);
            case 'identifier_short':
                return $this->getResourceIdentifier->__invoke($resource->item(), false, true);
            case 'position':
                // Don't use $item->media() to avoid a different position
                // with public and private media.
                // The media representation doesn't have the position.
                // $view->api() cannot set a response content.
                return $this->entityManager
                    ->getRepository(\Omeka\Entity\Media::class)
                    ->find($resource->id())
                    ->getPosition();
            default:
                return null;
        }
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

    /**
     * Encode a string.
     *
     * This method avoids to raw-urlencode characters that don't need.
     *
     * @see \Laminas\Router\Http\Segment::encode()
     *
     * @param string $value
     * @param bool $keepSlash
     * @return string
     */
    protected function encode($value, $keepSlash = false): string
    {
        static $urlencodeCorrectionMap;

        if (is_null($urlencodeCorrectionMap)) {
            $urlencodeCorrectionMap = [];
            $urlencodeCorrectionMap[false] = [
                '%21' => '!', // sub-delims
                '%24' => '$', // sub-delims
                '%26' => '&', // sub-delims
                '%27' => "'", // sub-delims
                '%28' => '(', // sub-delims
                '%29' => ')', // sub-delims
                '%2A' => '*', // sub-delims
                '%2B' => '+', // sub-delims
                '%2C' => ',', // sub-delims
                // '%2D' => '-', // unreserved - not touched by rawurlencode
                // '%2E' => '.', // unreserved - not touched by rawurlencode
                '%3A' => ':', // pchar
                '%3B' => ';', // sub-delims
                '%3D' => '=', // sub-delims
                '%40' => '@', // pchar
                // '%5F' => '_', // unreserved - not touched by rawurlencode
                // '%7E' => '~', // unreserved - not touched by rawurlencode
            ];
            $urlencodeCorrectionMap[true] = $urlencodeCorrectionMap[false];
            $urlencodeCorrectionMap[true]['%2F'] = '/';
        }

        return strtr(rawurlencode((string) $value), $urlencodeCorrectionMap[$keepSlash]);
    }
}
