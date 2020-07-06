<?php

namespace CleanUrl\Mvc;

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Mvc\Exception\NotFoundException;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Router\Http\RouteMatch;

class MvcListeners extends AbstractListenerAggregate
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Omeka\Api\Adapter\Manager
     */
    protected $apiAdapterManager;

    /**
     * @var \Omeka\Api\\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \CleanUrl\View\Helper\GetResourceIdentifier
     */
    protected $getResourceIdentifier;

    /**
     * @var \CleanUrl\View\Helper\GetResourceFromIdentifier
     */
    protected $getResourceFromIdentifier;

    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var array
     */
    protected $params;

    protected $isAdmin = false;
    protected $space;
    protected $namespace;
    protected $namespaceItemSet;
    protected $namespaceItem;
    protected $namespaceMedia;

    // The type and id of record to get.
    private $_resource_identifier = '';
    private $_resource_name = '';
    private $_resource_id = 0;
    // Identifiers from the url.
    private $_item_set_identifier = '';
    private $_item_identifier = '';
    private $_media_identifier = '';
    // Resolved records.
    private $_item_set_id = 0;
    private $_item_id = 0;
    private $_file_id = 0;

    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectTo']
        );
    }

    public function redirectTo(MvcEvent $event)
    {
        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();
        if ($matchedRouteName !== 'clean-url') {
            return;
        }

        $actions = [
            'route-item-browse',
            'route-item',
            'route-item-set',
            'route-item-set-item',
            'route-item-set-item-media',
            'route-item-set-media',
            'route-media',
            'route-item-media',
        ];

        $this->params = $routeMatch->getParams();
        $action = $this->params['action'];
        if (!in_array($action, $actions)) {
            // TODO Remove the clean url controler for page.
            // It may be a page, that is managed via controller currently.
            if ($action === 'top' && empty($this->params['page-slug'])) {
                // @see \Omeka\Controller\Site\IndexController::indexAction()
                /** @var \Omeka\Api\Representation\SiteRepresentation $site */
                $site = $event->getApplication()->getServiceManager()->get('ControllerPluginManager')->get('currentSite')->__invoke();
                $page = method_exists(\Omeka\Api\Representation\SiteRepresentation::class, 'homepage') ? $site->homepage() : null;
                if (!$page) {
                    $linkedPages = $site->linkedPages();
                    if (!count($linkedPages)) {
                        return;
                    }
                    $page = current($linkedPages);
                }
                $this->params['page-slug'] = $page->slug();
                return $this->forwardRouteMatch('top', $this->params);
            }
            return;
        }

        $this->event = $event;
        $services = $event->getApplication()->getServiceManager();
        $this->connection = $services->get('Omeka\Connection');
        $this->apiAdapterManager = $services->get('Omeka\ApiAdapterManager');
        $this->api = $services->get('Omeka\ApiManager');
        $this->settings = $services->get('Omeka\Settings');
        $viewHelpers = $services->get('ViewHelperManager');
        $this->getResourceIdentifier = $viewHelpers->get('getResourceIdentifier');
        $this->getResourceFromIdentifier = $viewHelpers->get('getResourceFromIdentifier');

        $this->params += [
            'resource_identifier' => null,
            'site-slug' => null,
            'item_set_identifier' => null,
            'item_identifier' => null,
            'media_identifier' => null,
        ];

        if (isset($this->params['__ADMIN__'])) {
            $this->isAdmin = true;
            $this->space = '__ADMIN__';
            $this->namespace = 'Omeka\Controller\Admin';
            $this->namespaceItemSet = 'Omeka\Controller\Admin\ItemSet';
            $this->namespaceItem = 'Omeka\Controller\Admin\Item';
            $this->namespaceMedia = 'Omeka\Controller\Admin\Media';
        } else {
            $this->space = '__SITE__';
            $this->namespace = 'Omeka\Controller\Site';
            $this->namespaceItemSet = 'Omeka\Controller\Site\ItemSet';
            $this->namespaceItem = 'Omeka\Controller\Site\Item';
            $this->namespaceMedia = 'Omeka\Controller\Site\Media';
        }

        switch ($this->params['action']) {
            case 'route-item-browse':
                return $this->routeItemBrowse();
            case 'route-item':
                return $this->routeItem();
            case 'route-item-set':
                return $this->routeItemSet();
            case 'route-item-set-item':
                return $this->routeItemSetItem();
            case 'route-item-set-item-media':
                return $this->routeItemSet();
            case 'route-item-set-media':
                return $this->routeItemSetMedia();
            case 'route-media':
                return $this->routeMedia();
            case 'route-item-media':
                return $this->routeItemMedia();
            default:
                return;
        }
    }

    protected function forwardRouteMatch($routeName, array $params)
    {
        $routeMatch = new RouteMatch($params);
        $routeMatch->setMatchedRouteName($routeName);
        $this->event->setRouteMatch($routeMatch);
        return $routeMatch;
    }

    protected function routeItemSet()
    {
        $this->_item_set_identifier = $this->params['resource_identifier'];
        $result = $this->_setItemSetId();
        if (empty($result)) {
            return $this->notFound();
        }
        return $this->itemSet();
    }

    protected function itemSet()
    {
        return $this->isAdmin
            ? $this->forwardRouteMatch('admin/id', [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'controller' => $this->namespaceItemSet,
                'action' => 'show',
                'id' => $this->_item_set_id,
                'cleanurl_route' => $this->params['action'],
            ])
            : $this->forwardRouteMatch('site/item-set', [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $this->namespaceItem,
                'action' => 'browse',
                'item-set-id' => $this->_item_set_id,
                'cleanurl_route' => $this->params['action'],
            ]);
    }

    protected function routeItemBrowse()
    {
        return $this->forwardRouteMatch(
            $this->isAdmin ? 'admin/default' : 'site/resource',
            [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $this->namespaceItem,
                'action' => 'browse',
                'cleanurl_route' => $this->params['action'],
            ]
        );
    }

    /**
     * Routes a clean url of an item to the default url.
     */
    protected function routeItem()
    {
        return $this->routeItemSetItem();
    }

    /**
     * Routes a clean url of an item to the default url.
     */
    protected function routeItemSetItem()
    {
        $this->_item_set_identifier = $this->params['item_set_identifier'];
        // If 0, this is possible (item without item set, or generic route).
        $result = $this->_setItemSetId();
        if (is_null($result)) {
            return $this->notFound();
        }

        $this->_resource_name = 'items';
        $id = $this->_routeResource();

        // If no identifier exists, the module tries to use the resource id
        // directly.
        if (!$id) {
            // When there is no difference between the identifier of an item and
            // identifier of another resource, for example when there is an ark
            // for an item set), the route of the resource goes here.
            try {
                $resource = $this->api->read('resources', $this->_resource_identifier)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return $this->notFound();
            }

            $this->_resource_name = $resource->resourceName();
            $this->_resource_id = $resource->id();
            switch ($this->_resource_name) {
                case 'item_sets':
                    $this->_item_set_id = $this->_resource_id;
                    return $this->itemSet();
                case 'media':
                    $this->_media_id = $this->_resource_id;
                    return $this->forwardRouteMatch(
                        $this->isAdmin ? 'admin/id' : 'site/resource-id',
                        [
                            '__NAMESPACE__' => $this->namespace,
                            $this->space => true,
                            'site-slug' => $this->params['site-slug'],
                            'controller' => $this->namespaceMedia,
                            'action' => 'show',
                            'id' => $this->_resource_id,
                            'cleanurl_route' => $this->params['action'],
                        ]
                    );
                    break;
                case 'items':
                    $this->checkItemBelongsToItemSet($resource, $this->_item_set_id);
                    break;
                default:
                    return $this->notFound();
            }
        }

        $this->_resource_id = $id;

        return $this->forwardRouteMatch(
            $this->isAdmin ? 'admin/id' : 'site/resource-id',
            [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $this->namespaceItem,
                'action' => 'show',
                'id' => $this->_resource_id,
                'cleanurl_route' => $this->params['action'],
            ]
        );
    }

    /**
     * Routes a clean url of a media to the default url.
     */
    protected function routeMedia()
    {
        $this->_resource_name = 'media';
        $id = $this->_routeResource();

        // If no identifier exists, the module tries to use the identifier
        // specified in the config, or the resource id directly.
        if (!$id) {
            $media = $this->retrieveMedia($this->_resource_identifier);
            if (!$media) {
                return $this->notFound();
            }
            $id = $media->id();
        }

        $this->_resource_id = $id;

        return $this->forwardRouteMatch(
            $this->isAdmin ? 'admin/id' : 'site/resource-id',
            [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $this->namespaceMedia,
                'action' => 'show',
                'id' => $this->_resource_id,
                'cleanurl_route' => $this->params['action'],
            ]
        );
    }

    /**
     * Routes a clean url of a media with item to the default url.
     */
    protected function routeItemMedia()
    {
        return $this->routeItemSetItemMedia();
    }

    /**
     * Routes a clean url of a media with item to the default url.
     */
    protected function routeItemSetMedia()
    {
        return $this->routeItemSetItemMedia();
    }

    /**
     * Routes a clean url of a media with item set and item to the default url.
     */
    protected function routeItemSetItemMedia()
    {
        $this->_item_set_identifier = $this->params['item_set_identifier'];
        // If 0, this is possible (item without item set, or generic route).
        $itemSetId = $this->_setItemSetId();
        if (is_null($itemSetId)) {
            return $this->notFound();
        }
        $this->_item_identifier = $this->params['item_identifier'];
        // TODO Check if it is still the case.
        // If 0, this is possible (generic route).
        $itemId = $this->_setItemId();
        if (is_null($itemId)) {
            return $this->notFound();
        }
        $this->_resource_name = 'media';
        $id = $this->_routeResource();

        // If no identifier exists, the module tries to use the identifier
        // specified in the config, or the resource id directly.
        if (!$id) {
            $resource = $this->retrieveMedia($this->_resource_identifier, $itemId);
            if (!$resource) {
                return $this->notFound();
            }

            $id = $resource->id();

            // Check if the found media belongs to the item set.
            if (!$this->_checkItemSetMedia($resource)) {
                return $this->notFound();
            }

            // Check if the found file belongs to the item.
            if (!$this->_checkItemMedia($resource)) {
                return $this->notFound();
            }
        }

        $this->_resource_id = $id;

        return $this->forwardRouteMatch(
            $this->isAdmin ? 'admin/id' : 'site/resource-id',
            [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $this->namespaceMedia,
                'action' => 'show',
                'id' => $this->_resource_id,
                'cleanurl_route' => $this->params['action'],
            ]
        );
    }

    /**
     * Get id from the resource identifier (item or media).
     *
     * @todo Use the standard getResourceFromIdentifier().
     *
     * @return int Id of the resource.
     */
    protected function _routeResource()
    {
        $this->_resource_identifier = $this->params['resource_identifier'];

        $identifiers = [];

        switch ($this->_resource_name) {
            case 'items':
                $allowShortIdentifier = $this->allowShortIdentifierItem();
                $allowFullIdentifier = $this->allowFullIdentifierItem();
                $includeItemSetIdentifier = $this->settings->get('cleanurl_item_item_set_included');
                $itemSetIdentifier = $this->_item_set_identifier && $includeItemSetIdentifier !== 'no'
                    ? $this->_item_set_identifier  . '/'
                    : '';
                $includeItemIdentifier = 'no';
                $itemIdentifier = '';
                break;
            case 'media':
                $allowShortIdentifier = $this->allowShortIdentifierMedia();
                $allowFullIdentifier = $this->allowFullIdentifierMedia();
                $includeItemSetIdentifier = $this->settings->get('cleanurl_media_item_set_included');
                $itemSetIdentifier = $this->_item_set_identifier && $includeItemSetIdentifier !== 'no'
                    ? $this->_item_set_identifier  . '/'
                    : '';
                $includeItemIdentifier = $this->settings->get('cleanurl_media_item_included');
                $itemIdentifier = $this->_item_identifier && $includeItemIdentifier !== 'no'
                    ? $this->_item_identifier  . '/'
                    : '';
                break;
        }

        $isNN = $includeItemSetIdentifier === 'no' && $includeItemIdentifier === 'no';
        $isNY = $includeItemSetIdentifier === 'no' && $includeItemIdentifier !== 'no';
        $isYN = $includeItemSetIdentifier !== 'no' && $includeItemIdentifier === 'no';
        $isYY = $includeItemSetIdentifier !== 'no' && $includeItemIdentifier !== 'no';

        if ($allowShortIdentifier) {
            // Check the identifier of the record (commonly dcterms:identifier).
            $prefix = $this->settings->get('cleanurl_identifier_prefix');
            $identifiers[] = $isNN ? $prefix . $this->_resource_identifier : null;
            $identifiers[] = $isNY ? $prefix . $itemIdentifier . $this->_resource_identifier : null;
            $identifiers[] = $isYN ? $prefix . $itemSetIdentifier . $this->_resource_identifier : null;
            $identifiers[] = $isYY ? $prefix . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier : null;

            // Check with a space between prefix and identifier too.
            $identifiers[] = $isNN ? $prefix . ' ' . $this->_resource_identifier : null;
            $identifiers[] = $isNY ? $prefix . ' ' . $itemIdentifier . $this->_resource_identifier : null;
            $identifiers[] = $isYN ? $prefix . ' ' . $itemSetIdentifier . $this->_resource_identifier : null;
            $identifiers[] = $isYY ? $prefix . ' ' . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier : null;

            // Check prefix with a space and a no-break space.
            if ($this->settings->get('cleanurl_identifier_unspace')) {
                $unspace = str_replace([' ', ' '], '', $prefix);
                if ($prefix != $unspace) {
                    $identifiers[] = $isNN ? $unspace . $this->_resource_identifier : null;
                    $identifiers[] = $isNN ? $unspace . ' ' . $this->_resource_identifier : null;
                    $identifiers[] = $isNY ? $unspace . $itemIdentifier . $this->_resource_identifier : null;
                    $identifiers[] = $isNY ? $unspace . ' ' . $itemIdentifier . $this->_resource_identifier : null;
                    $identifiers[] = $isYN ? $unspace . $itemSetIdentifier . $this->_resource_identifier : null;
                    $identifiers[] = $isYN ? $unspace . ' ' . $itemSetIdentifier . $this->_resource_identifier : null;
                    $identifiers[] = $isYY ? $unspace . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier : null;
                    $identifiers[] = $isYY ? $unspace . ' ' . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier : null;
                }
            }
        }

        if ($allowFullIdentifier) {
            $identifiers[] = $this->_resource_identifier;
        }

        $result = $this->queryResource($identifiers, $this->_item_set_id, $this->_item_id, $this->_resource_name);
        return $result ? $result['id'] : null;
    }

    /**
     * Try to catch flat identifier before returning a not found.
     *
     * @return array|null Id and type of the resource.
     */
    protected function notFound()
    {
        // Manage the case where the same format is used by multiple routes, for
        // example for a root identifier, or routes generic/resource_identifier
        // with the same generic name.

        // The difference with _routeResource() is that the resource name is
        // unknown and probably wrong.
        $includeItemSetIdentifierItem = $this->settings->get('cleanurl_item_item_set_included');
        $includeItemSetIdentifierMedia = $this->settings->get('cleanurl_media_item_set_included');
        $includeItemIdentifier = $this->settings->get('cleanurl_media_item_included');

        $itemSetIdentifier = $this->_item_set_identifier
            ? $this->_item_set_identifier  . '/'
            : '';
        $itemIdentifier = $this->_item_identifier
            ? $this->_item_identifier  . '/'
            : '';

        $identifiers = [];

        // Check the identifier of the record (commonly dcterms:identifier).
        $prefix = $this->settings->get('cleanurl_identifier_prefix');
        $identifiers[] = $prefix . $this->_resource_identifier;
        if ($includeItemIdentifier !== 'no') {
            $identifiers[] = $prefix . $itemIdentifier . $this->_resource_identifier;
        }
        if ($includeItemSetIdentifierItem !== 'no' || $includeItemSetIdentifierMedia !== 'no') {
            $identifiers[] = $prefix . $itemSetIdentifier . $this->_resource_identifier;
            if ($includeItemIdentifier !== 'no') {
                $identifiers[] = $prefix . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier;
            }
        }

        // Check with a space between prefix and identifier too.
        $identifiers[] = $prefix . ' ' . $this->_resource_identifier;
        if ($includeItemIdentifier !== 'no') {
            $identifiers[] = $prefix . ' ' . $itemIdentifier . $this->_resource_identifier;
        }
        if ($includeItemSetIdentifierItem !== 'no' || $includeItemSetIdentifierMedia !== 'no') {
            $identifiers[] = $prefix . ' ' . $itemSetIdentifier . $this->_resource_identifier;
            if ($includeItemIdentifier !== 'no') {
                $identifiers[] = $prefix . ' ' . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier;
            }
        }

        // Check prefix with a space and a no-break space.
        if ($this->settings->get('cleanurl_identifier_unspace')) {
            $unspace = str_replace([' ', ' '], '', $prefix);
            if ($prefix != $unspace) {
                $identifiers[] = $unspace . $this->_resource_identifier;
                $identifiers[] = $unspace . ' ' . $this->_resource_identifier;
                if ($includeItemIdentifier !== 'no') {
                    $identifiers[] = $unspace . $itemIdentifier . $this->_resource_identifier;
                    $identifiers[] = $unspace . ' ' . $itemIdentifier . $this->_resource_identifier;
                }
                if ($includeItemSetIdentifierItem !== 'no' || $includeItemSetIdentifierMedia !== 'no') {
                    $identifiers[] = $unspace . $itemSetIdentifier . $this->_resource_identifier;
                    $identifiers[] = $unspace . ' ' . $itemSetIdentifier . $this->_resource_identifier;
                    if ($includeItemIdentifier !== 'no') {
                        $identifiers[] = $unspace . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier;
                        $identifiers[] = $unspace . ' ' . $itemSetIdentifier . $itemIdentifier . $this->_resource_identifier;
                    }
                }
            }
        }

        $identifiers[] = $this->_resource_identifier;

        $result = $this->queryResource($identifiers, $this->_item_set_id, $this->_item_id);
        if (!$result) {
            if ($this->_resource_name !== 'media') {
                throw new NotFoundException(sprintf(
                    'Resource not found. Check if the url "%s" should be skipped in Clean Url.', // @translate
                    strtok($this->event->getRequest()->getRequestUri(), '?')
                ));
            }
            // An exception may be thrown.
            $media = $this->notFoundMedia();
            $result = [
                'id' => $media->id(),
                'type' => \Omeka\Entity\Media::class,
            ];
        }

        if ($result['type'] === \Omeka\Entity\ItemSet::class) {
            $this->_item_set_id = $result['id'];
            return $this->itemSet();
        }

        return $this->forwardRouteMatch(
            $this->isAdmin ? 'admin/id' : 'site/resource-id',
            [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'site-slug' => $this->params['site-slug'],
                'controller' => $result['type'] === \Omeka\Entity\Media::class ? $this->namespaceMedia : $this->namespaceItem,
                'action' => 'show',
                'id' => $result['id'],
                'cleanurl_route' => $this->params['action'],
            ]
        );
    }

    protected function notFoundMedia()
    {
        // Here, it's probably an item and a media without identifier, so the
        // resource identifier is the item id / media.

        if ($this->_item_id) {
            $media = $this->retrieveMedia($this->_resource_identifier, $this->_item_id);
            if ($media) {
                return $media;
            }
        }

        throw new NotFoundException(sprintf(
            'Resource not found. Check if the url "%s" should be skipped in Clean Url.', // @translate
            strtok($this->event->getRequest()->getRequestUri(), '?')
        ));
    }

    /**
     * Get a resource id from a list of identifiers (item set, item or media).
     *
     * @todo Use the standard getResourceFromIdentifier().
     *
     * @param array $identifiers
     * @param int $itemSetId
     * @param int $itemId
     * @param string $resourceName
     * @return array|null Id and type of the resource.
     */
    protected function queryResource(array $identifiers, $itemSetId = null, $itemId = null, $resourceName = null)
    {
        // Adapted from \CleanUrl\View\Helper\GetResourcesFromIdentifiers().
        $identifiers = array_unique(array_filter($identifiers));
        if (!count($identifiers)) {
            return null;
        }

        $propertyId = (int) $this->settings->get('cleanurl_identifier_property');
        if (!$propertyId) {
            return null;
        }

        $parameters = [];

        $caseSensitiveIdentifier = (bool) $this->settings->get('cleanurl_identifier_case_sensitive');
        $collation = $caseSensitiveIdentifier ? 'COLLATE utf8mb4_bin' : '';

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select([
                'resource.id',
                'resource.resource_type AS type',
            ])
            ->from('resource', 'resource')
            ->leftJoin('resource', 'value', 'value', 'value.resource_id = resource.id')
            ->where($expr->eq('value.property_id', ':property_id'))
            ->andWhere($expr->in("value.value $collation", array_map([$this->connection, 'quote'], $identifiers)))
            ->addOrderBy('"id"', 'ASC')
            ->setMaxResults(1);

        $parameters['property_id'] = $propertyId;

        // Checks if url contains generic or true item set.
        if ($itemSetId) {
            switch ($resourceName) {
                case 'items':
                    $qb
                        ->join('resource', 'item_item_set', 'item_item_set', 'resource.id = item_item_set.item_id')
                        ->andWhere($expr->eq('item_item_set.item_set_id', ':item_set_id'));
                    $parameters['item_set_id'] = $itemSetId;
                    break;
                case 'media':
                    $qb
                        ->join('resource', 'media', 'media', 'resource.id = media.id')
                        ->join('resource', 'item_item_set', 'item_item_set', 'media.item_id = item_item_set.item_id')
                        ->andWhere($expr->eq('item_item_set.item_set_id', ':item_set_id'));
                    $parameters['item_set_id'] = $itemSetId;
                    break;
            }
        }

        if ($resourceName) {
            $apiAdapter = $this->apiAdapterManager->get($resourceName);
            $resourceType = $apiAdapter->getEntityClass();
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':resource_type'));
            $parameters['resource_type'] = $resourceType;
        }

        $qb
            ->setParameters($parameters);

        $stmt = $this->connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetch();

        // Additional check for item identifier: the media should belong to item.
        // TODO Include this in the query.
        if ($result && $resourceName == 'media' && !empty($this->_item_identifier)) {
            // Check if the found file belongs to the item.
            try {
                $media = $this->api->read('media', $result['id'])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return null;
            }
            if (!$this->_checkItemMedia($media)) {
                return null;
            }
        }

        return $result;
    }

    /**
     * Checks if a media belongs to an item set.
     *
     * @param MediaRepresentation $media Media to check.
     * @return bool
     */
    protected function _checkItemSetMedia(MediaRepresentation $media)
    {
        // Get the item.
        $item = $media->item();

        // Check if the found file belongs to the item set.
        if (!empty($this->_item_set_id)) {
            $itemSetsIds = array_map(function ($itemSet) {
                return $itemSet->id();
            }, $item->itemSets());
            if (!in_array($this->_item_set_id, $itemSetsIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a media belongs to an item.
     *
     * @param MediaRepresentation $media Media to check.
     * @return bool
     */
    protected function _checkItemMedia(MediaRepresentation $media)
    {
        // Get the item.
        $item = $media->item();

        // Check if the found file belongs to the item.
        if (!empty($this->_item_identifier)) {
            if ($item->id() != $this->_item_id) {
                return false;
            }

            // Get the full item identifier.
            $getResourceIdentifierHelper = $this->getResourceIdentifier;
            $itemIdentifier = $getResourceIdentifierHelper($item, false, false);
            if (mb_strtolower($this->_item_identifier) == mb_strtolower($itemIdentifier)) {
                return true;
            }

            // Get the item identifier.
            $itemIdentifier = $getResourceIdentifierHelper($item, false, true);
            if (mb_strtolower($this->_item_identifier) == mb_strtolower($itemIdentifier)) {
                return true;
            }

            return false;
        }

        return true;
    }

    protected function checkItemBelongsToItemSet(ItemRepresentation $item, $item_set_id)
    {
        if (empty($item_set_id)) {
            return;
        }

        $itemSetsIds = array_map(function ($itemSet) {
            return $itemSet->id();
        }, $item->itemSets());

        if (!in_array($item_set_id, $itemSetsIds)) {
            throw new NotFoundException;
        }
    }

    /**
     * @param string $mediaIdentifier
     * @param int $itemId
     * @return \Omeka\Api\Representation\MediaRepresentation|null
     */
    protected function retrieveMedia($mediaIdentifier, $itemId = null)
    {
        $undefined = $this->settings->get('cleanurl_media_media_undefined');
        if (!in_array($undefined, ['id', 'position'])) {
            $undefined = $this->settings->get('cleanurl_identifier_undefined');
        }
        switch ($undefined) {
            case 'exception':
                return null;
            case 'position':
                if (!$itemId) {
                    return null;
                }
                // Whatever the format, use the numeric character only: sprintf
                // cannot be reversed.
                $position = preg_replace('~\D~', '', $mediaIdentifier);
                $sql = 'SELECT id FROM media WHERE item_id = :item_id AND position = :position;';
                $stmt = $this->connection->prepare($sql);
                $stmt->bindValue('item_id', $itemId);
                $stmt->bindValue('position', $position);
                $stmt->execute();
                $id = $stmt->fetchColumn();
                if (!$id) {
                    return null;
                }
                // The id may be private.
                try {
                    return $this->api->read('media', $id)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
                break;
            case 'id':
            default:
                try {
                    return $this->api->read('media', $mediaIdentifier)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
        }
    }

    protected function _setItemSetId()
    {
        if ($this->_item_set_identifier) {
            $getResourceFromIdentifierHelper = $this->getResourceFromIdentifier;
            $resource = $getResourceFromIdentifierHelper($this->_item_set_identifier, 'item_sets');
            $this->_item_set_id = $resource ? $resource->id() : null;
        }
        return $this->_item_set_id;
    }

    protected function _setItemId()
    {
        if ($this->_item_identifier) {
            $getResourceFromIdentifierHelper = $this->getResourceFromIdentifier;
            $resource = $getResourceFromIdentifierHelper($this->_item_identifier, 'items');
            $this->_item_id = $resource ? $resource->id() : null;
        }
        return $this->_item_id;
    }

    protected function _setMediaId()
    {
        if ($this->_media_identifier) {
            $getResourceFromIdentifierHelper = $this->getResourceFromIdentifier;
            $resource = $getResourceFromIdentifierHelper($this->_media_identifier, 'media');
            $this->_media_id = $resource ? $resource->id() : null;
        }
        return $this->_media_id;
    }

    protected function allowShortIdentifierItem()
    {
        return (bool) array_intersect(array_merge($this->settings->get('cleanurl_item_allowed', []), $this->settings->get('cleanurl_media_allowed', [])), [
            'generic_item',
            'generic_item_media',
            'generic_item_media_full',
            'item_set_item',
            'item_set_item_media',
            'item_set_item_media_full',
        ]);
    }

    protected function allowFullIdentifierItem()
    {
        return (bool) array_intersect(array_merge($this->settings->get('cleanurl_item_allowed', []), $this->settings->get('cleanurl_media_allowed', [])), [
            'generic_item_full',
            'generic_item_full_media',
            'generic_item_full_media_full',
            'item_set_item_full',
            'item_set_item_full_media',
            'item_set_item_full_media_full',
        ]);
    }

    protected function allowShortIdentifierMedia()
    {
        return (bool) array_intersect($this->settings->get('cleanurl_media_allowed', []), [
            'generic_media',
            'generic_item_media',
            'generic_item_full_media',
            'item_set_media',
            'item_set_item_media',
            'item_set_item_full_media',
        ]);
    }

    protected function allowFullIdentifierMedia()
    {
        return (bool) array_intersect($this->settings->get('cleanurl_media_allowed', []), [
            'generic_media_full',
            'generic_item_media_full',
            'generic_item_full_media_full',
            'item_set_media_full',
            'item_set_item_media_full',
            'item_set_item_full_media_full',
        ]);
    }
}
