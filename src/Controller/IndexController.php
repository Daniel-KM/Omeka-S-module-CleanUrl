<?php

namespace CleanUrl\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * The plugin controller for index pages.
 *
 * @package CleanUrl
 */
class IndexController extends AbstractActionController
{
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

    // Resolved route plugin.
    private $_routePlugin = '';
    // The allowed plugins.
    private $_routePlugins = array();

    public function itemsetShowAction()
    {
        $this->_item_set_identifier = rawurldecode($this->params('resource_identifier'));
        $result = $this->_setItemSetId();
        if (empty($result)) {
            throw new NotFoundException;
        }
        return $this->forward()->dispatch('Omeka\Controller\Site\ItemSet', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__' => true,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $this->_item_set_id,
        ]);
    }

    public function itemsBrowseAction()
    {
        return $this->forward()->dispatch('Omeka\Controller\Site\Item', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__' => true,
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
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $this->_item_set_identifier = rawurldecode($this->params('item_set_identifier'));
        // If 0, this is possible (item without item set, or generic route).
        $result = $this->_setItemSetId();
        if (is_null($result)) {
            throw new NotFoundException;
        }

        $this->_resource_name = 'items';
        $id = $this->_routeResource();

        // If no identifier exists, the plugin tries to use the record id directly.
        if (!$id) {
            $resource = $api->read($this->_resource_name, $this->_resource_identifier)->getContent();
            if (!$resource) {
                throw new NotFoundException;
            }

            $this->checkItemBelongsToItemSet($resource, $this->_item_set_id);

            $id = $this->_resource_identifier;
        }

        $this->_resource_id = $id;

        if ($this->_checkRoutePlugin()) {
            return $this->_forwardToPlugin();
        }

        return $this->forward()->dispatch('Omeka\Controller\Site\Item', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__', true,
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

        // If no identifier exists, the plugin tries to use the record id directly.
        if (!$id) {
            $resource = $api->read($this->_resource_name, $this->_resource_identifier);
            if (!$resource) {
                throw new NotFoundException;
            }

            $id = $this->_resource_identifier;
        }

        $this->_resource_id = $id;

        if ($this->_checkRoutePlugin()) {
            return $this->_forwardToPlugin();
        }

        return $this->forward()->dispatch('Omeka\Controller\Site\Media', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__' => true,
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
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $this->_item_set_identifier = rawurldecode($this->params('item_set_identifier'));
        // If 0, this is possible (item without item set, or generic route).
        $result = $this->_setItemSetId();
        if (is_null($result)) {
            throw new NotFoundException;
        }
        $this->_item_identifier = rawurldecode($this->params('item_identifier'));
        // If 0, this is possible (generic route).
        $result = $this->_setItemId();
        if (is_null($result)) {
            throw new NotFoundException;
        }
        $this->_resource_name = 'media';
        $id = $this->_routeResource();

        // If no identifier exists, the plugin tries to use the record id directly.
        if (!$id) {
            $response = $api->read($this->_resource_name, $this->_resource_identifier);
            $resource = $response->getContent();

            if (!$resource) {
                throw new NotFoundException;
            }

            // Check if the found media belongs to the item set.
            if (!$this->_checkItemSetMedia($resource)) {
                throw new NotFoundException;
            }

            // Check if the found file belongs to the item.
            if (!$this->_checkItemMedia($resource)) {
                throw new NotFoundException;
            }

            $id = $this->_resource_identifier;
        }

        $this->_resource_id = $id;

        if ($this->_checkRoutePlugin()) {
            return $this->_forwardToPlugin();
        }

        return $this->forward()->dispatch('Omeka\Controller\Site\Media', [
            '__NAMESPACE__' => 'Omeka\Controller\Site',
            '__SITE__' => true,
            'action' => 'show',
            'site-slug' => $this->params('site-slug'),
            'id' => $this->_resource_id,
        ]);
    }

    /**
     * Routes a clean url of an item or a media to the default url.
     *
     * Item sets are managed directly in itemSetShowAction().
     *
     * @return id
     *   Id of the record.
     */
    protected function _routeResource()
    {
        $serviceLocator = $this->getServiceLocator();
        $db = $serviceLocator->get('Omeka\Connection');
        $settings = $serviceLocator->get('Omeka\Settings');
        $apiAdapterManager = $serviceLocator->get('Omeka\ApiAdapterManager');
        $api = $serviceLocator->get('Omeka\ApiManager');

        $propertyId = (integer) $settings->get('clean_url_identifier_property');

        $this->_resource_identifier = rawurldecode($this->params('resource_identifier'));

        $sqlFrom = 'FROM resource';

        // Use of ordered placeholders.
        $bind = array();

        // Check the dublin core identifier of the record.
        $prefix = $settings->get('clean_url_identifier_prefix');
        $identifiers = array();
        $identifiers[] = $prefix . $this->_resource_identifier;
        // Check with a space between prefix and identifier too.
        $identifiers[] = $prefix . ' ' . $this->_resource_identifier;
        // Check prefix with a space and a no-break space.
        if ($settings->get('clean_url_identifier_unspace')) {
            $unspace = str_replace(array(' ', ' '), '', $prefix);
            if ($prefix != $unspace) {
                $identifiers[] = $unspace . $this->_resource_identifier;
                $identifiers[] = $unspace . ' ' . $this->_resource_identifier;
            }
        }
        $in = implode(',', array_fill(0, count($identifiers), '?'));

        // If the table is case sensitive, lower-case the search.
        if ($settings->get('clean_url_case_insensitive')) {
            $identifiers = array_map('strtolower', $identifiers);
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
        if ($this->_item_set_id) {
            switch ($this->_resource_name) {
                case 'items':
                    $sqlFrom .= '
                        JOIN item_item_set ON (resource.id = item_item_set.item_id)
                    ';
                    $sqlWhereItemSet = 'AND item_item_set.item_set_id = ?';
                    $bind[] = $this->_item_set_id;
                    break;

                case 'media':
                    $sqlFrom .= '
                        JOIN media ON (resource.id = media.id)
                        JOIN item_item_set ON (media.item_id = item_item_set.item_id)
                    ';
                    $sqlWhereItemSet = 'AND item_item_set.item_set_id = ?';
                    $bind[] = $this->_item_set_id;
                    break;
            }
        }

        $apiAdapter = $apiAdapterManager->get($this->_resource_name);
        $resourceType = $apiAdapter->getEntityClass();
        $sqlWhereResourceType = 'AND resource.resource_type = ?';
        $bind[] = $resourceType;

        $sql = "
            SELECT resource.id
            $sqlFrom
                JOIN value ON (resource.id = value.resource_id)
            WHERE value.property_id = '$propertyId'
                $sqlWhereValue
                $sqlWhereItemSet
                $sqlWhereResourceType
            LIMIT 1
        ";
        $id = $db->fetchColumn($sql, $bind);

        // Additional check for item identifier: the media should belong to item.
        // TODO Include this in the query.
        if ($id && !empty($this->_item_identifier) && $this->_resource_name == 'media') {
            // Check if the found file belongs to the item.
            $response = $api->read('media', $id);
            $media = $response->getContent();
            if (!$this->_checkItemMedia($media)) {
                return null;
            }
        }

        return $id;
    }

    /**
     * Checks if a media belongs to an item set.
     *
     * @param MediaRepresentation $media Media to check.
     *
     * @return boolean
     */
    protected function _checkItemSetMedia(MediaRepresentation $media)
    {
        // Get the item.
        $item = $media->item();

        // Check if the found file belongs to the item set.
        if (!empty($this->_item_set_id)) {
            $itemSetsIds = array_map(function($itemSet) {
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
     *
     * @return boolean
     */
    protected function _checkItemMedia($media)
    {
        // Get the item.
        $item = $media->item();

        // Check if the found file belongs to the item.
        if (!empty($this->_item_identifier)) {
            // Get the item identifier.
            $viewHelpers = $this->getServiceLocator()->get('ViewHelperManager');
            $getResourceIdentifier = $viewHelpers->get('getResourceIdentifier');
            $item_identifier = $getResourceIdentifier($item, false);
            // Check identifier and id of item.
            if (strtolower($this->_item_identifier) != strtolower($item_identifier)
                    && $this->_item_identifier != $item->id())
            {
                return false;
            }
        }

        return true;
    }

    protected function checkItemBelongsToItemSet(ItemRepresentation $item, $item_set_id)
    {
        if (empty($item_set_id)) {
            return;
        }

        $itemSetsIds = array_map(function($itemSet) {
            return $itemSet->id();
        }, $item->itemSets());

        if (!in_array($item_set_id, $itemSetsIds)) {
            throw new Omeka_Controller_Exception_404;
        }
    }

    protected function _setItemSetId()
    {
        if ($this->_item_set_identifier) {
            $viewHelpers = $this->getServiceLocator()->get('ViewHelperManager');
            $getResourceFromIdentifier = $viewHelpers->get('getResourceFromIdentifier');

            $itemSet = $getResourceFromIdentifier($this->_item_set_identifier, false, 'item_sets');
            $this->_item_set_id = $itemSet ? $itemSet->id() : null;
        }
        return $this->_item_set_id;
    }

    protected function _setItemId()
    {
        if ($this->_item_identifier) {
            $viewHelpers = $this->getServiceLocator()->get('ViewHelperManager');
            $getResourceFromIdentifier = $viewHelpers->get('getResourceFromIdentifier');

            $item = $getResourceFromIdentifier($this->_item_identifier, false, 'items');
            $this->_item_id = $item ? $item->id() : null;
        }
        return $this->_item_id;
    }

    protected function _setMediaId()
    {
        if ($this->_media_identifier) {
            $this->_media_id = $this->getView()->getResourceFromIdentifier($this->_media_identifier, false, 'media');
        }
        return $this->_media_id;
    }

    /**
     * Check if this is a route to a plugin.
     *
     * @return string|null The plugin route to use, else null.
     */
    protected function _checkRoutePlugin()
    {
        $routePlugin = $this->params('rp');
        if (empty($routePlugin)) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $allowed = unserialize($settings->get('clean_url_route_plugins')) ?: array();
        if (!in_array($routePlugin, $allowed)) {
            return;
        }

        $eventManager = $serviceLocator->get('EventManager');
        $eventManager->setIdentifiers('CleanUrl');
        $responses = $eventManager->trigger('route_plugins');
        $route_plugins = [];
        foreach ($responses as $response) {
            $route_plugins = array_merge($route_plugins, $response);
        }
        $this->_routePlugins = $route_plugins;
        if (!isset($this->_routePlugins[$routePlugin])) {
            return;
        }

        $plugin = $this->_routePlugins[$routePlugin];
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($plugin['plugin']);

        if ($module->getState() == ModuleManager::STATE_ACTIVE) {
            return;
        }

        if (!empty($plugin['resource_names'])
                && !in_array($this->_resource_name, $plugin['resource_names'])
            ) {
            return;
        }

        $this->_routePlugin = $routePlugin;

        return $routePlugin;
    }

    /**
     * Forward to a plugin.
     *
     * @return string|null The plugin route to use, else null.
     */
    protected function _forwardToPlugin()
    {
        $route = &$this->_routePlugins[$this->_routePlugin];

        $params = array_merge($this->params()->fromRoute(), $this->params()->fromQuery());
        unset($params['resource_identifier']);
        unset($params['rp']);

        if (isset($route['map']['id'])) {
            $params[$route['map']['id']] = $this->_resource_id;
        }
        if (isset($route['map']['type'])) {
            $params[$route['map']['type']] = $this->_resource_name;
        }

        $params = array_merge($params, $route['params']);

        return $this->forward()->dispatch($route['params']['controller'], $params);
    }
}
