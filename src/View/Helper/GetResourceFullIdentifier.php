<?php

namespace CleanUrl\View\Helper;

/*
 * Get resource full identifier
 *
 * @todo Use CleanRoute (but it's for the full identifier).
 *
 * @see Omeka\View\Helper\CleanUrl.php
 */

use const CleanUrl\SLUG_MAIN_SITE;
use const CleanUrl\SLUG_SITE;
use const CleanUrl\SLUG_SITE_DEFAULT;
use const CleanUrl\SLUGS_SITE;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
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
     * @param string $withBasePath Can be empty, "admin", "public" or "current".
     * @param bool $withMainPath
     * @param bool $absoluteUrl
     * @param string $format Format of the identifier (default one if empty).
     * @return string Full identifier of the resource if any, else empty string.
     */
    public function __invoke(
        $resource,
        $siteSlug = null,
        $withBasePath = 'current',
        $withMainPath = true,
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

        $setting = $view->plugin('setting');

        switch ($resource->resourceName()) {
            case 'item_sets':
                $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                $identifier = $view->getResourceIdentifier($resource, $urlEncode, true);
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
                $identifier = $view->getResourceIdentifier($resource, $urlEncode, $skipPrefixItem);
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
                                ? $view->getResourceFullIdentifier($resource, $siteSlug, $withBasePath, $withMainPath, $absolute, $format)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $view->getResourceIdentifier($itemSet, $urlEncode, true);
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
                $identifier = $view->getResourceIdentifier($resource, $urlEncode, $skipPrefixMedia);
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
                            $api = $this->application->getServiceManager()->get('Omeka\ApiManager');
                            $position = $api->read('media', ['id' => $resource->id()], [], ['responseContent' => 'resource'])->getContent()
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
                            $allowedForMedia = $this->view->setting('cleanurl_media_allowed', []);
                            $result = array_intersect([
                                'generic_item_media',
                                'generic_item_full_media',
                                'generic_item_media_full',
                                'generic_item_full_media_full',
                            ], $allowedForMedia);
                            return $result
                                ? $view->getResourceFullIdentifier($resource, $siteSlug, $withBasePath, $withMainPath, $absolute, reset($result))
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
                        $itemIdentifier = $view->getResourceIdentifier($item, $urlEncode, $skipPrefixItem);
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
                            $allowedForMedia = $this->view->setting('cleanurl_media_allowed', []);
                            $result = array_intersect([
                                'item_set_item_media',
                                'item_set_item_full_media',
                                'item_set_item_media_full',
                                'item_set_item_full_media_full',
                            ], $allowedForMedia);
                            return $result
                                ? $view->getResourceFullIdentifier($resource, $siteSlug, $withBasePath, $withMainPath, $absolute, reset($result))
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $item = $resource->item();
                        $itemSets = $item->itemSets();
                        if (empty($itemSets)) {
                            $format = $this->_getGenericFormat('media');
                            return $format
                                ? $view->getResourceFullIdentifier($resource, $siteSlug, $withBasePath, $withMainPath, $absolute, $format)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $view->getResourceIdentifier($itemSet, $urlEncode, true);
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
                                ? $view->getResourceFullIdentifier($resource, $siteSlug, $withBasePath, $withMainPath, $absolute, $format)
                                : $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                        }

                        $itemSet = reset($itemSets);
                        $urlEncode = !$setting('cleanurl_item_set_keep_raw');
                        $itemSetIdentifier = $view->getResourceIdentifier($itemSet, $urlEncode, true);
                        if (empty($itemSetIdentifier)) {
                            $itemSetUndefined = $setting('cleanurl_media_item_set_undefined');
                            if ($itemSetUndefined !== 'parent_id') {
                                return $this->urlNoIdentifier($resource, $siteSlug, $absolute, $withBasePath, $withMainPath);
                            }
                            $itemSetIdentifier = $itemSet->id();
                        }

                        $urlEncode = !$setting('cleanurl_item_keep_raw');
                        $skipPrefixItem = !strpos($format, 'item_full');
                        $itemIdentifier = $view->getResourceIdentifier($item, $urlEncode, $skipPrefixItem);
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
            $withBasePath = $this->view->status()->isAdminRequest() ? 'admin' : 'public';
        }

        switch ($withBasePath) {
            case 'public':
                if (strlen($siteSlug)) {
                    if (SLUG_MAIN_SITE && $siteSlug === SLUG_MAIN_SITE) {
                        $siteSlug = '';
                    }
                } else {
                    $routeMatch = $this->application->getMvcEvent()->getRouteMatch();
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

        $mainPath = $withMainPath ? $this->view->setting('cleanurl_main_path_full') : '';

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
                $allowedForItems = $this->view->setting('cleanurl_item_allowed', []);
                return in_array($format, $allowedForItems);

            case 'media':
                $allowedForMedia = $this->view->setting('cleanurl_media_allowed', []);
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
                $allowedForItems = $this->view->setting('cleanurl_item_allowed', []);
                $result = array_intersect([
                    'generic_item',
                    'generic_item_full',
                ], $allowedForItems);
                return $result
                    ? reset($result)
                    : null;

            case 'media':
                $allowedForMedia = $this->view->setting('cleanurl_media_allowed', []);
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
        switch ($this->view->setting('cleanurl_identifier_undefined')) {
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
                if (!$this->view->status()->isAdminRequest()) {
                    $message = new \Omeka\Stdlib\Message('The "%1$s" #%2$d has no normalized identifier.', $resource->getControllerName(), $resource->id()); // @translate
                    throw new \Omeka\Mvc\Exception\RuntimeException($message);
                }
                // no break.
            case 'default':
            default:
                return $this->_getUrlPath($siteSlug, $absolute, $withBasePath, false) . $resource->getControllerName() . '/' . $resource->id();
        }
    }
}
