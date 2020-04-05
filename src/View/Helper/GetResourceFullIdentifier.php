<?php

namespace CleanUrl\View\Helper;

/*
 * Get resource full identifier
 *
 * @todo Use a route name?
 * @see Omeka\View\Helper\CleanUrl.php
 */

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_SITE;
use const CleanUrl\SLUG_SITE_DEFAULT;
use const CleanUrl\SLUGS_SITE;

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
     * Get clean url path of a resource in the default or specified format.
     *
     * @todo Replace by standard routing assemble.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|array $resource
     * @param string $siteSlug May be required on main public pages.
     * @param bool $withMainPath
     * @param string $withBasePath Can be empty, 'admin', 'public' or
     * 'current'. If any, implies main path.
     * @param bool $absoluteUrl If true, implies current / admin or public
     * path and main path.
     * @param string $format Format of the identifier (default one if empty).
     * @return string Full identifier of the resource if any, else empty string.
     */
    public function __invoke(
        $resource,
        $siteSlug = null,
        $withMainPath = true,
        $withBasePath = 'current',
        $absolute = false,
        $format = null
    ) {
        $view = $this->getView();

        if (is_array($resource)) {
            $resourceNames = [
                // Manage api names.
                'item_sets' => 'item_sets',
                'items' => 'items',
                'medias' => 'media',
                // Manage json ld types too.
                'o:ItemSet' => 'item_sets',
                'o:Item' => 'items',
                'o:Media' => 'media',
                // Manage controller names too.
                'item-set' => 'item_sets',
                'item' => 'items',
                'media' => 'media',
            ];
            if (!isset($resource['type'])
                || !isset($resource['id'])
                || !isset($resourceNames[$resource['type']])
            ) {
                return '';
            }

            try {
                $resource = $this->view->api()
                    ->read(
                        $resourceNames[$resource['type']],
                        ['id' => $resource['id']]
                    )
                    ->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return '';
            }
        }

        switch ($resource->resourceName()) {
            case 'item_sets':
                $identifier = $view->getResourceIdentifier($resource, true, true);
                if (empty($identifier)) {
                    return '';
                }

                $generic = $view->setting('cleanurl_item_set_generic');
                return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $generic . $identifier;

            case 'items':
                if (empty($format)) {
                    $format = $view->setting('cleanurl_item_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'items')) {
                    return '';
                }

                $identifier = $view->getResourceIdentifier($resource, true, true);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                switch ($format) {
                    case 'generic_item':
                        $generic = $view->setting('cleanurl_item_generic');
                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $generic . $identifier;

                    case 'item_set_item':
                        $itemSets = $resource->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet, true, true);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('items');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $siteSlug, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }

                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $identifier;

                    default:
                        break;
                }
                break;

            case 'media':
                if (empty($format)) {
                    $format = $view->setting('cleanurl_media_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'media')) {
                    return '';
                }

                $identifier = $view->getResourceIdentifier($resource, true, true);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                switch ($format) {
                    case 'generic_media':
                        $generic = $view->setting('cleanurl_media_generic');
                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $generic . $identifier;

                    case 'generic_item_media':
                        $generic = $view->setting('cleanurl_media_generic');

                        $item = $resource->item();
                        $item_identifier = $view->getResourceIdentifier($item, true, true);
                        if (!$item_identifier) {
                            $item_identifier = $item->id();
                        }
                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $generic . $item_identifier . '/' . $identifier;

                    case 'item_set_media':
                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet, true, true);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('media');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $siteSlug, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }
                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $identifier;

                    case 'item_set_item_media':
                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        $itemSetIdentifier = null;
                        if (!empty($itemSets)) {
                            $itemSet = reset($itemSets);
                            $itemSetIdentifier = $view->getResourceIdentifier($itemSet, true, true);
                        }
                        if (empty($itemSetIdentifier)) {
                            $genericFormat = $this->_getGenericFormat('media');
                            if ($genericFormat) {
                                return $view->getResourceFullIdentifier($resource, $siteSlug, $withMainPath, $withBasePath, $absolute, $genericFormat);
                            }
                            return '';
                        }
                        $itemIdentifier = $view->getResourceIdentifier($item, true, true);
                        if (!$itemIdentifier) {
                            $itemIdentifier = $item->id();
                        }
                        return $this->_getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath) . $itemSetIdentifier . '/' . $itemIdentifier . '/' . $identifier;

                    default:
                        break;
                }
                break;

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
     * @param bool $withMainPath
     * @param bool $withBasePath Implies main path.
     * @return string The string ends with '/'.
     */
    protected function _getUrlPath($siteSlug, $absolute, $withMainPath, $withBasePath)
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
                if (strlen($siteSlug)) {
                    if (SLUG_MAIN_SITE && $siteSlug === SLUG_MAIN_SITE) {
                        $siteSlug = '';
                    }
                } else {
                    if (empty($routeMatch)) {
                        $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
                    }
                    $siteSlug = $routeMatch->getParam('site-slug');
                    if (SLUG_MAIN_SITE && $siteSlug === SLUG_MAIN_SITE) {
                        $siteSlug = '';
                    }
                }
                if (mb_strlen($siteSlug)) {
                    // The check of "slugs_site" may avoid an issue when empty,
                    // after install or during/after upgrade.
                    $basePath = $this->view->basePath(
                        (mb_strlen(SLUGS_SITE) || mb_strlen(SLUG_SITE) ? SLUG_SITE : SLUG_SITE_DEFAULT) . $siteSlug
                    );
                } else {
                    $basePath = $this->view->basePath();
                }
                break;

            case 'admin':
                $basePath = $this->view->basePath('admin');
                break;

            default:
                $basePath = '';
        }

        $mainPath = $withMainPath ? $this->view->setting('cleanurl_main_path') : '';

        return ($absolute ? $this->view->serverUrl() : '') . $basePath . '/' . $mainPath;
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

        switch ($resourceName) {
            case 'items':
                $allowedForItems = $this->view->setting('cleanurl_item_allowed');
                return in_array($format, $allowedForItems);

            case 'media':
                $allowedForMedia = $this->view->setting('cleanurl_media_allowed');
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
        switch ($resourceName) {
            case 'items':
                $allowedForItems = $this->view->setting('cleanurl_item_allowed');
                return in_array('generic_item', $allowedForItems)
                    ? 'generic_item'
                    : null;

            case 'media':
                $allowedForMedia = $this->view->setting('cleanurl_media_allowed');
                if (in_array('generic_item_media', $allowedForMedia)) {
                    return 'generic_item_media';
                }
                return in_array('generic_media', $allowedForMedia)
                    ? 'generic_media'
                    : null;

            default:
                return null;
        }
    }
}
