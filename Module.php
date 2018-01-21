<?php
namespace CleanUrl;

/*
 * Clean Url
 *
 * Allows to have URL like http://example.com/my_item_set/dc:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2017
 * @copyright BibLibre, 2016-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

use CleanUrl\Form\ConfigForm;
use CleanUrl\Service\ViewHelper\GetResourceTypeIdentifiersFactory;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
        $this->addRoutes();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
        $this->cacheItemSetsRegex($serviceLocator);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $config = include __DIR__ . '/config/module.config.php';

        if (version_compare($oldVersion, '3.14', '<')) {
            $settings->set('clean_url_identifier_property',
                (integer) $settings->get('clean_url_identifier_property'));

            $settings->set('clean_url_item_allowed',
                unserialize($settings->get('clean_url_item_allowed')));
            $settings->set('clean_url_media_allowed',
                unserialize($settings->get('clean_url_media_allowed')));

            $this->cacheItemSetsRegex($serviceLocator);
        }

        if (version_compare($oldVersion, '3.15.3', '<')) {
            foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
                $oldName = str_replace('cleanurl_', 'clean_url_', $name);
                $settings->set($name, $settings->get($oldName, $value));
                $settings->delete($oldName);
            }
        }

        if (version_compare($oldVersion, '3.15.5', '<')) {
            $settings->set('cleanurl_use_admin',
                $config[strtolower(__NAMESPACE__)]['config']['cleanurl_use_admin']);
        }
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $formElementManager = $services->get('FormElementManager');

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data['clean_url_identifiers'][$name] = $settings->get($name);
            $data['clean_url_main_path'][$name] = $settings->get($name);
            $data['clean_url_item_sets'][$name] = $settings->get($name);
            $data['clean_url_items'][$name] = $settings->get($name);
            $data['clean_url_medias'][$name] = $settings->get($name);
            $data['clean_url_admin'][$name] = $settings->get($name);
        }

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        $form->setData($data);

        return $renderer->render('clean-url/module/config', [
            'form' => $form,
        ]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $this->getServiceLocator()->get('FormElementManager')
            ->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = array_merge(
            $params['clean_url_identifiers'],
            $params['clean_url_main_path'],
            $params['clean_url_item_sets'],
            $params['clean_url_items'],
            $params['clean_url_medias'],
            $params['clean_url_admin']
        );

        // TODO Move the post-checks into the form.

        // Sanitize first.
        $params['cleanurl_identifier_prefix'] = trim($params['cleanurl_identifier_prefix']);
        foreach ([
            'cleanurl_main_path',
            'cleanurl_item_set_generic',
            'cleanurl_item_generic',
            'cleanurl_media_generic',
        ] as $posted) {
            $value = trim(trim($params[$posted]), ' /');
            $params[$posted] = empty($value) ? '' : trim($value) . '/';
        }

        $params['cleanurl_identifier_property'] = (integer) $params['cleanurl_identifier_property'];

        // The default url should be allowed for items and media.
        $params['cleanurl_item_allowed'][] = $params['cleanurl_item_default'];
        $params['cleanurl_item_allowed'] = array_values(array_unique($params['cleanurl_item_allowed']));
        $params['cleanurl_media_allowed'][] = $params['cleanurl_media_default'];
        $params['cleanurl_media_allowed'] = array_values(array_unique($params['cleanurl_media_allowed']));

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (isset($defaultSettings[$name])) {
                $settings->set($name, $value);
            }
        }

        $this->cacheItemSetsRegex($this->getServiceLocator());
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        // Allow all access to the controller, because there will be a forward.
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $acl->allow(null, [Controller\Site\CleanUrlController::class]);
        $roles = $acl->getRoles();
        $acl->allow($roles, [Controller\Admin\CleanUrlController::class]);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        if ($settings->get('cleanurl_display_admin_show_identifier')) {
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\ItemSet',
                'view.show.sidebar',
                [$this, 'displayViewResourceIdentifier']
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\Item',
                'view.show.sidebar',
                [$this, 'displayViewResourceIdentifier']
            );
            $sharedEventManager->attach(
                'Omeka\Controller\Admin\Media',
                'view.show.sidebar',
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
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.create.post',
            [$this, 'afterSaveItemSet']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.update.post',
            [$this, 'afterSaveItemSet']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
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
     * Helper to display an identifier.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|Resource $resource
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

    /**
     * Defines public routes "main_path / my_item_set | generic / dcterms:identifier".
     *
     * @todo Rechecks performance of routes definition.
     */
    protected function addRoutes()
    {
        $serviceLocator = $this->getServiceLocator();
        $router = $serviceLocator->get('Router');
        if (!$router instanceof \Zend\Router\Http\TreeRouteStack) {
            return;
        }

        $settings = $serviceLocator->get('Omeka\Settings');
        $mainPath = $settings->get('cleanurl_main_path');
        $itemSetGeneric = $settings->get('cleanurl_item_set_generic');
        $itemGeneric = $settings->get('cleanurl_item_generic');
        $mediaGeneric = $settings->get('cleanurl_media_generic');

        $allowedForItems = $settings->get('cleanurl_item_allowed');
        $allowedForMedia = $settings->get('cleanurl_media_allowed');

        $itemSetsRegex = $settings->get('cleanurl_item_set_regex');

        // Note: order of routes is important: Zend checks from the last one
        // (most specific) to the first one (most generic).

        $baseRoutes['_public'] = [
            '/s/:site-slug/',
            '__SITE__',
            'CleanUrl\Controller\Site',
        ];
        if ($settings->get('cleanurl_use_admin')) {
            $baseRoutes['_admin'] = [
                '/admin/',
                '__ADMIN__',
                'CleanUrl\Controller\Admin',
            ];
        }

        foreach ($baseRoutes as $routeExt => $array) {
            list($baseRoute, $space, $namespaceController) = $array;
            if (!empty($itemSetsRegex)) {
                // Add an item set route.
                $route = $baseRoute . $mainPath . $itemSetGeneric;
                // Use one regex for all item sets. Default is case insensitve.
                $router->addRoute('cleanUrl_item_sets' . $routeExt, [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':resource_identifier',
                        'constraints' => [
                            'resource_identifier' => $itemSetsRegex,
                        ],
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'item-set-show',
                        ],
                    ],
                ]);

                // Add an item set route for media.
                if (in_array('item_set', $allowedForMedia)) {
                    $router->addRoute('cleanUrl_item_sets_media' . $routeExt, [
                        'type' => 'segment',
                        'options' => [
                            'route' => $route . ':item_set_identifier/:resource_identifier',
                            'constraints' => [
                                'item_set_identifier' => $itemSetsRegex,
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => $namespaceController,
                                $space => true,
                                'controller' => 'CleanUrlController',
                                'action' => 'route-item-set-media',
                            ],
                        ],
                    ]);
                }

                // Add an item set / item route for media.
                if (in_array('item_set_item', $allowedForMedia)) {
                    $router->addRoute('cleanUrl_item_sets_item_media' . $routeExt, [
                        'type' => 'segment',
                        'options' => [
                            'route' => $route . ':item_set_identifier/:item_identifier/:resource_identifier',
                            'constraints' => [
                                'item_set_identifier' => $itemSetsRegex,
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => $namespaceController,
                                $space => true,
                                'controller' => 'CleanUrlController',
                                'action' => 'route-item-set-item-media',
                            ],
                        ],
                    ]);
                }

                // Add an item set route for items.
                if (in_array('item_set', $allowedForItems)) {
                    $router->addRoute('cleanUrl_item_sets_item' . $routeExt, [
                        'type' => 'segment',
                        'options' => [
                            'route' => $route . ':item_set_identifier/:resource_identifier',
                            'constraints' => [
                                'item_set_identifier' => $itemSetsRegex,
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => $namespaceController,
                                $space => true,
                                'controller' => 'CleanUrlController',
                                'action' => 'route-item-set-item',
                            ],
                        ],
                    ]);
                }
            }

            // Add a generic route for media.
            if (in_array('generic', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $router->addRoute('cleanUrl_generic_media' . $routeExt, [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-media',
                            'item_set_id' => null,
                        ],
                    ],
                ]);
            }

            // Add a generic / item route for media.
            if (in_array('generic_item', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $router->addRoute('cleanUrl_generic_item_media' . $routeExt, [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . ':item_identifier/:resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item-media',
                            'item_set_id' => null,
                        ],
                    ],
                ]);
            }

            // Add a generic route for items.
            if (in_array('generic', $allowedForItems)) {
                $route = $baseRoute . $mainPath . trim($itemGeneric, '/');
                $router->addRoute('cleanUrl_generic_items_browse' . $routeExt, [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route,
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'items-browse',
                        ],
                    ],
                ]);
                $router->addRoute('cleanUrl_generic_item' . $routeExt, [
                    'type' => 'segment',
                    'options' => [
                        'route' => $route . '/:resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item',
                            'item_set_id' => null,
                        ],
                    ],
                ]);
            }
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
        $settings->set('cleanurl_item_set_regex', $itemSetsRegex);
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
        $propertyId = (integer) $settings->get('cleanurl_identifier_property');
        $prefix = $settings->get('cleanurl_identifier_prefix');

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
