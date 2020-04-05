<?php
namespace CleanUrl;

/*
 * Clean Url
 *
 * Allows to have URL like http://example.com/my_item_set/dc:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2020
 * @copyright BibLibre, 2016-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

require_once file_exists(OMEKA_PATH . '/config/clean_url.config.php')
    ? OMEKA_PATH . '/config/clean_url.config.php'
    : __DIR__ . '/config/clean_url.config.php';

require_once file_exists(OMEKA_PATH . '/config/clean_url.dynamic.php')
    ? OMEKA_PATH . '/config/clean_url.dynamic.php'
    : __DIR__ . '/config/clean_url.dynamic.php';

use CleanUrl\Form\ConfigForm;
use CleanUrl\Service\ViewHelper\GetResourceTypeIdentifiersFactory;
use Generic\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
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

    protected function preInstall()
    {
        $services = $this->getServiceLocator();
        $t = $services->get('MvcTranslator');
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;

        $this->preInstallCopyConfigFiles();

        $messenger->addWarning($t->translate('Some settings may be configured in the file "config/clean_url.config.php" in the root of Omeka.')); // @translate
    }

    protected function preInstallCopyConfigFiles()
    {
        $success = true;

        $services = $this->getServiceLocator();
        $t = $services->get('MvcTranslator');
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;

        $configPath = __DIR__ . '/config/clean_url.dynamic.php';
        $omekaConfigPath = OMEKA_PATH . '/config/clean_url.dynamic.php';
        if (file_exists($configPath) && !file_exists($omekaConfigPath)) {
            $result = @copy($configPath, $omekaConfigPath);
            if (!$result) {
                $success = false;
                $message = $t->translate('Unable to copy the file "config/clean_url.dynamic.php" in Omeka config directory. It should be kept writeable by the server.') // @translate
                    . ' ' . $t->translate('Without this file, it won‘t be possible to modify or remove the "s/".'); // @translate
                $messenger->addWarning($message);
            }
        }

        $configPath = __DIR__ . '/config/clean_url.config.php';
        $omekaConfigPath = OMEKA_PATH . '/config/clean_url.config.php';
        if (file_exists($configPath) && !file_exists($omekaConfigPath)) {
            $result = @copy($configPath, $omekaConfigPath);
            if (!$result) {
                $success = false;
                $message = $t->translate('Unable to copy the special config file "config/clean_url.config;php" in Omeka config directory.') // @translate
                    . ' ' . $t->translate('Without this file, it won‘t be possible to modify or remove the "s/" and "page/" or to define a main site.'); // @translate
                $messenger->addWarning($message);
            }
        }

        return $success;
    }

    protected function postInstall()
    {
        $this->cacheCleanData();
        $this->cacheItemSetsRegex();
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

    /**
     * Defines public routes "main_path / my_item_set | generic / dcterms:identifier".
     *
     * @todo Rechecks performance of routes definition.
     */
    protected function addRoutes()
    {
        $services = $this->getServiceLocator();
        $router = $services->get('Router');
        if (!$router instanceof \Zend\Router\Http\TreeRouteStack) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $basePath = $services->get('ViewHelperManager')->get('basePath');

        $router
            ->addRoute('clean-url', [
                'type' => \CleanUrl\Router\Http\CleanRoute::class,
                // Check clean url first.
                'priority' => 10,
                'options' => [
                    // TODO Save all these settings in one array.
                    'base_path' => $basePath(),
                    'settings' => [
                        'default_site' => $settings->get('default_site'),
                        'main_path' => $settings->get('cleanurl_main_path'),
                        'item_set_generic' => $settings->get('cleanurl_item_set_generic'),
                        'item_generic' => $settings->get('cleanurl_item_generic'),
                        'media_generic' => $settings->get('cleanurl_media_generic'),
                        'item_allowed' => $settings->get('cleanurl_item_allowed'),
                        'media_allowed' => $settings->get('cleanurl_media_allowed'),
                        'use_admin' => $settings->get('cleanurl_use_admin'),
                        'item_set_regex' => $settings->get('cleanurl_item_set_regex'),
                    ],
                    'defaults' => [
                        'controller' => 'CleanUrlController',
                        'action' => 'index',
                    ],
                ],
            ]);
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

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        // The params are cached on load and save, to manage the case the user
        // doesn‘t save the config.
        $this->cacheCleanData();
        $this->cacheItemSetsRegex();

        // TODO Clean filling of the config form.
        $data = [];
        $defaultSettings = $config['cleanurl']['config'];
        foreach ($defaultSettings as $name => $value) {
            $data['clean_url_pages'][$name] = $settings->get($name, $value);
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

        $html = $translate('"CleanUrl" module allows to have clean, readable and search engine optimized urls for pages and resources, like http://example.com/my_item_set/item_identifier.') // @translate
            . '<br />'
            . sprintf($translate('See %s for more information.'), // @translate
            sprintf('<a href="https://github.com/Daniel-KM/Omeka-S-module-CleanUrl">%s</a>', 'Readme'))
            . '<br />'
            . sprintf($translate('%sNote%s: identifiers should never contain reserved characters such "/" or "%%".'), '<strong>', '</strong>') // @translate
            . '<br />'
            . sprintf($translate('%sNote%s: For a good seo, it‘s not recommended to have multiple urls for the same resource.'), '<strong>', '</strong>') // @translate
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

        // TODO Normalize the filling of the config form.
        $params = $form->getData();

        // Make the params a flat array.
        $params = array_merge(
            $params['clean_url_pages'],
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
            'cleanurl_site_slug',
            'cleanurl_page_slug',
        ] as $posted) {
            $value = trim(trim($params[$posted]), ' /');
            $params[$posted] = mb_strlen($value) ? trim($value) . '/' : '';
        }

        $params['cleanurl_identifier_property'] = (int) $params['cleanurl_identifier_property'];

        // The default url should be allowed for items and media.
        $params['cleanurl_item_allowed'][] = $params['cleanurl_item_default'];
        $params['cleanurl_item_allowed'] = array_values(array_unique($params['cleanurl_item_allowed']));
        $params['cleanurl_media_allowed'][] = $params['cleanurl_media_default'];
        $params['cleanurl_media_allowed'] = array_values(array_unique($params['cleanurl_media_allowed']));

        $defaultSettings = $config['cleanurl']['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        $this->cacheCleanData();
        $this->cacheItemSetsRegex();
        return true;
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
     * Process after saving or deleting an item set.
     *
     * @param Event $event
     */
    public function afterSaveItemSet(Event $event)
    {
        $this->cacheItemSetsRegex();
    }

    /**
     * Cache site slugs in file config/clean_url.dynamic.php.
     */
    protected function cacheCleanData()
    {
        $services = $this->getServiceLocator();

        $filepath = OMEKA_PATH . '/config/clean_url.dynamic.php';
        if (!$this->checkFilepath($filepath)) {
            $logger = $services->get('Omeka\Logger');
            $logger->warn('The file "clean_url.dynamic.php" in the config directory of Omeka is not writeable.'); // @translate
            return false;
        }

        $settings = $services->get('Omeka\Settings');

        // The file is always reset from original file.
        $sourceFilepath = __DIR__ . '/config/clean_url.dynamic.php';
        $content = file_get_contents($sourceFilepath);

        // Update main site.
        $default = $settings->get('default_site', '');
        $skip = $settings->get('cleanurl_site_skip_main');
        if ($default) {
            try {
                $default = $services->get('Omeka\ApiManager')->read('sites', ['id' => $default])->getContent()->slug();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $default = '';
            }
        }
        $replaceRegex = $skip && strlen($default) ? "'$default'" : 'false';
        $regex = "~const SLUG_MAIN_SITE = (?:'[^']*?'|false);~";
        $replace = "const SLUG_MAIN_SITE = $replaceRegex;";
        $content = preg_replace($regex, $replace, $content, 1);

        // Update options for site prefix.
        $siteSlug = trim($settings->get('cleanurl_site_slug', ''), ' /');
        $siteSlug = mb_strlen($siteSlug) ? $siteSlug . '/' : '';
        $regex = "~const SLUG_SITE = '[^']*?';~";
        $replace = "const SLUG_SITE = '$siteSlug';";
        $content = preg_replace($regex, $replace, $content, 1);

        // Update options for page prefix.
        $pageSlug = trim($settings->get('cleanurl_page_slug', ''), ' /');
        $pageSlug = mb_strlen($pageSlug) ? $pageSlug . '/' : '';
        $regex = "~const SLUG_PAGE = '[^']*?';~";
        $replace = "const SLUG_PAGE = '$pageSlug';";
        $content = preg_replace($regex, $replace, $content, 1);

        // Update list of sites.
        // Get all site slugs, public or not.
        $sql = 'SELECT slug FROM site;';
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $stmt = $connection->query($sql);
        $slugs = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $replaceRegex = $this->prepareRegex($slugs);
        $regex = "~const SLUGS_SITE = '[^']*?';~";
        $replace = "const SLUGS_SITE = '" . $replaceRegex . "';";
        $content = preg_replace($regex, $replace, $content, 1);

        file_put_contents($filepath, $content);
    }

    protected function checkFilepath($filepath)
    {
        return file_exists($filepath)
            && is_file($filepath)
            && filesize($filepath)
            && is_writeable($filepath);
    }

    /**
     * Cache item set identifiers as string to speed up routing.
     */
    protected function cacheItemSetsRegex()
    {
        $services = $this->getServiceLocator();
        // Get all item set identifiers with one query.
        $viewHelpers = $services->get('ViewHelperManager');
        // The view helper is not available during intall, upgrade and tests.
        if ($viewHelpers->has('getResourceTypeIdentifiers')) {
            $getResourceTypeIdentifiers = $viewHelpers->get('getResourceTypeIdentifiers');
            $itemSetIdentifiers = $getResourceTypeIdentifiers('item_sets', false);
        } else {
            $getResourceTypeIdentifiers = $this->getViewHelperRTI($services);
            $itemSetIdentifiers = $getResourceTypeIdentifiers->__invoke('item_sets', false);
        }

        $regex = $this->prepareRegex($itemSetIdentifiers);

        $settings = $services->get('Omeka\Settings');
        $settings->set('cleanurl_item_set_regex', $regex);
    }

    protected function prepareRegex($list)
    {
        // To avoid issues with identifiers that contain another identifier, for
        // example "identifier_bis" contains "identifier", they are ordered by
        // reversed length.
        array_multisort(
            array_map('mb_strlen', $list),
            $list
        );
        $list = array_reverse($list);

        // Don't quote "-", it's useless for matches.
        $listRegex = array_map(function ($v) {
            return str_replace('\\-', '-', preg_quote($v));
        }, $list);

        // To avoid a bug with identifiers that contain a "/", that is not
        // escaped with preg_quote().
        return str_replace('/', '\/', implode('|', $listRegex));
    }

    /**
     * Get the view helper getResourceTypeIdentifiers with some params.
     *
     * @return \CleanUrl\View\Helper\GetResourceTypeIdentifiers
     */
    protected function getViewHelperRTI()
    {
        $services = $this->getServiceLocator();

        require_once __DIR__ . '/src/Service/ViewHelper/GetResourceTypeIdentifiersFactory.php';
        require_once __DIR__ . '/src/View/Helper/GetResourceTypeIdentifiers.php';

        $settings = $services->get('Omeka\Settings');
        $propertyId = (int) $settings->get('cleanurl_identifier_property');
        $prefix = $settings->get('cleanurl_identifier_prefix');

        $factory = new GetResourceTypeIdentifiersFactory();
        return $factory(
            $services,
            'getResourceTypeIdentifiers',
            [
                'propertyId' => $propertyId,
                'prefix' => $prefix,
            ]
        );
    }
}
