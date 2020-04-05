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
     * Defines public routes like "main_path / main_path_2 / my_item_set | generic / item dcterms:identifier / media dcterms:identifier".
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
                        'main_path_full' => $settings->get('cleanurl_main_path_full'),
                        'main_path_full_encoded' => $settings->get('cleanurl_main_path_full_encoded'),
                        'item_set_generic' => $settings->get('cleanurl_item_set_generic'),
                        'item_generic' => $settings->get('cleanurl_item_generic'),
                        'media_generic' => $settings->get('cleanurl_media_generic'),
                        'item_allowed' => $settings->get('cleanurl_item_allowed'),
                        'media_allowed' => $settings->get('cleanurl_media_allowed'),
                        'admin_use' => $settings->get('cleanurl_admin_use') && $services->get('Omeka\Status')->isAdminRequest(),
                        'item_set_regex' => $settings->get('cleanurl_item_set_regex'),
                        'regex' => $settings->get('cleanurl_regex'),
                    ],
                ],
            ]);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        if ($settings->get('cleanurl_admin_show_identifier')) {
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
            [$this, 'handleSaveItemSet']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.update.post',
            [$this, 'handleSaveItemSet']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.delete.post',
            [$this, 'handleSaveItemSet']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'handleSaveSite']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.post',
            [$this, 'handleSaveSite']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.delete.post',
            [$this, 'handleSaveSite']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.pre',
            [$this, 'handleCheckSlug']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.pre',
            [$this, 'handleCheckSlug']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.create.pre',
            [$this, 'handleCheckSlug']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.update.pre',
            [$this, 'handleCheckSlug']
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

        /** @var \CleanUrl\Form\ConfigForm $form */
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
            'cleanurl_main_path_2',
            'cleanurl_main_path_3',
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

        if (!mb_strlen($params['cleanurl_main_path_2']) && mb_strlen($params['cleanurl_main_path_3'])) {
            $params['cleanurl_main_path_2'] = $params['cleanurl_main_path_3'];
            $params['cleanurl_main_path_3'] = '';
        }
        if (!mb_strlen($params['cleanurl_main_path']) && mb_strlen($params['cleanurl_main_path_2'])) {
            $params['cleanurl_main_path'] = $params['cleanurl_main_path_2'];
            $params['cleanurl_main_path_2'] = '';
        }
        // Prepare hidden params with the full path, to avoid checks later.
        $params['cleanurl_main_path_full'] = $params['cleanurl_main_path'] . $params['cleanurl_main_path_2'] . $params['cleanurl_main_path_3'];
        if (mb_strlen($params['cleanurl_main_path'])) {
            $params['cleanurl_main_path_full_encoded'] = $this->encode(rtrim($params['cleanurl_main_path'], '/')) . '/';
            if (mb_strlen($params['cleanurl_main_path_2'])) {
                $params['cleanurl_main_path_full_encoded'] .= $this->encode(rtrim($params['cleanurl_main_path_2'], '/')) . '/';
                if (mb_strlen($params['cleanurl_main_path_3'])) {
                    $params['cleanurl_main_path_full_encoded'] .= $this->encode(rtrim($params['cleanurl_main_path_3'], '/')) . '/';
                }
            }
        }

        // The default url should be allowed for items and media.
        $params['cleanurl_item_allowed'][] = $params['cleanurl_item_default'];
        $params['cleanurl_item_allowed'] = array_values(array_unique($params['cleanurl_item_allowed']));
        $params['cleanurl_media_allowed'][] = $params['cleanurl_media_default'];
        $params['cleanurl_media_allowed'] = array_values(array_unique($params['cleanurl_media_allowed']));

        $connection = $services->get('Omeka\Connection');

        // Check the default site.
        $skip = $params['cleanurl_site_skip_main'];
        if ($skip) {
            $default = $settings->get('default_site', '');
            if ($default) {
                try {
                    $default = $services->get('Omeka\ApiManager')->read('sites', ['id' => $default])->getContent()->slug();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    $default = '';
                }
            }
            if (!$default) {
                $message = new \Omeka\Stdlib\Message('There is no default site: "/s/site-slug" cannot be skipped.'); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }

            // Check all pages of the default site.
            // TODO Manage the case where the default site is updated after (rare).
            $result = [];
            $slugs = $connection->query('SELECT slug FROM site;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new \Omeka\Stdlib\Message('The sites "%s" use a reserved string and the "/s/site-slug" cannot be skipped.', implode('", "', $result)); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }
            $slugs = $connection->query('SELECT slug FROM site_page;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|') !== false) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new \Omeka\Stdlib\Message('The site pages "%s" use a reserved string and "/s/site-slug" cannot be skipped.', implode('", "', $result)); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }
        }

        // Check the option site slug.
        $slug = $params['cleanurl_site_slug'];
        if (mb_strlen($slug)
            && $slug !== 's/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|') !== false
        ) {
            $message = new \Omeka\Stdlib\Message('The slug "%s" is used or reserved and the prefix for sites cannot be updated.', $slug); // @translate
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messenger->addError($message);
            return false;
        }

        if (!mb_strlen($slug)) {
            $result = [];
            $slugs = $services->get('Omeka\Connection')->query('SELECT slug FROM site;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new \Omeka\Stdlib\Message('The sites "%s" use a reserved string and the prefix for sites cannot be removed.', implode('", "', $result)); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }
        }

        // Check the option page slug.
        $slug = $params['cleanurl_page_slug'];
        if (mb_strlen($slug)
            && $slug !== 'page/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|') !== false
        ) {
            $message = new \Omeka\Stdlib\Message('The slug "%s" is used or reserved and the prefix for pages cannot be updated.', $slug); // @translate
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messenger->addError($message);
            return false;
        }

        if (!mb_strlen($slug)) {
            $result = [];
            $slugs = $services->get('Omeka\Connection')->query('SELECT slug FROM site_page;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new \Omeka\Stdlib\Message('The site pages "%s" use a reserved string and the prefix for pages cannot be removed.', implode('", "', $result)); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }
        }

        if ($params['cleanurl_media_media_undefined'] === 'position') {
            $hasGeneric = (bool) array_intersect(['generic_media', 'generic_media_full', 'generic_item_media', 'generic_item_full_media', 'generic_item_media_full', 'generic_item_full_media_full'], $params['cleanurl_media_allowed']);
            $hasNoGenericItem = (bool) array_intersect(['generic_item_media', 'generic_item_full_media', 'generic_item_media_full', 'generic_item_full_media_full'], $params['cleanurl_media_allowed']);
            if ($hasGeneric && !$hasNoGenericItem) {
                $params['cleanurl_media_allowed'][] = 'generic_item_media';
                $message = new \Omeka\Stdlib\Message('The option "media position" requires to set a generic route with an item id. One route was added.'); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addWarning($message);
            }
            $hasItemSet = (bool) array_intersect(['item_set_media', 'item_set_media_full', 'item_set_item_media', 'item_set_item_full_media', 'item_set_item_media_full', 'item_set_item_full_media_full'], $params['cleanurl_media_allowed']);
            $hasNoItemSetItem = (bool) array_intersect(['item_set_item_media', 'item_set_item_full_media', 'item_set_item_media_full', 'item_set_item_full_media_full'], $params['cleanurl_media_allowed']);
            if ($hasItemSet && !$hasNoItemSetItem) {
                $params['cleanurl_media_allowed'][] = 'item_set_item_media';
                $message = new \Omeka\Stdlib\Message('The option "media position" requires to set an item set route with an item id. One route was added.'); // @translate
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addWarning($message);
            }
        }

        // Prepare the regexes one time.
        $params['cleanurl_regex'] = $this->prepareRegexes($params);

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
        $identifier = $getResourceIdentifier($resource, false, false);

        echo '<div class="meta-group"><h4>'
            . $translator->translate('Clean identifier') // @translate
            . '</h4><div class="value">'
            . ($identifier ?: '<em>' . $translator->translate('[none]') . '</em>')
            . '</div></div>';
    }

    /**
     * Process after saving or deleting an item set.
     *
     * @param Event $event
     */
    public function handleSaveItemSet(Event $event)
    {
        $this->cacheItemSetsRegex();
    }

    /**
     * Process after saving or deleting a site.
     *
     * @param Event $event
     */
    public function handleSaveSite(Event $event)
    {
        $this->cacheCleanData();
    }

    /**
     * Check a site before saving it.
     *
     * @param Event $event
     */
    public function handleCheckSlug(Event $event)
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $data = $request->getContent();
        if (!isset($data['o:slug'])) {
            return;
        };
        $slug = $data['o:slug'];
        if (!mb_strlen($slug)) {
            return;
        }
        // Don't update if the slug didn't change.
        if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . $slug . '|') === false) {
            return;
        }

        $data['o:slug'] .= '_' . substr(str_replace(['+', '/'], '', base64_encode(random_bytes(20))), 0, 4);
        $request->setContent($data);

        $message = new \Omeka\Stdlib\Message('The slug "%s" is used or reserved. A random string has been automatically appended.', $slug); // @translate
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
        $messenger->addWarning($message);
        // throw new \Omeka\Api\Exception\ValidationException($message);
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
        // The view helper is not available during install, upgrade and tests.
        if ($viewHelpers->has('getResourceTypeIdentifiers')) {
            $getResourceTypeIdentifiers = $viewHelpers->get('getResourceTypeIdentifiers');
            $itemSetIdentifiers = $getResourceTypeIdentifiers('item_sets', false, true);
        } else {
            $getResourceTypeIdentifiers = $this->getViewHelperRTI($services);
            $itemSetIdentifiers = $getResourceTypeIdentifiers->__invoke('item_sets', false, true);
        }

        $regex = $this->prepareRegex($itemSetIdentifiers);

        $settings = $services->get('Omeka\Settings');
        $settings->set('cleanurl_item_set_regex', $regex);
    }

    protected function prepareRegex(array $list)
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

    protected function prepareRegexes(array $params)
    {
        // No need to preg quote "/".
        $replaces = [
            '\\-' => '-',
        ];
        $result = [];
        $result['main_path_full'] = str_replace(array_keys($replaces), array_values($replaces), preg_quote($params['cleanurl_main_path_full']));
        $result['item_set_generic'] = str_replace(array_keys($replaces), array_values($replaces), preg_quote($params['cleanurl_item_set_generic']));
        $result['item_generic'] = str_replace(array_keys($replaces), array_values($replaces), preg_quote($params['cleanurl_item_generic']));
        $result['media_generic'] = str_replace(array_keys($replaces), array_values($replaces), preg_quote($params['cleanurl_media_generic']));
        return $result;
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

    /**
     * Encode a path segment.
     *
     * @see \Zend\Router\Http\Segment::encode()
     *
     * @param  string $value
     * @return string
     */
    protected function encode($value)
    {
        $urlencodeCorrectionMap = [
            '%21' => "!", // sub-delims
            '%24' => "$", // sub-delims
            '%26' => "&", // sub-delims
            '%27' => "'", // sub-delims
            '%28' => "(", // sub-delims
            '%29' => ")", // sub-delims
            '%2A' => "*", // sub-delims
            '%2B' => "+", // sub-delims
            '%2C' => ",", // sub-delims
            // '%2D' => "-", // unreserved - not touched by rawurlencode
            // '%2E' => ".", // unreserved - not touched by rawurlencode
            '%3A' => ":", // pchar
            '%3B' => ";", // sub-delims
            '%3D' => "=", // sub-delims
            '%40' => "@", // pchar
            // '%5F' => "_", // unreserved - not touched by rawurlencode
            // '%7E' => "~", // unreserved - not touched by rawurlencode
        ];
        return strtr(rawurlencode($value), $urlencodeCorrectionMap);
    }
}
