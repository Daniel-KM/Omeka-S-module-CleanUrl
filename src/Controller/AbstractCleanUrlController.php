<?php

namespace CleanUrl\Controller;

use Doctrine\DBAL\Connection;
use Omeka\Api\Adapter\Manager as ApiAdapterManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Mvc\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * The module controller for index pages.
 *
 * @todo Rebuild and simplify this controller.
 *
 * @package CleanUrl
 */
abstract class AbstractCleanUrlController extends AbstractActionController
{
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

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ApiAdapterManager
     */
    protected $apiAdapterManager;

    /**
     * @param Connection $connection
     * @param ApiAdapterManager $apiAdapterManager
     */
    public function __construct(Connection $connection, ApiAdapterManager $apiAdapterManager)
    {
        $this->connection = $connection;
        $this->apiAdapterManager = $apiAdapterManager;
    }

    public function itemSetShowAction()
    {
        $this->_item_set_identifier = $this->params('resource_identifier');
        $result = $this->_setItemSetId();
        if (empty($result)) {
            return $this->notFound();
        }
        return $this->forward()->dispatch($this->namespaceItem, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $this->namespaceItem,
            'action' => 'browse',
            'site-slug' => $this->params('site-slug'),
            'item-set-id' => $this->_item_set_id,
        ]);
    }

    public function itemsBrowseAction()
    {
        return $this->forward()->dispatch($this->namespaceItem, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $this->namespaceItem,
            'action' => 'browse',
            'site-slug' => $this->params('site-slug'),
        ]);
    }

    /**
     * Routes a clean url of an item to the default url.
     */
    public function routeItemAction()
    {
        return $this->routeItemSetItemAction();
    }

    /**
     * Routes a clean url of an item to the default url.
     */
    public function routeItemSetItemAction()
    {
        $this->_item_set_identifier = $this->params('item_set_identifier');
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
            try {
                $resource = $this->api()->read($this->_resource_name, $this->_resource_identifier)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return $this->notFound();
            }

            $this->checkItemBelongsToItemSet($resource, $this->_item_set_id);

            $id = $this->_resource_identifier;
        }

        $this->_resource_id = $id;

        return $this->forward()->dispatch($this->namespaceItem, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $this->namespaceItem,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $this->_resource_id,
        ]);
    }

    /**
     * Routes a clean url of a media to the default url.
     */
    public function routeMediaAction()
    {
        $this->_resource_name = 'media';
        $id = $this->_routeResource();

        // If no identifier exists, the module tries to use the identifier
        // specified ini the config, or the resource id directly.
        if (!$id) {
            $media = $this->retrieveMedia($this->_resource_identifier);
            if (!$media) {
                return $this->notFound();
            }
        }

        $this->_resource_id = $media->id();

        return $this->forward()->dispatch($this->namespaceMedia, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $this->namespaceMedia,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $this->_resource_id,
        ]);
    }

    /**
     * Routes a clean url of a media with item to the default url.
     */
    public function routeItemMediaAction()
    {
        return $this->routeItemSetItemMediaAction();
    }

    /**
     * Routes a clean url of a media with item to the default url.
     */
    public function routeItemSetMediaAction()
    {
        return $this->routeItemSetItemMediaAction();
    }

    /**
     * Routes a clean url of a media with item set and item to the default url.
     */
    public function routeItemSetItemMediaAction()
    {
        $this->_item_set_identifier = $this->params('item_set_identifier');
        // If 0, this is possible (item without item set, or generic route).
        $itemSetId = $this->_setItemSetId();
        if (is_null($itemSetId)) {
            return $this->notFound();
        }
        $this->_item_identifier = $this->params('item_identifier');
        // TODO Check if it is still the case.
        // If 0, this is possible (generic route).
        $itemId = $this->_setItemId();
        if (is_null($itemId)) {
            return $this->notFound();
        }
        $this->_resource_name = 'media';
        $id = $this->_routeResource();

        // If no identifier exists, the module tries to use the identifier
        // specified ini the config, or the resource id directly.
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

        return $this->forward()->dispatch($this->namespaceMedia, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $this->namespaceMedia,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $this->_resource_id,
        ]);
    }

    /**
     * Get id from the resource identifier.
     *
     * @todo Use the standard getResourceFromIdentifier().
     *
     * @return int Id of the resource.
     */
    protected function _routeResource()
    {
        $settings = $this->settings();

        $this->_resource_identifier = $this->params('resource_identifier');

        $identifiers = [];

        switch ($this->_resource_name) {
            case 'items':
                $allowShortIdentifier = $this->allowShortIdentifierItem();
                $allowFullIdentifier = $this->allowFullIdentifierItem();
                $includeItemSetIdentifier = $settings->get('cleanurl_item_item_set_included');
                $itemSetIdentifier = $this->_item_set_identifier && $includeItemSetIdentifier !== 'no'
                    ? $this->_item_set_identifier  . '/'
                    : '';
                $includeItemIdentifier = 'no';
                $itemIdentifier = '';
                break;
            case 'media':
                $allowShortIdentifier = $this->allowShortIdentifierMedia();
                $allowFullIdentifier = $this->allowFullIdentifierMedia();
                $includeItemSetIdentifier = $settings->get('cleanurl_media_item_set_included');
                $itemSetIdentifier = $this->_item_set_identifier && $includeItemSetIdentifier !== 'no'
                    ? $this->_item_set_identifier  . '/'
                    : '';
                $includeItemIdentifier = $settings->get('cleanurl_media_item_included');
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
            $prefix = $settings->get('cleanurl_identifier_prefix');
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
            if ($settings->get('cleanurl_identifier_unspace')) {
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
        $settings = $this->settings();

        // The difference with _routeResource() is that the resource name is
        // unknown and probably wrong.
        $includeItemSetIdentifierItem = $settings->get('cleanurl_item_item_set_included');
        $includeItemSetIdentifierMedia = $settings->get('cleanurl_media_item_set_included');
        $includeItemIdentifier = $settings->get('cleanurl_media_item_included');

        $itemSetIdentifier = $this->_item_set_identifier
            ? $this->_item_set_identifier  . '/'
            : '';
        $itemIdentifier = $this->_item_identifier
            ? $this->_item_identifier  . '/'
            : '';

        $identifiers = [];

        // Check the identifier of the record (commonly dcterms:identifier).
        $prefix = $settings->get('cleanurl_identifier_prefix');
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
        if ($settings->get('cleanurl_identifier_unspace')) {
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
                throw new NotFoundException;
            }
            // An exception may be thrown.
            $media = $this->notFoundMedia();
            $result = [
                'id' => $media->id(),
                'type' => \Omeka\Entity\Media::class,
            ];
        }

        if ($result['type'] === \Omeka\Entity\ItemSet::class) {
            return $this->forward()->dispatch($this->namespaceItem, [
                '__NAMESPACE__' => $this->namespace,
                $this->space => true,
                'controller' => $this->namespaceItem,
                'action' => 'browse',
                'site-slug' => $this->params('site-slug'),
                'item-set-id' => $result['id'],
            ]);
        }

        return $this->forward()->dispatch($this->namespaceMedia, [
            '__NAMESPACE__' => $this->namespace,
            $this->space => true,
            'controller' => $result['type'] === \Omeka\Entity\Media::class ? $this->namespaceMedia : $this->namespaceItem,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $result['id'],
        ]);
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

        throw new NotFoundException;
    }

    /**
     * Get a resource id from a list of identifiers.
     *
     * @todo Use the standard getResourceFromIdentifier().
     *
     * @return array|null Id and type of the resource.
     */
    protected function queryResource(array $identifiers, $itemSetId = null, $itemId = null, $resourceName = null)
    {
        $settings = $this->settings();

        $propertyId = (int) $settings->get('cleanurl_identifier_property');

        // Use of ordered placeholders.
        $bind = [];

        $identifiers = array_unique(array_filter($identifiers));
        $in = implode(',', array_fill(0, count($identifiers), '?'));

        $sqlFrom = 'FROM resource';

        // If the table is case sensitive, lower-case the search.
        if ($settings->get('cleanurl_identifier_case_insensitive')) {
            $identifiers = array_map('mb_strtolower', $identifiers);
            $sqlWhereValue =
                "AND LOWER(value.value) IN ($in)";
        }
        // Default.
        else {
            $sqlWhereValue =
                "AND value.value IN ($in)";
        }
        $bind = array_merge($bind, $identifiers);

        // Checks if url contains generic or true item set.
        $sqlWhereItemSet = '';
        if ($itemSetId) {
            switch ($resourceName) {
                case 'items':
                    $sqlFrom .= '
                        JOIN item_item_set ON (resource.id = item_item_set.item_id)
                    ';
                    $sqlWhereItemSet = 'AND item_item_set.item_set_id = ?';
                    $bind[] = $itemSetId;
                    break;

                case 'media':
                    $sqlFrom .= '
                        JOIN media ON (resource.id = media.id)
                        JOIN item_item_set ON (media.item_id = item_item_set.item_id)
                    ';
                    $sqlWhereItemSet = 'AND item_item_set.item_set_id = ?';
                    $bind[] = $itemSetId;
                    break;
            }
        }

        $sqlWhereResourceType = '';
        if ($resourceName) {
            $apiAdapter = $this->apiAdapterManager->get($resourceName);
            $resourceType = $apiAdapter->getEntityClass();
            $sqlWhereResourceType = 'AND resource.resource_type = ?';
            $bind[] = $resourceType;
        }

        $sql = "
            SELECT resource.id, resource.resource_type AS type
            $sqlFrom
                JOIN value ON (resource.id = value.resource_id)
            WHERE value.property_id = '$propertyId'
                $sqlWhereValue
                $sqlWhereItemSet
                $sqlWhereResourceType
            LIMIT 1
        ";

        $result = $this->connection->fetchAssoc($sql, $bind);

        // Additional check for item identifier: the media should belong to item.
        // TODO Include this in the query.
        if ($result && $resourceName == 'media' && !empty($this->_item_identifier)) {
            // Check if the found file belongs to the item.
            try {
                $media = $this->api()->read('media', $result['id'])->getContent();
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
            $getResourceIdentifier = $this->viewHelpers()->get('getResourceIdentifier');
            $itemIdentifier = $getResourceIdentifier($item, false, false);
            if (mb_strtolower($this->_item_identifier) == mb_strtolower($itemIdentifier)) {
                return true;
            }

            // Get the item identifier.
            $getResourceIdentifier = $this->viewHelpers()->get('getResourceIdentifier');
            $itemIdentifier = $getResourceIdentifier($item, false, true);
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
        $undefined = $this->settings()->get('cleanurl_media_media_undefined');
        if (!in_array($undefined, ['id', 'position'])) {
            $undefined = $this->settings()->get('cleanurl_identifier_undefined');
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
                    return $this->api()->read('media', $id)->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
                break;
            case 'id':
            default:
                try {
                    return $this->api()->read('media', $mediaIdentifier);
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
        }
    }

    protected function _setItemSetId()
    {
        if ($this->_item_set_identifier) {
            $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
            $resource = $getResourceFromIdentifier($this->_item_set_identifier, false, 'item_sets');
            $this->_item_set_id = $resource ? $resource->id() : null;
        }
        return $this->_item_set_id;
    }

    protected function _setItemId()
    {
        if ($this->_item_identifier) {
            $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
            if ($this->allowFullIdentifierItem()) {
                $resource = $getResourceFromIdentifier($this->_item_identifier, true, 'items');
                if (empty($resource) && $this->allowShortIdentifierItem()) {
                    $resource = $getResourceFromIdentifier($this->_item_identifier, false, 'items');
                }
            } else {
                $resource = $getResourceFromIdentifier($this->_item_identifier, false, 'items');
            }
            $this->_item_id = $resource ? $resource->id() : null;
        }
        return $this->_item_id;
    }

    protected function _setMediaId()
    {
        if ($this->_media_identifier) {
            $getResourceFromIdentifier = $this->viewHelpers()->get('getResourceFromIdentifier');
            if ($this->allowFullIdentifierMedia()) {
                $resource = $getResourceFromIdentifier($this->_media_identifier, true, 'media');
                if (empty($resource) && $this->allowShortIdentifierMedia()) {
                    $resource = $getResourceFromIdentifier($this->_media_identifier, false, 'media');
                }
            } else {
                $resource = $getResourceFromIdentifier($this->_media_identifier, false, 'media');
            }
            $this->_media_id = $resource ? $resource->id() : null;
        }
        return $this->_media_id;
    }

    protected function allowShortIdentifierItem()
    {
        return (bool) array_intersect(array_merge($this->settings()->get('cleanurl_item_allowed', []), $this->settings()->get('cleanurl_media_allowed', [])), [
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
        return (bool) array_intersect(array_merge($this->settings()->get('cleanurl_item_allowed', []), $this->settings()->get('cleanurl_media_allowed', [])), [
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
        return (bool) array_intersect($this->settings()->get('cleanurl_media_allowed', []), [
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
        return (bool) array_intersect($this->settings()->get('cleanurl_media_allowed', []), [
            'generic_media_full',
            'generic_item_media_full',
            'generic_item_full_media_full',
            'item_set_media_full',
            'item_set_item_media_full',
            'item_set_item_full_media_full',
        ]);
    }
}
