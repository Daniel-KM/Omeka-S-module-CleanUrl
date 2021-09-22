<?php declare(strict_types=1);

namespace CleanUrl;

/*
 * Clean Url
 *
 * Allows to have links like https://example.net/collection/dcterms:identifier.
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

use CleanUrl\Form\ConfigForm;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager): void
    {
        $moduleManager->getEventManager()->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onEventMergeConfig']);
    }

    public function onEventMergeConfig(ModuleEvent $event): void
    {
        // Check if the main site is skipped, else the standard urls apply.
        if (!SLUG_MAIN_SITE) {
            return;
        }

        /** @var \Laminas\ModuleManager\Listener\ConfigListener $configListener */
        $configListener = $event->getParam('configListener');
        // At this point, the config is read only, so it is copied and replaced.
        $config = $configListener->getMergedConfig(false);

        // Manage the routes for the main site when "s/site-slug/" is skipped.
        // So copy routes from "site", without starting "/".
        foreach ($config['router']['routes']['site']['child_routes'] as $routeName => $options) {
            // Skip some routes for pages that are set directly in the config.
            if (isset($config['router']['routes']['top']['child_routes'][$routeName])) {
                continue;
            }
            $config['router']['routes']['top']['child_routes'][$routeName] = $options;
            $config['router']['routes']['top']['child_routes'][$routeName]['options']['route'] =
                ltrim($config['router']['routes']['top']['child_routes'][$routeName]['options']['route'], '/');
        }

        $configListener->setMergedConfig($config);
    }

    public function getConfig()
    {
        require_once file_exists(OMEKA_PATH . '/config/cleanurl.config.php')
            ? OMEKA_PATH . '/config/cleanurl.config.php'
            : __DIR__ . '/config/cleanurl.config.php';
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // The page controller is already allowed, because it's an override.
        $this->addRoutes();
    }

    protected function preInstall(): void
    {
        if (!$this->isConfigWriteable()) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException('The file "cleanurl.config.php" in the config directory of Omeka is not writeable.'); // @translate
        }
        $services = $this->getServiceLocator();
        $module = $services->get('Omeka\ModuleManager')->getModule('Generic');
        if ($module && version_compare($module->getIni('version') ?? '', '3.3.27', '<')) {
            $translator = $services->get('MvcTranslator');
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                'Generic', '3.3.27'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $this->cacheCleanData();
        $this->cacheRouteSettings();
    }

    protected function isConfigWriteable(): bool
    {
        $filepath = OMEKA_PATH . '/config/cleanurl.config.php';
        return (file_exists($filepath) && is_writeable($filepath))
            || (!file_exists($filepath) && is_writeable(dirname($filepath)));
    }

    /**
     * Defines routes.
     */
    protected function addRoutes(): void
    {
        $services = $this->getServiceLocator();
        $router = $services->get('Router');
        if (!$router instanceof \Laminas\Router\Http\TreeRouteStack) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $helpers = $services->get('ViewHelperManager');
        $defaultSettings = ['routes' => [], 'route_aliases' => []];
        $cleanUrlSettings = $settings->get('cleanurl_settings', []) + $defaultSettings;

        $configRoutes = $services->get('Config')['router']['routes'];

        // Top routes are managed during init above.
        $childRoutes = ($configRoutes['site']['child_routes']['resource-id']['child_routes'] ?? [])
            + ($configRoutes['admin']['child_routes']['id']['child_routes'] ?? []);

        $router
            ->addRoute('clean-url', [
                'type' => \CleanUrl\Router\Http\CleanRoute::class,
                // Check clean url before core and other module routes.
                'priority' => 10,
                'options' => [
                    'routes' => $cleanUrlSettings['routes'],
                    'route_aliases' => $cleanUrlSettings['route_aliases'],
                    'api' => $services->get('Omeka\ApiManager'),
                    'entityManager' => $services->get('Omeka\EntityManager'),
                    'getMediaFromPosition' => $helpers->get('getMediaFromPosition'),
                    'getResourceFromIdentifier' => $helpers->get('getResourceFromIdentifier'),
                    'getResourceIdentifier' => $helpers->get('getResourceIdentifier'),
                ],
                // Fix https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/11
                // FIXME Go thorough to find why site/resource-id answer by a site/resource (so, above during merge of child routes).
                // 'may_terminate' => !empty($childRoutes),
                'may_terminate' => true,
                'child_routes' => $childRoutes,
            ]);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
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
            [$this, 'handleCheckSlugSite']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.pre',
            [$this, 'handleCheckSlugSite']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.create.pre',
            [$this, 'handleCheckSlugPage']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.update.pre',
            [$this, 'handleCheckSlugPage']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $translate = $renderer->plugin('translate');
        $html = $translate('"Clean Url" module allows to have clean, readable and search engine optimized urls for pages and resources, like https://example.net/item_set_identifier/item_identifier.') // @translate
            . '<br/>'
            . $translate('For identifiers, it is recommended to use a pattern that includes at least one letter to avoid confusion with internal numerical ids.') // @translate
            . '<br/>'
            . $translate('For a good seo, it’s not recommended to have multiple urls for the same resource.') // @translate
            . '<br/>'
            . sprintf($translate('See %s for more information.'), // @translate
                sprintf('<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl">%s</a>', 'Readme')
            );

        if (!$this->isConfigWriteable()) {
            $html .= '<br/><br/>'
                . sprintf($translate('%sWarning%s: the config of the module cannot be saved in "config/cleanurl.config.php". It is required to skip the site paths.'), // @translate
                    '<strong>', '</strong>')
                . '<br/><br/>';
        }

        return $html
            . parent::getConfigForm($renderer);
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

        // Check config.

        $params = $form->getData();
        $params['cleanurl_settings'] = [];

        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
        $connection = $services->get('Omeka\Connection');
        $hasError = false;

        // TODO Move the formatters and validators inside the config form.

        // TODO Make it an hidden input to forbid submission.
        if (!$this->isConfigWriteable()) {
            $controller->messenger()->addError(
                'The config of the module cannot be saved in "config/cleanurl.config.php". It is required to skip the site paths.' // @translate
            );
            $hasError = true;
        }

        // Sanitize params first.

        $trimSlash = function ($v) {
            return trim((string) $v, "/ \t\n\r\0\x0B");
        };

        $params['cleanurl_admin_reserved'] = array_unique(array_filter(array_map($trimSlash, $params['cleanurl_admin_reserved'])));

        foreach ([
            'cleanurl_site_slug',
            'cleanurl_page_slug',
        ] as $posted) {
            $value = $trimSlash($params[$posted]);
            $params[$posted] = mb_strlen($value) ? $value . '/' : '';
        }

        $siteSlug = $params['cleanurl_site_slug'];
        $pageSlug = $params['cleanurl_page_slug'];

        // Check the default site.
        $skip = $params['cleanurl_site_skip_main']
            || !($siteSlug . $pageSlug);
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
                $message = new Message('There is no default site: "/s/site-slug" cannot be empty or skipped.'); // @translate
                $messenger->addError($message);
                return false;
            }

            // Check all pages of the default site.
            // TODO Manage the case where the default site is updated after (rare).
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new Message(
                    'The sites "%s" use a reserved string and the "/s/site-slug" cannot be skipped.', // @translate
                    implode('", "', $result)
                );
                $messenger->addError($message);
                $hasError = true;
            }
            $slugs = $connection->executeQuery('SELECT slug FROM site_page;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|') !== false) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new Message(
                    'The site pages "%s" use a reserved string and "/s/site-slug" cannot be skipped.', // @translate
                    implode('", "', $result)
                );
                $messenger->addError($message);
                $hasError = true;
            }
        }

        // Check the option site slug.
        if (mb_strlen($siteSlug)
            && $siteSlug !== 's/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($siteSlug, '/') . '|') !== false
        ) {
            $message = new Message('The slug "%s" is used or reserved and the prefix for sites cannot be updated.', $siteSlug); // @translate
            $messenger->addError($message);
            $hasError = true;
        }
        // Check the existing slugs with reserved slugs.
        else {
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if (count($result)) {
                $message = new Message(
                    'The sites "%s" use a reserved string and the prefix for sites cannot be removed.', // @translate
                    implode('", "', $result)
                );
                $messenger->addError($message);
                $hasError = true;
            }
        }

        // Check the option page slug.
        if (mb_strlen($pageSlug)
            && $pageSlug !== 'page/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($pageSlug, '/') . '|') !== false
        ) {
            $message = new Message('The slug "%s" is used or reserved and the prefix for pages cannot be updated.', $pageSlug); // @translate
            $messenger->addError($message);
            $hasError = true;
        }
        // Check the existing slugs with reserved slugs.
        else {
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site_page;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new Message(
                    'The site pages "%s" use a reserved string and the prefix for pages cannot be removed.', // @translate
                    implode('", "', $result)
                ); // @translate
                $messenger->addError($message);
                $hasError = true;
            }
        }

        $resourceTypes = ['item_set', 'item', 'media'];

        foreach ($resourceTypes as $resourceType) {
            $paramName = 'cleanurl_' . $resourceType;
            foreach (['default', 'short', 'pattern', 'pattern_short'] as $name) {
                $params[$paramName][$name] = $trimSlash($params[$paramName][$name]);
            }
            // Don't trim the prefix. See GetResourcesFromIdentifiers.
            // TODO Remove the prefix fix for space.
            foreach (['prefix'] as $name) {
                $params[$paramName][$name] = trim($params[$paramName][$name]);
            }
            foreach (['prefix_part_of', 'keep_slash', 'case_sensitive'] as $name) {
                $params[$paramName][$name] = (bool) $params[$paramName][$name];
            }
            foreach (['property'] as $name) {
                $params[$paramName][$name] = (int) $params[$paramName][$name];
            }
            $params[$paramName]['paths'] = array_unique(array_filter(array_map($trimSlash, $params[$paramName]['paths'])));
        }

        // Quick check of paths and pattern for identifiers.
        $hasPattern = [];
        foreach ($resourceTypes as $resourceType) {
            $hasPattern[$resourceType]['full'] = !empty($params['cleanurl_' . $resourceType]['pattern']);
            $hasPattern[$resourceType]['short'] = !empty($params['cleanurl_' . $resourceType]['pattern_short']);
        }
        foreach ($resourceTypes as $resourceType) {
            $name = 'cleanurl_' . $resourceType;
            $paths = $params[$name]['paths'];
            $paths[] = $params[$name]['default'];
            $paths[] = $params[$name]['short'];
            foreach (array_filter($paths) as $path) {
                foreach ($resourceTypes as $resource) {
                    if (!$hasPattern[$resource]['full'] && mb_strpos($path, "{{$resource}_identifier}") !== false) {
                        $message = new Message('A pattern for "%s", for example "[a-zA-Z0-9_-]+", is required to use the path "%s".', $resource, $path); // @translate
                        $messenger->addError($message);
                        $hasError = true;
                    }
                    if (!$hasPattern[$resource]['full'] && !$hasPattern[$resource]['short'] && mb_strpos($path, "{{$resource}_identifier_short}") !== false) {
                        $message = new Message('A pattern for "%s", for example "[a-zA-Z0-9_-]+", is required to use the path "%s".', $resource, $path); // @translate
                        $messenger->addError($message);
                        $hasError = true;
                    }
                }
            }
        }

        if ($hasError) {
            return false;
        }

        // Save all the params.
        $defaultSettings = $config['cleanurl']['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        $this->cacheCleanData();
        $this->cacheRouteSettings(true);

        return true;
    }

    /**
     * Display an identifier.
     */
    public function displayViewResourceIdentifier(Event $event): void
    {
        $resource = $event->getTarget()->resource;
        $this->displayResourceIdentifier($resource);
    }

    /**
     * Display an identifier.
     */
    public function displayViewEntityIdentifier(Event $event): void
    {
        $resource = $event->getParam('entity');
        $this->displayResourceIdentifier($resource);
    }

    /**
     * Helper to display an identifier.
     *
     * @param \Omeka\Api\Representation\AbstractResourceRepresentation|Resource $resource
     */
    protected function displayResourceIdentifier($resource): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');
        $getResourceIdentifier = $services->get('ViewHelperManager')
            ->get('getResourceIdentifier');
        $identifier = $getResourceIdentifier($resource, false, false);

        echo '<div class="meta-group"><h4>'
            . $translator->translate('Identifier') // @translate
            . '</h4><div class="value">'
            . ($identifier ?: '<em>' . $translator->translate('[none]') . '</em>')
            . '</div></div>';
    }

    /**
     * Process after saving or deleting a site.
     *
     * @param Event $event
     */
    public function handleSaveSite(Event $event): void
    {
        $this->cacheCleanData();
        $this->cacheRouteSettings();
    }

    /**
     * Check a site before saving it.
     *
     * @param Event $event
     */
    public function handleCheckSlugSite(Event $event): void
    {
        $this->handleCheckSlug($event, 'sites');
    }

    /**
     * Check a site page before saving it.
     *
     * @param Event $event
     */
    public function handleCheckSlugPage(Event $event): void
    {
        $this->handleCheckSlug($event, 'site_pages');
    }

    /**
     * Check a site before saving it.
     *
     * @param Event $event
     * @param string $resourceType
     */
    protected function handleCheckSlug(Event $event, $resourceType): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $data = $request->getContent();
        if (!isset($data['o:slug'])) {
            return;
        }
        $slug = $data['o:slug'];
        if (!mb_strlen($slug)) {
            return;
        }

        // Name of the site is already checked for duplication.
        $slugCheck = $resourceType === 'sites' ? '' : SLUGS_SITE . '|';

        // Don't update if the slug didn't change.
        if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . $slugCheck, '|' . $slug . '|') === false) {
            return;
        }

        $data['o:slug'] .= '_' . substr(str_replace(['+', '/'], '', base64_encode(random_bytes(20))), 0, 4);
        $request->setContent($data);

        $message = new Message('The slug "%s" is used or reserved. A random string has been automatically appended.', $slug); // @translate
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
        $messenger->addWarning($message);
        // throw new \Omeka\Api\Exception\ValidationException((string) $message);
    }

    /**
     * Cache site slugs in file config/clean_url.config.php.
     */
    protected function cacheCleanData()
    {
        $services = $this->getServiceLocator();

        $filepath = OMEKA_PATH . '/config/cleanurl.config.php';
        if (!$this->isConfigWriteable()) {
            $logger = $services->get('Omeka\Logger');
            $logger->err('The file "cleanurl.config.php" in the config directory of Omeka is not writeable.'); // @translate
            return false;
        }

        $settings = $services->get('Omeka\Settings');

        // The file is always reset from the original file.
        $sourceFilepath = __DIR__ . '/config/cleanurl.config.php';
        $content = file_get_contents($sourceFilepath);

        // Update main site.
        $default = $settings->get('default_site', '');
        $skip = $settings->get('cleanurl_site_skip_main');
        $siteSlug = $settings->get('cleanurl_site_slug');
        $pageSlug = $settings->get('cleanurl_page_slug');

        // Check the default site.
        $skip = $skip
            || !($siteSlug . $pageSlug);
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
        $stmt = $connection->executeQuery($sql);
        $slugs = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $replaceRegex = $this->prepareRegex($slugs);
        $regex = "~const SLUGS_SITE = '[^']*?';~";
        $replace = "const SLUGS_SITE = '" . $replaceRegex . "';";
        $content = preg_replace($regex, $replace, $content, 1);

        file_put_contents($filepath, $content);
    }

    /**
     * Prepare the quick settings and regex one time.
     *
     * @param bool $displayMessages
     */
    protected function cacheRouteSettings($displayMessages = false): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $logger = $services->get('Omeka\Logger');

        // Controller name and resource types.
        $resourceTypes = ['item-set' => 'item_set', 'item' => 'item', 'media' => 'media'];

        $defaults = [
            'default' => 'resource/{resource_id}',
            'short' => '',
            'paths' => [],
            'pattern' => '\d+',
            'pattern_short' => '',
            'property' => 10,
            'prefix' => '',
            'prefix_part_of' => false,
            'keep_slash' => false,
            'case_sensitive' => false,
        ];

        $params = [
            'default_site' => (int) $settings->get('default_site'),
            'site_skip_main' => (bool) $settings->get('cleanurl_site_skip_main', false),
            'site_slug' => $settings->get('cleanurl_site_slug', 's/'),
            'page_slug' => $settings->get('cleanurl_site_slug', 'page/'),
            'resource' => $settings->get('cleanurl_resource', $defaults) + $defaults,
            'item_set' => $settings->get('cleanurl_item_set', $defaults) + $defaults,
            'item' => $settings->get('cleanurl_item', $defaults) + $defaults,
            'media' => $settings->get('cleanurl_media', $defaults) + $defaults,
            'admin_use' => $settings->get('cleanurl_admin_use', true),
            'admin_reserved' => $settings->get('cleanurl_admin_reserved', []),
            'routes' => [],
            'route_aliases' => [],
        ];

        // TODO Save the slug sites with the updated slugs_sites (but when the config is edited, the sites don't change).

        // Default, short and core urls are merged to manage paths simpler,
        // Set the default route the first in stacks if any for performance.
        foreach (['resource' => 'resource', 'item_set' => 'item-set', 'item' => 'item', 'media' => 'media'] as $resourceType => $controller) {
            array_unshift($params[$resourceType]['paths'], $params[$resourceType]['default']);
            $params[$resourceType]['paths'][] = $params[$resourceType]['short'];
            // Core paths.
            // $params[$resourceType]['paths'][] = "$controller/{{$resourceType}_id}";
            $params[$resourceType]['paths'] = array_unique(array_filter(array_map('trim', $params[$resourceType]['paths'])));
            if (empty($params[$resourceType]['pattern_short'])) {
                $params[$resourceType]['pattern_short'] = $params[$resourceType]['pattern'];
            }
        }

        $baseRoutes = [
            'public' => [
                'base_route' => '/' . SLUG_SITE . ':site-slug/',
                'base_regex' => '/' . SLUG_SITE . '(?P<site_slug>' . SLUGS_SITE . ')/',
                'base_spec' => '/' . SLUG_SITE . '%site-slug%/',
                'space' => '__SITE__',
                'namespace' => 'CleanUrl\Controller\Site',
                'site_slug' => null,
                'forward' => [
                    'route_name' => 'site/resource-id',
                    'namespace' => 'Omeka\Controller\Site',
                    'controller' => [
                        'item_set' => 'Omeka\Controller\Site\ItemSet',
                        'item' => 'Omeka\Controller\Site\Item',
                        'media' => 'Omeka\Controller\Site\Media',
                    ],
                    'action' => 'show',
                ],
            ],
            'admin' => [
                'base_route' => '/admin/',
                'base_regex' => '/admin/',
                'base_spec' => '/admin/',
                'space' => '__ADMIN__',
                'namespace' => 'CleanUrl\Controller\Admin',
                'site_slug' => null,
                'forward' => [
                    'route_name' => 'admin/default',
                    'namespace' => 'Omeka\Controller\Admin',
                    'controller' => [
                        'item_set' => 'Omeka\Controller\Admin\ItemSet',
                        'item' => 'Omeka\Controller\Admin\Item',
                        'media' => 'Omeka\Controller\Admin\Media',
                    ],
                    'action' => 'show',
                ],
            ],
            'top' => [
                'base_route' => '/',
                'base_regex' => '/',
                'base_spec' => '/',
                'space' => '__SITE__',
                'namespace' => 'CleanUrl\Controller\Site',
                'site_slug' => SLUG_MAIN_SITE,
                'forward' => [
                    'route_name' => 'site/resource-id',
                    'namespace' => 'Omeka\Controller\Site',
                    'controller' => [
                        'item_set' => 'Omeka\Controller\Site\ItemSet',
                        'item' => 'Omeka\Controller\Site\Item',
                        'media' => 'Omeka\Controller\Site\Media',
                    ],
                    'action' => 'show',
                ],
            ],
        ];

        $regexes = [
            '{resource_id}' => '(?P<resource_id>\d+)',
            '{resource_identifier}' => '(?P<resource_identifier>' . $params['resource']['pattern'] . ')',
            '{resource_identifier_short}' => '(?P<resource_identifier_short>' . $params['resource']['pattern_short'] . ')',
            '{item_set_id}' => '(?P<item_set_id>\d+)',
            '{item_set_identifier}' => '(?P<item_set_identifier>' . $params['item_set']['pattern'] . ')',
            '{item_set_identifier_short}' => '(?P<item_set_identifier_short>' . $params['item_set']['pattern_short'] . ')',
            '{item_id}' => '(?P<item_id>\d+)',
            '{item_identifier}' => '(?P<item_identifier>' . $params['item']['pattern'] . ')',
            '{item_identifier_short}' => '(?P<item_identifier_short>' . $params['item']['pattern_short'] . ')',
            '{media_id}' => '(?P<media_id>\d+)',
            '{media_identifier}' => '(?P<media_identifier>' . $params['media']['pattern'] . ')',
            '{media_identifier_short}' => '(?P<media_identifier_short>' . $params['media']['pattern_short'] . ')',
            '{media_position}' => '(?P<media_position>\d+)',
        ];

        $specs = [
            '{resource_id}' => '%resource_id%',
            '{resource_identifier}' => '%resource_identifier%',
            '{resource_identifier_short}' => '%resource_identifier_short%',
            '{item_set_id}' => '%item_set_id%',
            '{item_set_identifier}' => '%item_set_identifier%',
            '{item_set_identifier_short}' => '%item_set_identifier_short%',
            '{item_id}' => '%item_id%',
            '{item_identifier}' => '%item_identifier%',
            '{item_identifier_short}' => '%item_identifier_short%',
            '{media_id}' => '%media_id%',
            '{media_identifier}' => '%media_identifier%',
            '{media_identifier_short}' => '%media_identifier_short%',
            '{media_position}' => '%media_position%',
        ];

        $trimSlash = function ($v) {
            return trim((string) $v, "/ \t\n\r\0\x0B");
        };

        if ($displayMessages) {
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messager = function ($message) use ($messenger): void {
                $messenger->addError($message);
            };
        } else {
            $messager = function ($message) use ($logger): void {
                $logger->err($message);
            };
        }

        $getItemSetIdentifierName = function (string $path): ?string {
            if (mb_strpos($path, '{item_set_id}') === false) {
                if (mb_strpos($path, '{item_set_identifier}') === false) {
                    return mb_strpos($path, '{item_set_identifier_short}') === false
                        ? null
                        : 'item_set_identifier_short';
                }
                return 'item_set_identifier';
            }
            return 'item_set_id';
        };

        $getItemIdentifierName = function (string $path): ?string {
            if (mb_strpos($path, '{item_id}') === false) {
                if (mb_strpos($path, '{item_identifier}') === false) {
                    return mb_strpos($path, '{item_identifier_short}') === false
                        ? null
                        : 'item_identifier_short';
                }
                return 'item_identifier';
            }
            return 'item_id';
        };

        $checkPathItemSet = function (string $path) use ($messager): ?string {
            $checks = [
                '{item_set_id}',
                '{item_set_identifier}',
                '{item_set_identifier_short}',
            ];
            $resourceIdentifier = array_filter($checks, function ($v) use ($path) {
                return mb_strpos($path, $v) !== false;
            });
            if (count($resourceIdentifier) !== 1) {
                $messager(new Message('The path "%s" for item sets should contain one and only one item set identifier.', $path)); // @translate
                return null;
            }
            $checks = [
                '{site_slug}',
                '{resource_id}',
                '{resource_identifier}',
                '{resource_identifier_short}',
                '{item_id}',
                '{item_identifier}',
                '{item_identifier_short}',
                '{media_id}',
                '{media_identifier}',
                '{media_identifier_short}',
                '{media_position}',
            ];
            foreach ($checks as $check) {
                if (mb_strpos($path, $check) !== false) {
                    $messager(new Message('The path "%s" for item sets should not contain identifier "%s".', $path, $check)); // @translate
                    return null;
                }
            }
            return reset($resourceIdentifier);
        };

        $checkPathItem = function (string $path) use ($messager): ?string {
            $checks = [
                '{item_id}',
                '{item_identifier}',
                '{item_identifier_short}',
            ];
            $resourceIdentifier = array_filter($checks, function ($v) use ($path) {
                return mb_strpos($path, $v) !== false;
            });
            if (count($resourceIdentifier) !== 1) {
                $messager(new Message('The path "%s" for items should contain one and only one item identifier.', $path)); // @translate
                return null;
            }
            $checks = [
                '{site_slug}',
                '{resource_id}',
                '{resource_identifier}',
                '{resource_identifier_short}',
                '{media_id}',
                '{media_identifier}',
                '{media_identifier_short}',
                '{media_position}',
            ];
            foreach ($checks as $check) {
                if (mb_strpos($path, $check) !== false) {
                    $messager(new Message('The path "%s" for items should not contain identifier "%s".', $path, $check)); // @translate
                    return null;
                }
            }
            return reset($resourceIdentifier);
        };

        $checkPathMedia = function (string $path) use ($messager, $getItemIdentifierName): ?string {
            $checks = [
                '{media_id}',
                '{media_identifier}',
                '{media_identifier_short}',
                '{media_position}',
            ];
            $resourceIdentifier = array_filter($checks, function ($v) use ($path) {
                return mb_strpos($path, $v) !== false;
            });
            if (count($resourceIdentifier) !== 1) {
                $messager(new Message('The path "%s" for medias should contain one and only one item identifier.', $path)); // @translate
                return null;
            }
            $checks = [
                '{site_slug}',
                '{resource_id}',
                '{resource_identifier}',
                '{resource_identifier_short}',
            ];
            foreach ($checks as $check) {
                if (mb_strpos($path, $check) !== false) {
                    $messager(new Message('The path "%s" for medias should not contain identifier "%s".', $path, $check)); // @translate
                    return null;
                }
            }
            $resourceIdentifier = reset($resourceIdentifier);
            if ($resourceIdentifier === '{media_position}') {
                $itemIdentifier = $getItemIdentifierName($path);
                if (!$itemIdentifier) {
                    $messager(new Message('The path "%s" for medias should contain an item identifier.', $path)); // @translate
                    return null;
                }
            }
            return $resourceIdentifier;
        };

        $checkPatterns = function (string $path) use ($resourceTypes, $params, $messager): bool {
            foreach ($resourceTypes as $resourceType) {
                if (mb_strpos($path, "{{$resourceType}_identifier}") !== false && !$params[$resourceType]['pattern']) {
                    $messager(new Message('A pattern for "%s", for example "[a-zA-Z0-9_-]+", is required to use the path "%s".', $resourceType, $path)); // @translate
                    return false;
                } elseif (mb_strpos($path, "{{$resourceType}_identifier_short}") !== false && !$params[$resourceType]['pattern_short']) {
                    $messager(new Message('A short pattern for "%s", for example "[a-zA-Z0-9_-]+", is required to use the path "%s".', $resourceType, $path)); // @translate
                    return false;
                }
            }
            return true;
        };

        $routeAction = function (string $path): string {
            $route = '';
            if (mb_strpos($path, '{item_set_id}') !== false
                || mb_strpos($path, '{item_set_identifier}') !== false
                || mb_strpos($path, '{item_set_identifier_short}') !== false
            ) {
                $route .= '-item-set';
            }
            if (mb_strpos($path, '{item_id}') !== false
                || mb_strpos($path, '{item_identifier}') !== false
                || mb_strpos($path, '{item_identifier_short}') !== false
            ) {
                $route .= '-item';
            }
            if (mb_strpos($path, '{media_id}') !== false
                || mb_strpos($path, '{media_identifier}') !== false
                || mb_strpos($path, '{media_identifier_short}') !== false
                || mb_strpos($path, '{media_position}') !== false
            ) {
                $route .= '-media';
            }
            return trim($route, '-');
        };

        $getSpecParts = function (string $spec) use ($specs): array {
            $result = [];
            foreach ($specs as $specPart) {
                if (mb_strpos($spec, $specPart) !== false) {
                    $result[] = trim($specPart, '%');
                }
            }
            return $result;
        };

        $siteParts = [];
        if ($params['default_site']) {
            $siteParts[] = 'public';
        }
        if ($params['admin_use']) {
            $siteParts[] = 'admin';
        }
        if ($params['default_site']
            && ($params['site_skip_main'] || !($params['site_slug'] . $params['page_slug']))
        ) {
            $siteParts[] = 'top';
        }

        $resourcesParams = [
            'item_set' => [
                'check' => $checkPathItemSet,
                'controller' => 'item-set',
                'name' => 'item_sets',
            ],
            'item' => [
                'check' => $checkPathItem,
                'controller' => 'item',
                'name' => 'items',
            ],
            'media' => [
                'check' => $checkPathMedia,
                'controller' => 'media',
                'name' => 'media',
            ],
        ];

        $index = 0;
        $mapRoutes = [];

        foreach ($resourcesParams as $resourceType => $resourceParams) {
            foreach ($params[$resourceType]['paths'] as $resourcePath) {
                $resourcePath = $trimSlash($resourcePath);
                $checkPathResource = $resourceParams['check'];
                $resourceIdentifier = $checkPathResource($resourcePath);
                if (empty($resourceIdentifier)) {
                    continue;
                }
                if (!$checkPatterns($resourcePath)) {
                    continue;
                }
                $action = $routeAction($resourcePath);
                if (empty($action)) {
                    continue;
                }
                if ($resourceType === 'item') {
                    $itemSetIdentifierName = $getItemSetIdentifierName($resourcePath);
                } elseif ($resourceType === 'media') {
                    $itemSetIdentifierName = $getItemSetIdentifierName($resourcePath);
                    $itemIdentifierName = $getItemIdentifierName($resourcePath);
                }
                foreach ($siteParts as $sitePart) {
                    $routeName = 'cleanurl_' . $resourceType . '_' . $sitePart . '_' . ++$index;
                    $spec = $baseRoutes[$sitePart]['base_spec'] . str_replace(array_keys($specs), array_values($specs), $resourcePath);
                    $parts = $getSpecParts($spec);
                    $isAdmin = $sitePart === 'admin';
                    if ($sitePart === 'public') {
                        $parts[] = 'site-slug';
                    }
                    $data = [
                        'resource_path' => $resourcePath,
                        'resource_type' => $resourceParams['name'],
                        'resource_identifier' => trim($resourceIdentifier, '{}'),
                        'context' => $isAdmin ? 'admin' : 'site',
                        'regex' => $baseRoutes[$sitePart]['base_regex'] . str_replace(array_keys($regexes), array_values($regexes), $resourcePath),
                        'spec' => $spec,
                        'part' => $sitePart,
                        'parts' => $parts,
                        'route_name' => $routeName,
                        'defaults' => [
                            '__NAMESPACE__' => $baseRoutes[$sitePart]['namespace'],
                            $baseRoutes[$sitePart]['space'] => true,
                            'controller' => 'CleanUrlController',
                            'action' => $action,
                            'site-slug' => $baseRoutes[$sitePart]['site_slug'],
                            // The forward is required to keep original routes,
                            // that can be used by another module. It is build
                            // one time here.
                            'forward_route_name' => $baseRoutes[$sitePart]['forward']['route_name'],
                            'forward' => [
                                '__NAMESPACE__' => $baseRoutes[$sitePart]['forward']['namespace'],
                                $baseRoutes[$sitePart]['space'] => true,
                                'site-slug' => $baseRoutes[$sitePart]['site_slug'],
                                'controller' => $baseRoutes[$sitePart]['forward']['controller'][$resourceType],
                                'action' => $baseRoutes[$sitePart]['forward']['action'],
                                'id' => null,
                                '__CONTROLLER__' => $resourceParams['controller'],
                                'cleanurl_route' => $action,
                            ],
                        ],
                        'options' => [
                            'keep_slash' => $params[$resourceType]['keep_slash'],
                        ],
                    ];
                    if ($isAdmin) {
                        unset($data['defaults']['forward']['site-slug']);
                    }
                    // Manage exceptions and other identifiers.
                    if ($resourceType === 'item_set') {
                        if ($sitePart === 'public' || $sitePart === 'top') {
                            $data['defaults']['forward_route_name'] = 'site/item-set';
                            $data['defaults']['forward']['controller'] = 'Omeka\Controller\Site\Item';
                            $data['defaults']['forward']['action'] = 'browse';
                            $data['defaults']['forward']['item-set-id'] = null;
                            $data['defaults']['forward']['__CONTROLLER__'] = 'item';
                        }
                    } elseif ($resourceType === 'item') {
                        $data['item_set_identifier'] = $itemSetIdentifierName;
                    } elseif ($resourceType === 'media') {
                        $data['item_set_identifier'] = $itemSetIdentifierName;
                        $data['item_identifier'] = $itemIdentifierName;
                    }
                    $params['routes'][$routeName] = $data;
                    $params['route_aliases'][$sitePart][$action][] = $routeName;
                    $mapRoutes[$resourceType][$routeName] = $resourcePath;
                }
                // TODO Add search and browse route (replace or remove last identifier).
            }
        }

        // Add missing routes to simplify url building: use the default one,
        // that is the first in the list.
        $firstRoute = function ($part, $resourceType, $routePath = null) use ($params): ?string {
            foreach ($params['routes'] as $routeName => $route) {
                if ($route['part'] === $part
                    && $route['resource_type'] === $resourceType
                    && (empty($routePath) || $route['resource_path'] === $routePath)
                ) {
                    return $routeName;
                }
            }
            return null;
        };

        // Append the default and short routes.
        foreach (['default', 'short'] as $routeType) {
            foreach ($siteParts as $sitePart) {
                foreach ($resourceTypes as $controllerName => $resourceType) {
                    $routeName = $firstRoute($sitePart, $resourcesParams[$resourceType]['name'], $params[$resourceType][$routeType] ?? null);
                    $params['route_aliases'][$sitePart][$controllerName . '-' . $routeType] = $routeName ? [$routeName] : [];
                }
            }
        }

        // Keep only useful keys.
        $keys = [
            'routes' => [],
            'route_aliases' => [],
        ];
        $settings->set('cleanurl_settings', array_intersect_key($params, $keys));
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
}
