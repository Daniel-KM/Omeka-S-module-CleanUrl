<?php
namespace CleanUrl;
use Omeka\Module\AbstractModule;
/**
 * Clean Url
 *
 * Allows to have URL like http://example.com/my_item_set/dc:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2014
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

use Omeka\Event\Event;
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
    protected $settings = array(
        // 10 is the hard set id of "dcterms:identifier" in default install.
        'clean_url_identifier_property' => 10,
        'clean_url_identifier_prefix' => 'document:',
        'clean_url_identifier_unspace' => false,
        'clean_url_case_insensitive' => false,
        'clean_url_main_path' => '',
        'clean_url_item_set_generic' => '',
        'clean_url_item_default' => 'generic',
        'clean_url_item_allowed' => 'a:2:{i:0;s:7:"generic";i:1;s:8:"item_set";}',
        'clean_url_item_generic' => 'document/',
        'clean_url_media_default' => 'generic',
        'clean_url_media_allowed' => 'a:2:{i:0;s:7:"generic";i:1;s:13:"item_set_item";}',
        'clean_url_media_generic' => 'media/',
        'clean_url_display_admin_show_identifier' => true,
        'clean_url_route_plugins' => 'a:0:{}',
    );

    /**
     * Installs the plugin.
     */
    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        foreach ($this->settings as $name => $value) {
            $settings->set($name, $value);
        }
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

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addRoutes();
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

        $response = $api->search('properties');
        $properties = [];
        foreach ($response->getContent() as $p) {
            $properties[$p->id()] = $p->term();
        }
        asort($properties);

        $eventManager->setIdentifiers('CleanUrl');
        $responses = $eventManager->trigger('route_plugins');

        $route_plugins = [];
        foreach ($responses as $response) {
            foreach ($response as $key => $plugin) {
                $label = $plugin['plugin'];

                $module = $moduleManager->getModule($plugin['plugin']);
                if (!$module || $module->getState() != ModuleManager::STATE_ACTIVE) {
                    $label .= ' <em>(' . $translator->translate('inactive') . ')</em>';
                }

                $route_plugins[$key] = $label;
            }
        }

        $vars = [
            'settings' => $serviceLocator->get('Omeka\Settings'),
            'properties' => $properties,
            'route_plugins' => $route_plugins,
        ];
        return $renderer->render('config-form', $vars);
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
            ] as $posted)
        {
            $value = trim($post[$posted], ' /');
            $post[$posted] = empty($value) ? '' : trim($value) . '/';
        }

        // The default url should be allowed for items and media.
        $post['clean_url_item_allowed'][] = $post['clean_url_item_default'];
        $post['clean_url_item_allowed'] = array_values(array_unique($post['clean_url_item_allowed']));
        $post['clean_url_media_allowed'][] = $post['clean_url_media_default'];
        $post['clean_url_media_allowed'] = array_values(array_unique($post['clean_url_media_allowed']));

        foreach ($this->settings as $settingKey => $settingValue) {
            if (in_array($settingKey, [
                    'clean_url_item_allowed',
                    'clean_url_media_allowed',
                    'clean_url_route_plugins',
                ]))
            {
                $post[$settingKey] = empty($post[$settingKey])
                    ? serialize([])
                    : serialize($post[$settingKey]);
            }
            if (isset($post[$settingKey])) {
                $settings->set($settingKey, $post[$settingKey]);
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('Omeka\Controller\Admin\Item',
            'view.show.after', array($this, 'displayItemIdentifier'));
    }

    /**
     * Add the identifiant in the list.
     */
    public function displayItemIdentifier(Event $event)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        if ($settings->get('clean_url_display_admin_show_identifier')) {
            $view = $event->getTarget();
            $identifier = $view->getResourceIdentifier($view->item);

            echo '<div><span>'
                . $translator->translate('CleanUrl identifier:')
                . ' '
                . ($identifier ?: $translator->translate('none'))
                . '</span></div>';
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

        $routes = [];

        $mainPath = $settings->get('clean_url_main_path');
        $itemSetGeneric = $settings->get('clean_url_item_set_generic');
        $itemGeneric = $settings->get('clean_url_item_generic');
        $mediaGeneric = $settings->get('clean_url_media_generic');

        $allowedForItems = unserialize($settings->get('clean_url_item_allowed'));
        $allowedForMedia = unserialize($settings->get('clean_url_media_allowed'));

        // Note: order of routes is important: Zend checks from the last one
        // (most specific) to the first one (most generic).

        // Get all item sets identifiers with one query.
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $getResourceTypeIdentifiers = $viewHelpers->get('getResourceTypeIdentifiers');
        $itemSetsIdentifiers = $getResourceTypeIdentifiers('item_sets', false);

        if (!empty($itemSetsIdentifiers)) {
            // Use one regex for all item sets. Default is case insensitve.
            $itemSetsRegex = array_map('preg_quote', $itemSetsIdentifiers);
            // To avoid a bug with identifiers that contain a "/", that is not
            // escaped with preg_quote().
            $itemSetsRegex = '(' . str_replace('/', '\/', implode('|', $itemSetsRegex)) . ')';

            // Add an item set route.
            $route = '/s/:site-slug:/' . $mainPath . $itemSetGeneric;
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

            // Add a item set route for media.
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

            // Add a item set / item route for media.
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

            // Add a item set route for items.
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
            $route = '/s/:site-slug/' . $mainPath . $itemGeneric;
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
}
