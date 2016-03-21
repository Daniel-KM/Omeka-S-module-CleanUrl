<?php

namespace CleanUrl\View\Helper;

/**
 * Clean Url Get Record Full Identifier
 *
 * @todo Use a route name?
 * @see Omeka\View\Helper\RecordUrl.php
 */

use Zend\View\Helper\AbstractHelper;

/**
 * @package Omeka\Plugins\CleanUrl\views\helpers
 */
class GetResourceFullIdentifier extends AbstractHelper
{
    /**
     * Get clean url path of a record in the default or specified format.
     *
     * @param AbstractResourceRepresentation $resource
     * @param boolean $withMainPath
     * @param string $withBasePath Can be empty, 'admin', 'public' or
     * 'current'. If any, implies main path.
     * @param boolean $absoluteUrl If true, implies current / admin or public
     * path and main path.
     * @param string $format Format of the identifier (default one if empty).
     * @return string
     *   Full identifier of the record, if any, else empty string.
     */
    public function __invoke(
        $resource,
        $withMainPath = true,
        $withBasePath = 'current',
        $absolute = false,
        $format = null)
    {
        $view = $this->getView();
        $serviceLocator = $view->getHelperPluginManager()->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        switch ($resource->getResourceName()) {
            case 'item_sets':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    return '';
                }

                $generic = $settings->get('clean_url_itemset_generic');
                return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;

            case 'items':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                if (empty($format)) {
                    $format = $settings->get('clean_url_item_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'items')) {
                    return '';
                }

                if ($format === 'generic') {
                    $generic = $settings->get('clean_url_item_generic');
                    return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;
                }
                break;

            case 'media':
                $identifier = $view->getResourceIdentifier($resource);
                if (empty($identifier)) {
                    $identifier = $resource->id();
                }

                if (empty($format)) {
                    $format = $settings->get('clean_url_file_default');
                }
                // Else check if the format is allowed.
                elseif (!$this->_isFormatAllowed($format, 'media')) {
                    return '';
                }

                switch ($format) {
                    case 'generic':
                        $generic = $settings->get('clean_url_file_generic');
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $identifier;

                    case 'generic_item':
                        $generic = $settings->get('clean_url_file_generic');

                        $item = $resource->item();
                        $item_identifier = $view->getResourceIdentifier($item);
                        if (!$item_identifier) {
                            $item_identifier = $item->id();
                        }
                        return $this->_getUrlPath($absolute, $withMainPath, $withBasePath) . $generic . $item_identifier . '/' . $identifier;
                }
                break;
        }

        // This resource don't have a clean url.
        return '';
    }

    /**
     * Return beginning of the record name if needed.
     *
     * @param boolean $withMainPath
     * @param boolean $withBasePath Implies main path.
     * @return string
     * The string ends with '/'.
     */
    protected function _getUrlPath($absolute, $withMainPath, $withBasePath)
    {
        if ($absolute) {
            $withBasePath = empty($withBasePath) ? 'current' : $withBasePath;
            $withMainPath = true;
        }
        elseif ($withBasePath) {
            $withMainPath = true;
        }

        $view = $this->getView();

        $publicBasePath = $view->basePath();
        $adminBasePath = $view->basePath('admin');

        switch ($withBasePath) {
            case 'public':
                $basePath = $publicBasePath;
                break;

            case 'admin':
                $basePath = $adminBasePath;
                break;

            case 'current':
                $serviceLocator = $view->getHelperPluginManager()->getServiceLocator();
                $routeMatch = $serviceLocator->get('Application')->getMvcEvent()->getRouteMatch();
                if ($routeMatch->getParam('__ADMIN__')) {
                    $basePath = $adminBasePath;
                } else {
                    $basePath = $publicBasePath;
                }
                break;

            default:
                $basePath = '';
        }

        $mainPath = $withMainPath ? $settings->get('clean_url_main_path') : '';

        return ($absolute ? $this->getView()->serverUrl() : '') . $basePath . '/' . $mainPath;
    }

    /**
     * Check if a format is allowed for a record type.
     *
     * @param string $format
     * @param string $resourceName
     * @return boolean|null True if allowed, false if not, null if no format.
     */
    private function _isFormatAllowed($format, $resourceName)
    {
        if (empty($format)) {
            return;
        }

        switch ($resourceName) {
            case 'items':
                $allowedForItems = unserialize($settings->get('clean_url_items_allowed'));
                return in_array($format, $allowedForItems);

            case 'media':
                $allowedForMedia = unserialize($settings->get('clean_url_media_allowed'));
                return in_array($format, $allowedForMedia);
        }
    }

    /**
     * Return the generic format, if exists, for items or media.
     *
     * @param string $resourceName
     * @return string|null
     */
    private function _getGenericFormat($resourceName)
    {
        switch ($resourceName) {
            case 'items':
                $allowedForItems = unserialize($settings->get('clean_url_items_allowed'));
                if (in_array('generic', $allowedForItems)) {
                    return 'generic';
                }
                break;

            case 'media':
                $allowedForMedia = unserialize($settings->get('clean_url_media_allowed'));
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
