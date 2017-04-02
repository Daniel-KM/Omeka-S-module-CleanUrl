<?php
namespace CleanUrl;

use Omeka\Module\AbstractModule;

/*
 * Clean Url
 *
 * Allows to have URL like http://example.com/my_item_set/dc:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

use CleanUrl\Service\ViewHelper\GetResourceTypeIdentifiersFactory;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * The Clean Url plugin.
 * @package Omeka\Plugins\CleanUrl
 */
class Module extends AbstractModule
{
    /**
     * @var array This plugin's options.
     */
    protected $settings = [
        // 10 is the hard set id of "dcterms:identifier" in default install.
        'clean_url_identifier_property' => 10,
        'clean_url_identifier_prefix' => 'document:',
        'clean_url_identifier_unspace' => false,
        'clean_url_case_insensitive' => false,
        'clean_url_main_path' => '',
        'clean_url_item_set_regex' => '',
        'clean_url_item_set_generic' => '',
        'clean_url_item_default' => 'generic',
        'clean_url_item_allowed' => ['generic', 'item_set'],
        'clean_url_item_generic' => 'document/',
        'clean_url_media_default' => 'generic',
        'clean_url_media_allowed' => ['generic', 'item_set_item'],
        'clean_url_media_generic' => 'media/',
        'clean_url_display_admin_show_identifier' => true,
    ];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addRoutes();
    }

    /**
     * Installs the plugin.
     */
    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        foreach ($this->settings as $name => $value) {
            $settings->set($name, $value);
        }

        $this->cacheItemSetsRegex($serviceLocator);
    }

    /**
     * Uninstalls the plugin.
     */
    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        foreach ($this->settings as $name => $value) {
            $settings->delete($name);
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '3.14', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');

            $settings->set('clean_url_identifier_property',
                (integer) $settings->get('clean_url_identifier_property'));

            $settings->set('clean_url_item_allowed',
                unserialize($settings->get('clean_url_item_allowed')));
            $settings->set('clean_url_media_allowed',
                unserialize($settings->get('clean_url_media_allowed')));

            $this->cacheItemSetsRegex($serviceLocator);
        }
    }

    /**
     * Shows plugin configuration page.
     */
    public function getConfigForm(PhpRenderer $renderer)
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');
        $eventManager = $serviceLocator->get('EventManager');
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $translator = $serviceLocator->get('MvcTranslator');

        return $renderer->render('clean-url/config-form');
    }

    /**
     * Saves plugin configuration page.
     *
     * @param array Options set in the config form.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        $post = $controller->getRequest()->getPost()->toArray();

        // Sanitize first.
        $post['clean_url_identifier_prefix'] = trim($post['clean_url_identifier_prefix']);
        foreach ([
                'clean_url_main_path',
                'clean_url_item_set_generic',
                'clean_url_item_generic',
                'clean_url_media_generic',
            ] as $posted) {
            $value = trim($post[$posted], ' /');
            $post[$posted] = empty($value) ? '' : trim($value) . '/';
        }

        $post['clean_url_identifier_property'] = (integer) $post['clean_url_identifier_property'];

        // The default url should be allowed for items and media.
        $post['clean_url_item_allowed'][] = $post['clean_url_item_default'];
        $post['clean_url_item_allowed'] = array_values(array_unique($post['clean_url_item_allowed']));
        $post['clean_url_media_allowed'][] = $post['clean_url_media_default'];
        $post['clean_url_media_allowed'] = array_values(array_unique($post['clean_url_media_allowed']));

        foreach ($this->settings as $settingKey => $settingValue) {
            if (isset($post[$settingKey])) {
                $settings->set($settingKey, $post[$settingKey]);
            }
        }

        $this->cacheItemSetsRegex($this->getServiceLocator());
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        if ($settings->get('clean_url_display_admin_show_identifier')) {
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\ItemSet',
                'view.show.after',
                [$this, 'displayViewResourceIdentifier']
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\Item',
                'view.show.after',
                [$this, 'displayViewResourceIdentifier']
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\Media',
                'view.show.after',
                [$this, 'displayViewResourceIdentifier']
            );
        }
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'displayViewEntityIdentifier']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.details',
            [$this, 'displayViewEntityIdentifier']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ValueRepresentation',
            'rep.value.html',
            [$this, 'repValueHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemSetAdapter',
            'api.create.post',
            [$this, 'afterSaveItemSet']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemSetAdapter',
            'api.update.post',
            [$this, 'afterSaveItemSet']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemSetAdapter',
            'api.delete.post',
            [$this, 'afterSaveItemSet']
        );
    }

    /**
     * Display an identifier.
     */
    public function displayViewResourceIdentifier(Event $event)
    {
        $resource = $event->getTarget()->resource;
        $this->displayResourceIdentifier($resource);
    }

    /**
     * Display an identifier.
     */
    public function displayViewEntityIdentifier(Event $event)
    {
        $resource = $event->getParam('entity');
        $this->displayResourceIdentifier($resource);
    }

    /**
     * Helepr to display an identifier.
     *
     * @param AbstractResourceRepresentation|Resource $resource
     */
    protected function displayResourceIdentifier($resource)
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $getResourceIdentifier = $services->get('ViewHelperManager')
            ->get('getResourceIdentifier');
        $identifier = $getResourceIdentifier($resource);

        echo '<div class="property meta-group"><h4>'
            . $translator->translate('CleanUrl identifier')
            . '</h4><div class="value">'
            . ($identifier ?: '<em>' . $translator->translate('[none]') . '</em>')
            . '</div></div>';
    }

    public function repValueHtml(Event $event)
    {
        $value = $event->getTarget();
        $params = $event->getParams();

        if ($value->type() == 'resource') {
            $resource = $value->valueResource();
            $viewHelperManager = $this->getServiceLocator()->get('ViewHelperManager');
            $getResourceFullIdentifier = $viewHelperManager->get('getResourceFullIdentifier');

            $url = $getResourceFullIdentifier($resource);
            if ($url) {
                $escapeHtml = $viewHelperManager->get('escapeHtml');
                $title = $escapeHtml($resource->displayTitle());
                $params['html'] = '<a href="' . $url . '">' . $title . '</a>';
            }
        }
    }

    /**
     * Defines public routes "main_path / my_item_set | generic / dc:identifier".
     *
     * @todo Rechecks performance of routes definition.
     */
    protected function addRoutes()
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $router = $serviceLocator->get('Router');

        if (!$router instanceof \Zend\Router\Http\TreeRouteStack) {
            return;
        }

        $routes = [];

        $mainPath = $settings->get('clean_url_main_path');
        $itemSetGeneric = $settings->get('clean_url_item_set_generic');
        $itemGeneric = $settings->get('clean_url_item_generic');
        $mediaGeneric = $settings->get('clean_url_media_generic');

        $allowedForItems = $settings->get('clean_url_item_allowed');
        $allowedForMedia = $settings->get('clean_url_media_allowed');

        // Note: order of routes is important: Zend checks from the last one
        // (most specific) to the first one (most generic).

        $itemSetsRegex = $settings->get('clean_url_item_set_regex');
        if (!empty($itemSetsRegex)) {
            // Add an item set route.
            $route = '/s/:site-slug/' . $mainPath . $itemSetGeneric;
            // Use one regex for all item sets. Default is case insensitve.
            $router->addRoute('cleanUrl_item_sets', [
                'type' => 'segment',
                'options' => [
                    'route' => $route . ':resource_identifier',
                    'constraints' => [
                        'resource_identifier' => $itemSetsRegex,
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'CleanUrl\Controller',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'item-set-show',
                    ],
                ],
            ]);

            // Add an item set route for media.
            if (in_array('item_set', $allowedForMedia)) {
                $router->addRoute('cleanUrl_item_sets_media', [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':item_set_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
                        'defaults' => [
                            '__NAMESPACE__' => 'CleanUrl\Controller',
                            '__SITE__' => true,
                            'controller' => 'Index',
                            'action' => 'route-item-set-media',
                        ],
                    ],
                ]);
            }

            // Add an item set / item route for media.
            if (in_array('item_set_item', $allowedForMedia)) {
                $router->addRoute('cleanUrl_item_sets_item_media', [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':item_set_identifier/:item_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
                        'defaults' => [
                            '__NAMESPACE__' => 'CleanUrl\Controller',
                            '__SITE__' => true,
                            'controller' => 'Index',
                            'action' => 'route-item-set-item-media',
                        ],
                    ],
                ]);
            }

            // Add an item set route for items.
            if (in_array('item_set', $allowedForItems)) {
                $router->addRoute('cleanUrl_item_sets_item', [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':item_set_identifier/:resource_identifier',
                        'constraints' => [
                            'item_set_identifier' => $itemSetsRegex,
                        ],
                        'defaults' => [
                            '__NAMESPACE__' => 'CleanUrl\Controller',
                            '__SITE__' => true,
                            'controller' => 'Index',
                            'action' => 'route-item-set-item',
                        ],
                    ],
                ]);
            }
        }

        // Add a generic route for media.
        if (in_array('generic', $allowedForMedia)) {
            $route = '/s/:site-slug/' . $mainPath . $mediaGeneric;
            $router->addRoute('cleanUrl_generic_media', [
                'type' => 'segment',
                'options' => [
                    'route' => $route . ':resource_identifier',
                    'defaults' => [
                        '__NAMESPACE__' => 'CleanUrl\Controller',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'route-media',
                        'item_set_id' => null,
                    ],
                ],
            ]);
        }

        // Add a generic / item route for media.
        if (in_array('generic_item', $allowedForMedia)) {
            $route = '/s/:site-slug/' . $mainPath . $mediaGeneric;
            $router->addRoute('cleanUrl_generic_item_media', [
                'type' => 'segment',
                'options' => [
                    'route' => $route . ':item_identifier/:resource_identifier',
                    'defaults' => [
                        '__NAMESPACE__' => 'CleanUrl\Controller',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'route-item-media',
                        'item_set_id' => null,
                    ],
                ],
            ]);
        }

        // Add a generic route for items.
        if (in_array('generic', $allowedForItems)) {
            $route = '/s/:site-slug/' . $mainPath . trim($itemGeneric, '/');
            $router->addRoute('cleanUrl_generic_items_browse', [
                'type' => 'segment',
                'options' => [
                    'route' => $route,
                    'defaults' => [
                        '__NAMESPACE__' => 'CleanUrl\Controller',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'items-browse',
                    ],
                ],
            ]);
            $router->addRoute('cleanUrl_generic_item', [
                'type' => 'segment',
                'options' => [
                    'route' => $route . '/:resource_identifier',
                    'defaults' => [
                        '__NAMESPACE__' => 'CleanUrl\Controller',
                        '__SITE__' => true,
                        'controller' => 'Index',
                        'action' => 'route-item',
                        'item_set_id' => null,
                    ],
                ],
            ]);
        }
    }

    /**
     * Process after saving or deleting an item set.
     *
     * @param Event $event
     */
    public function afterSaveItemSet(Event $event)
    {
        $this->cacheItemSetsRegex($this->getServiceLocator());
    }

    /**
     * Cache item set identifiers as string to speed up routing.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function cacheItemSetsRegex(ServiceLocatorInterface $serviceLocator)
    {
        // Get all item set identifiers with one query.
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        // The view helper is not available during intall, upgrade and tests.
        if ($viewHelpers->has('getResourceTypeIdentifiers')) {
            $getResourceTypeIdentifiers = $viewHelpers->get('getResourceTypeIdentifiers');
            $itemSetIdentifiers = $getResourceTypeIdentifiers('item_sets', false);
        } else {
            $getResourceTypeIdentifiers = $this->getViewHelperRTI($serviceLocator);
            $itemSetIdentifiers = $getResourceTypeIdentifiers->__invoke('item_sets', false);
        }

        // To avoid issues with identifiers that contain another identifier,
        // for example "item_set_bis" contains "item_set", they are ordered
        // by reversed length.
        array_multisort(
            array_map('strlen', $itemSetIdentifiers),
            $itemSetIdentifiers
        );
        $itemSetIdentifiers = array_reverse($itemSetIdentifiers);

        $itemSetsRegex = array_map('preg_quote', $itemSetIdentifiers);
        // To avoid a bug with identifiers that contain a "/", that is not
        // escaped with preg_quote().
        $itemSetsRegex = str_replace('/', '\/', implode('|', $itemSetsRegex));

        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->set('clean_url_item_set_regex', $itemSetsRegex);
    }

    /**
     * Get the view helper getResourceTypeIdentifiers with some params.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return \CleanUrl\View\Helper\GetResourceTypeIdentifiers
     */
    protected function getViewHelperRTI(ServiceLocatorInterface $serviceLocator)
    {
        require_once __DIR__
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'Service'
            . DIRECTORY_SEPARATOR . 'ViewHelper'
            . DIRECTORY_SEPARATOR . 'GetResourceTypeIdentifiersFactory.php';

        require_once __DIR__
            . DIRECTORY_SEPARATOR . 'src'
            . DIRECTORY_SEPARATOR . 'View'
            . DIRECTORY_SEPARATOR . 'Helper'
            . DIRECTORY_SEPARATOR . 'GetResourceTypeIdentifiers.php';

        $settings = $serviceLocator->get('Omeka\Settings');
        $propertyId = (integer) $settings->get('clean_url_identifier_property');
        $prefix = $settings->get('clean_url_identifier_prefix');

        $factory = new GetResourceTypeIdentifiersFactory();
        $helper = $factory(
            $serviceLocator,
            'getResourceTypeIdentifiers',
            [
                'propertyId' => $propertyId,
                'prefix' => $prefix,
            ]
        );
        return $helper;
    }
}
