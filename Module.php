<?php
namespace CleanUrl;

/*
 * Clean Url
 *
 * Allows to have URL like http://example.com/my_item_set/dc:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2019
 * @copyright BibLibre, 2016-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

require_once file_exists(OMEKA_PATH . '/config/routes.main_slug.php')
    ? OMEKA_PATH . '/config/routes.main_slug.php'
    : __DIR__ . '/config/routes.main_slug.php';

use CleanUrl\Form\ConfigForm;
use CleanUrl\Service\ViewHelper\GetResourceTypeIdentifiersFactory;
use Generic\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
        $this->addRoutes();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        parent::install($serviceLocator);
        $this->cacheItemSetsRegex($serviceLocator);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data['clean_url_identifiers'][$name] = $settings->get($name, $value);
            $data['clean_url_main_path'][$name] = $settings->get($name, $value);
            $data['clean_url_item_sets'][$name] = $settings->get($name, $value);
            $data['clean_url_items'][$name] = $settings->get($name, $value);
            $data['clean_url_medias'][$name] = $settings->get($name, $value);
            $data['clean_url_admin'][$name] = $settings->get($name, $value);
        }

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->init();
        $form->setData($data);

        $view = $renderer;
        $translate = $view->plugin('translate');
        $view->headStyle()->appendStyle('.inputs label { display: block; }');
        $form->prepare();

        $html = $translate('"CleanUrl" plugin allows to have clean, readable and search engine optimized Urls like http://example.com/my_item_set/item_identifier.') // @translate
            . '<br />'
            . sprintf($translate('See %s for more information.'), '<a href="https://github.com/Daniel-KM/Omeka-S-module-CleanUrl">ReadMe</a>') // @translate
            . '<br />'
            . sprintf($translate('%sNote%s: identifiers should never contain reserved characters such "/" or "%%".'), '<strong>', '</strong>') // @translate
            . '<br />'
            . sprintf($translate('%sNote%s: For a good seo, it‘s not recommended to have multiple urls for the same resource.'), '<strong>', '</strong>') // @translate
            . '<br />'
            . $translate('To keep the original routes, the main site slug must be set in the file "routes.main_slug.php".') // @translate
            . $view->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
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

        $params['cleanurl_identifier_property'] = (int) $params['cleanurl_identifier_property'];

        // The default url should be allowed for items and media.
        $params['cleanurl_item_allowed'][] = $params['cleanurl_item_default'];
        $params['cleanurl_item_allowed'] = array_values(array_unique($params['cleanurl_item_allowed']));
        $params['cleanurl_media_allowed'][] = $params['cleanurl_media_default'];
        $params['cleanurl_media_allowed'] = array_values(array_unique($params['cleanurl_media_allowed']));

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (array_key_exists($name, $defaultSettings)) {
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
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $roles = $acl->getRoles();
        $acl
            ->allow(null, [Controller\Site\CleanUrlController::class])
            ->allow($roles, [Controller\Admin\CleanUrlController::class])
        ;
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
        $identifier = $getResourceIdentifier($resource, false);

        echo '<div class="meta-group"><h4>'
            . $translator->translate('CleanUrl identifier') // @translate
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


        $baseRoutes = [];
        if (MAIN_SITE_SLUG) {
            $baseRoutes['_top'] = [
                '/',
                '__SITE__',
                'CleanUrl\Controller\Site',
                MAIN_SITE_SLUG
            ];
        }
        $baseRoutes['_public'] = [
            '/s/:site-slug/',
            '__SITE__',
            'CleanUrl\Controller\Site',
            null
        ];
        if ($settings->get('cleanurl_use_admin')) {
            $baseRoutes['_admin'] = [
                '/admin/',
                '__ADMIN__',
                'CleanUrl\Controller\Admin',
                null
            ];
        }

        foreach ($baseRoutes as $routeExt => $array) {
            list($baseRoute, $space, $namespaceController, $siteSlug) = $array;
            if (!empty($itemSetsRegex)) {
                // Add an item set route.
                $route = $baseRoute . $mainPath . $itemSetGeneric;
                // Use one regex for all item sets. Default is case insensitve.
                $router->addRoute('cleanurl_item_sets' . $routeExt, [
                    'type' => \Zend\Router\Http\Segment::class,
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
                            'site-slug' => $siteSlug,
                        ],
                    ],
                ]);

                // Add an item set route for media.
                if (in_array('item_set', $allowedForMedia)) {
                    $router->addRoute('cleanurl_item_sets_media' . $routeExt, [
                        'type' => \Zend\Router\Http\Segment::class,
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
                                'site-slug' => $siteSlug,
                            ],
                        ],
                    ]);
                }

                // Add an item set / item route for media.
                if (in_array('item_set_item', $allowedForMedia)) {
                    $router->addRoute('cleanurl_item_sets_item_media' . $routeExt, [
                        'type' => \Zend\Router\Http\Segment::class,
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
                                'site-slug' => $siteSlug,
                            ],
                        ],
                    ]);
                }

                // Add an item set route for items.
                if (in_array('item_set', $allowedForItems)) {
                    $router->addRoute('cleanurl_item_sets_item' . $routeExt, [
                        'type' => \Zend\Router\Http\Segment::class,
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
                                'site-slug' => $siteSlug,
                            ],
                        ],
                    ]);
                }
            }

            // Add a generic route for media.
            if (in_array('generic', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $router->addRoute('cleanurl_generic_media' . $routeExt, [
                    'type' => \Zend\Router\Http\Segment::class,
                    'options' => [
                        'route' => $route . ':resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-media',
                            'item_set_id' => null,
                            'site-slug' => $siteSlug,
                        ],
                    ],
                ]);
            }

            // Add a generic / item route for media.
            if (in_array('generic_item', $allowedForMedia)) {
                $route = $baseRoute . $mainPath . $mediaGeneric;
                $router->addRoute('cleanurl_generic_item_media' . $routeExt, [
                    'type' => \Zend\Router\Http\Segment::class,
                    'options' => [
                        'route' => $route . ':item_identifier/:resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item-media',
                            'item_set_id' => null,
                            'site-slug' => $siteSlug,
                        ],
                    ],
                ]);
            }

            // Add a generic route for items.
            if (in_array('generic', $allowedForItems)) {
                $route = $baseRoute . $mainPath . trim($itemGeneric, '/');
                $router->addRoute('cleanurl_generic_items_browse' . $routeExt, [
                    'type' => \Zend\Router\Http\Segment::class,
                    'options' => [
                        'route' => $route,
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'items-browse',
                            'site-slug' => $siteSlug,
                        ],
                    ],
                ]);
                $router->addRoute('cleanurl_generic_item' . $routeExt, [
                    'type' => \Zend\Router\Http\Segment::class,
                    'options' => [
                        'route' => $route . '/:resource_identifier',
                        'defaults' => [
                            '__NAMESPACE__' => $namespaceController,
                            $space => true,
                            'controller' => 'CleanUrlController',
                            'action' => 'route-item',
                            'item_set_id' => null,
                            'site-slug' => $siteSlug,
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
        $propertyId = (int) $settings->get('cleanurl_identifier_property');
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
