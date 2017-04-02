<?php

namespace CleanUrl\View\Helper;

/*
 * Clean Url Get Record Full Identifier
 *
 * @todo Use a route name?
 * @see Omeka\View\Helper\RecordUrl.php
 */

use Zend\Mvc\Application;
use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceFullIdentifier extends AbstractHelper
{
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Get clean url path of a record in the default or specified format.
     *
     * @param AbstractResourceRepresentation|array $resource
     * @param bool $withMainPath
     * @param string $withBasePath Can be empty, 'admin', 'public' or
     * 'current'. If any, implies main path.
     * @param bool $absoluteUrl If true, implies current / admin or public
     * path and main path.
     * @param string $format Format of the identifier (default one if empty).
     * @return string Full identifier of the record, if any, else empty string.
     */
    public function __invoke(
        $resource,
        $withMainPath = true,
        $withBasePath = 'current',
        $absolute = false,
        $format = null)
    {
        $view = $this->getView();

        if (is_array($resource)) {
            $resourceNames = [
                // Manage controller names.
                'item-set' => 'item_sets',
                'item' => 'items',
                'media' => 'media',
                // Manage api names too.
                'item_sets' => 'item_sets',
                'items' => 'items',
                'medias' => 'media',
                // Manage json ld types too.
                'o:ItemSet' => 'item_sets',
                'o:Item' => 'items',
                'o:Media' => 'media',
            ];
            if (!isset($resource['type'])
                || !isset($resource['id'])
                || !isset($resourceNames[$resource['type']])
            ) {
                return '';
            }

            $resource = $this->view->api()
                ->read(
                    $resourceNames[$resource['type']],
                    $resource['id']
                )
                ->getContent();
            if (empty($resource)) {
                return '';
            }
        }

        switch ($resource->resourceName()) {
            case 'item_sets':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    return '';
                }

                $generic = $view->setting('clean_url_item_set_generic');
                return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;

            case 'items':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                if (empty($format)) {
                    $format = $view->setting('clean_url_item_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'items')) {
                    return '';
                }

                switch ($format) {
                    case 'generic':
                        $generic = $view->setting('clean_url_item_generic');
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;

                    case 'item_set':
                        $itemSets = $resource->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('items');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }

                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $identifier;
                }
                break;

            case 'media':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                if (empty($format)) {
                    $format = $view->setting('clean_url_media_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'media')) {
                    return '';
                }

                switch ($format) {
                    case 'generic':
                        $generic = $view->setting('clean_url_media_generic');
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;

                    case 'generic_item':
                        $generic = $view->setting('clean_url_media_generic');

                        $item = $resource->item();
                        $item_identifier = $view->getResourceIdentifier($item);
                        if (!$item_identifier) {
                            $item_identifier = $item->id();
                        }
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $item_identifier . '/' . $identifier;

                    case 'item_set':
                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('media');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $identifier;

                    case 'item_set_item':
                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('media');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }
                        $itemIdentifier = $view->getResourceIdentifier($item);
                        if (!$itemIdentifier) {
                            $itemIdentifier = $item->id();
                        }
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $itemIdentifier . '/' . $identifier;
                }
                break;
        }

        // This resource doesn't have a clean url.
        return '';
    }

    /**
     * Return beginning of the record name if needed.
     *
     * @param bool $withMainPath
     * @param bool $withBasePath Implies main path.
     * @return string
     * The string ends with '/'.
     */
    protected function _getUrlPath($absolute, $withMainPath, $withBasePath)
    {
        if ($absolute) {
            $withBasePath = empty($withBasePath) ? 'current' : $withBasePath;
            $withMainPath = true;
        } elseif ($withBasePath) {
            $withMainPath = true;
        }

        if ($withBasePath == 'current') {
            $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
            $withBasePath = $routeMatch->getParam('__ADMIN__') ? 'admin' : 'public';
        }

        switch ($withBasePath) {
            case 'public':
                if (empty($routeMatch)) {
                    $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
                }
                $site_slug = $routeMatch->getParam('site-slug');
                $basePath = $this->view->basePath("s/$site_slug");
                break;

            case 'admin':
                $basePath = $this->view->basePath('admin');
                break;

            default:
                $basePath = '';
        }

        $mainPath = $withMainPath ? $this->view->setting('clean_url_main_path') : '';

        return ($absolute ? $this->view->serverUrl() : '') . $basePath . '/' . $mainPath;
    }

    /**
     * Check if a format is allowed for a record type.
     *
     * @param string $format
     * @param string $resourceName
     * @return bool|null True if allowed, false if not, null if no format.
     */
    protected function _isFormatAllowed($format, $resourceName)
    {
        if (empty($format)) {
            return;
        }

        switch ($resourceName) {
            case 'items':
                $allowedForItems = $this->view->setting('clean_url_item_allowed');
                return in_array($format, $allowedForItems);

            case 'media':
                $allowedForMedia = $this->view->setting('clean_url_media_allowed');
                return in_array($format, $allowedForMedia);
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
        switch ($resourceName) {
            case 'items':
                $allowedForItems = $this->view->setting('clean_url_item_allowed');
                if (in_array('generic', $allowedForItems)) {
                    return 'generic';
                }
                break;

            case 'media':
                $allowedForMedia = $this->view->setting('clean_url_media_allowed');
                if (in_array('generic_item', $allowedForMedia)) {
                    return 'generic_item';
                }
                if (in_array('generic', $allowedForMedia)) {
                    return 'generic';
                }
                break;
        }
    }
}
