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
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function getConfig()
    {
        require_once file_exists(__DIR__ . '/config/clean_url.config.php')
            ? __DIR__ . '/config/clean_url.config.php'
            :  __DIR__ . '/data/scripts/clean_url.config.php';
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // The page controller is already allowed, because it's an override.
        $this->addRoutes();
    }

    protected function postInstall(): void
    {
        $this->cacheCleanData();
        $this->cacheRouteSettings();
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
        $basePath = $helpers->get('basePath');

        $router
            ->addRoute('clean-url', [
                'type' => \CleanUrl\Router\Http\CleanRoute::class,
                // Check clean url before core and other module routes.
                'priority' => 10,
                'options' => [
                    'api' => $services->get('Omeka\ApiManager'),
                    'getResourceFromIdentifier' => $helpers->get('getResourceFromIdentifier'),
                    'getMediaFromPosition' => $helpers->get('getMediaFromPosition'),
                    'base_path' => $basePath(),
                    'settings' => $settings->get('cleanurl_quick_settings', []),
                ],
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
        return $translate('"Clean Url" module allows to have clean, readable and search engine optimized urls for pages and resources, like https://example.net/item_set_identifier/item_identifier.') // @translate
            . '<br/>'
            . sprintf($translate('See %s for more information.'), // @translate
                sprintf('<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl">%s</a>', 'Readme')
            )
            . '<br/>'
            . sprintf($translate('%sNote%s: For a good seo, it’s not recommended to have multiple urls for the same resource.'), // @translate
                '<strong>', '</strong>'
            )
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

        $params = $form->getData();

        $params['cleanurl_quick_settings'] = [];

        // TODO Move the formatters and validators inside the config form.
        // TODO Remove the default path and take the first.

        // Sanitize params first.

        $trimSlash = function ($v) {
            return trim((string) $v, "/ \t\n\r\0\x0B");
        };
        $params['cleanurl_identifier_prefix'] = trim($params['cleanurl_identifier_prefix']);
        foreach ([
            'cleanurl_site_slug',
            'cleanurl_page_slug',
        ] as $posted) {
            $value = $trimSlash($params[$posted]);
            $params[$posted] = mb_strlen($value) ? $value . '/' : '';
        }
        foreach ([
            'cleanurl_identifier_short',
            'cleanurl_item_set_default',
            'cleanurl_item_set_pattern',
            'cleanurl_item_set_pattern_short',
            'cleanurl_item_default',
            'cleanurl_item_pattern',
            'cleanurl_item_pattern_short',
            'cleanurl_media_default',
            'cleanurl_media_pattern',
            'cleanurl_media_pattern_short',
        ] as $posted) {
            $params[$posted] = $trimSlash($params[$posted]);
        }

        $params['cleanurl_identifier_property'] = (int) $params['cleanurl_identifier_property'];

        $params['cleanurl_item_set_paths'][] = $params['cleanurl_item_set_default'];
        $params['cleanurl_item_set_paths'] = array_unique(array_filter(array_map('trim', $params['cleanurl_item_set_paths'])));
        $params['cleanurl_item_paths'][] = $params['cleanurl_item_default'];
        $params['cleanurl_item_paths'] = array_unique(array_filter(array_map('trim', $params['cleanurl_item_paths'])));
        $params['cleanurl_media_paths'][] = $params['cleanurl_media_default'];
        $params['cleanurl_media_paths'] = array_unique(array_filter(array_map('trim', $params['cleanurl_media_paths'])));

        $params['cleanurl_admin_reserved'] = array_unique(array_filter(array_map('trim', $params['cleanurl_admin_reserved'])));

        // Check config.

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
                $message = new Message('There is no default site: "/s/site-slug" cannot be skipped.'); // @translate
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
                $message = new Message(
                    'The sites "%s" use a reserved string and the "/s/site-slug" cannot be skipped.', // @translate
                    implode('", "', $result)
                );
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
                $message = new Message(
                    'The site pages "%s" use a reserved string and "/s/site-slug" cannot be skipped.', // @translate
                    implode('", "', $result)
                );
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
            $message = new Message('The slug "%s" is used or reserved and the prefix for sites cannot be updated.', $slug); // @translate
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messenger->addError($message);
            return false;
        }

        if (!mb_strlen($slug)) {
            $result = [];
            $slugs = $connection->query('SELECT slug FROM site;')->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new Message(
                    'The sites "%s" use a reserved string and the prefix for sites cannot be removed.', // @translate
                    implode('", "', $result)
                );
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
            $message = new Message('The slug "%s" is used or reserved and the prefix for pages cannot be updated.', $slug); // @translate
            $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
            $messenger->addError($message);
            return false;
        }

        if (!mb_strlen($slug)) {
            $result = [];
            $slugs = $connection->query('SELECT slug FROM site_page;')->fetchAll(\PDO::FETCH_COLUMN);
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
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($message);
                return false;
            }
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
            . $translator->translate('Clean identifier') // @translate
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
        // throw new \Omeka\Api\Exception\ValidationException($message);
    }

    /**
     * Cache site slugs in file config/clean_url.config.php.
     */
    protected function cacheCleanData()
    {
        $services = $this->getServiceLocator();

        $filepath = __DIR__ . '/config/clean_url.config.php';
        if (!$this->checkFilepath($filepath)) {
            $logger = $services->get('Omeka\Logger');
            $logger->warn('The file "clean_url.config.php" in the config directory of the module is not writeable.'); // @translate
            return false;
        }

        $settings = $services->get('Omeka\Settings');

        // The file is always reset from the original file.
        $sourceFilepath = __DIR__ . '/data/scripts/clean_url.config.php';
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
     * Prepare the quick settings and regex one time.
     *
     * @param bool $displayMessages
     */
    protected function cacheRouteSettings($displayMessages = false): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $logger = $services->get('Omeka\Logger');

        $params = [
            'default_site' => (int) $settings->get('default_site'),
            'site_skip_main' => (bool) $settings->get('cleanurl_site_skip_main', false),
            'site_slug' => $settings->get('cleanurl_site_slug', 's/'),
            'page_slug' => $settings->get('cleanurl_site_slug', 'page/'),
            // 10 is the hard-coded id of "dcterms:identifier" in default install.
            'identifier_property' => $settings->get('cleanurl_identifier_property', 10),
            'identifier_prefix' => $settings->get('cleanurl_identifier_prefix', ''),
            'identifier_short' => $settings->get('cleanurl_identifier_short', ''),
            'identifier_prefix_part_of' => (bool) $settings->get('cleanurl_identifier_prefix_part_of', false),
            'identifier_case_sensitive' => (bool) $settings->get('cleanurl_identifier_case_sensitive', false),
            'resource_paths' => $settings->get('cleanurl_resource_paths', []),
            'resource_default' => $settings->get('cleanurl_resource_default', ''),
            'resource_pattern' => $settings->get('cleanurl_resource_pattern', ''),
            'resource_pattern_short' => $settings->get('cleanurl_resource_pattern_short', ''),
            'item_set_paths' => $settings->get('cleanurl_item_set_paths', []),
            'item_set_default' => $settings->get('cleanurl_item_set_default', ''),
            'item_set_pattern' => $settings->get('cleanurl_item_set_pattern', ''),
            'item_set_pattern_short' => $settings->get('cleanurl_item_set_pattern_short', ''),
            'item_paths' => $settings->get('cleanurl_item_paths', []),
            'item_default' => $settings->get('cleanurl_item_default', ''),
            'item_pattern' => $settings->get('cleanurl_item_pattern', ''),
            'item_pattern_short' => $settings->get('cleanurl_item_pattern_short', ''),
            'media_paths' => $settings->get('cleanurl_media_paths', []),
            'media_default' => $settings->get('cleanurl_media_default', ''),
            'media_pattern' => $settings->get('cleanurl_media_pattern', ''),
            'media_pattern_short' => $settings->get('cleanurl_media_pattern_short', ''),
            'admin_use' => $settings->get('cleanurl_admin_use', true),
            'admin_reserved' => $settings->get('cleanurl_admin_reserved', []),
            'regex' => [],
        ];

        // TODO Save the slug sites with the updated slugs_sites (but when the config is edited, the sites don't change).

        $baseRoutes = [
            '_public' => [
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
                        'item_sets' => 'Omeka\Controller\Site\ItemSet',
                        'items' => 'Omeka\Controller\Site\Item',
                        'media' => 'Omeka\Controller\Site\Media',
                    ],
                    'action' => 'show',
                ],
            ],
            '_admin' => [
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
                        'item_sets' => 'Omeka\Controller\Admin\ItemSet',
                        'items' => 'Omeka\Controller\Admin\Item',
                        'media' => 'Omeka\Controller\Admin\Media',
                    ],
                    'action' => 'show',
                ],
            ],
            '_top' => [
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
                        'item_sets' => 'Omeka\Controller\Site\ItemSet',
                        'items' => 'Omeka\Controller\Site\Item',
                        'media' => 'Omeka\Controller\Site\Media',
                    ],
                    'action' => 'show',
                ],
            ],
        ];

        $regexes = [
            '{resource_id}' => '(?P<resource_id>[1-9][0-9]*)',
            '{resource_identifier}' => '(?P<resource_identifier>' . $params['resource_pattern'] . ')',
            '{resource_identifier_short}' => '(?P<resource_identifier_short>' . $params['resource_pattern_short'] . ')',
            '{item_set_id}' => '(?P<item_set_id>[1-9][0-9]*)',
            '{item_set_identifier}' => '(?P<item_set_identifier>' . $params['item_set_pattern'] . ')',
            '{item_set_identifier_short}' => '(?P<item_set_identifier_short>' . $params['item_set_pattern_short'] . ')',
            '{item_id}' => '(?P<item_id>[1-9][0-9]*)',
            '{item_identifier}' => '(?P<item_identifier>' . $params['item_pattern'] . ')',
            '{item_identifier_short}' => '(?P<item_identifier_short>' . $params['item_pattern_short'] . ')',
            '{media_id}' => '(?P<media_id>[1-9][0-9]*)',
            '{media_identifier}' => '(?P<media_identifier>' . $params['media_pattern'] . ')',
            '{media_identifier_short}' => '(?P<media_identifier_short>' . $params['media_pattern_short'] . ')',
            '{media_position}' => '(?P<media_position>[1-9][0-9]*)',
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
                $logger->err($message); // @translate
            };
        }

        $getMediaItemIdentifier = function (string $path): ?string {
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

        $checkPathMedia = function (string $path) use ($messager, $getMediaItemIdentifier): ?string {
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
                $itemIdentifier = $getMediaItemIdentifier($path);
                if (!$itemIdentifier) {
                    $messager(new Message('The path "%s" for medias should contain an item identifier.', $path)); // @translate
                    return null;
                }
            }
            return $resourceIdentifier;
        };

        $checkPatterns = function (string $path) use ($params, $messager): bool {
            if (mb_strpos($path, '{item_set_identifier}') !== false && !$params['item_set_pattern']) {
                $messager(new Message('An item set pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            } elseif (mb_strpos($path, '{item_set_identifier_short}') !== false && !$params['item_set_pattern_short']) {
                $messager(new Message('An item set short pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            } elseif (mb_strpos($path, '{item_identifier}') !== false && !$params['item_pattern']) {
                $messager(new Message('An item pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            } elseif (mb_strpos($path, '{item_identifier_short}') !== false && !$params['item_pattern_short']) {
                $messager(new Message('An item short pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            } elseif (mb_strpos($path, '{media_identifier}') !== false && !$params['media_pattern']) {
                $messager(new Message('A media pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            } elseif (mb_strpos($path, '{media_identifier_short}') !== false && !$params['media_pattern_short']) {
                $messager(new Message('A media short pattern, for example "[a-z][a-z0-9]*" is required to use the path "%s".', $path)); // @translate
                return false;
            }
            return true;
        };

        $routeAction = function (string $path): string {
            $route = 'route';
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
            return $route;
        };

        $siteParts = [];
        if ($params['default_site']) {
            $siteParts[] = '_public';
        }
        if ($params['admin_use']) {
            $siteParts[] = '_admin';
        }
        if ($params['default_site'] && $params['site_skip_main']) {
            $siteParts[] = '_top';
        }

        $resourcesParams = [
            'item_sets' => [
                'check' => $checkPathItemSet,
                'name' => 'item_set',
                'paths' => 'item_set_paths',
            ],
            'items' => [
                'check' => $checkPathItem,
                'name' => 'item',
                'paths' => 'item_paths',
            ],
            'media' => [
                'check' => $checkPathMedia,
                'name' => 'media',
                'paths' => 'media_paths',
            ],
        ];

        $index = 0;

        foreach ($resourcesParams as $resourceType => $resourceParams) {
            foreach ($params[$resourceParams['paths']] as $resourcePath) {
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
                if ($action === 'route') {
                    continue;
                }
                if ($resourceType === 'media' && $resourceIdentifier === '{media_position}') {
                    $itemIdentifier = $getMediaItemIdentifier($resourcePath);
                }
                foreach ($siteParts as $sitePart) {
                    $routeName = 'cleanurl_' . $resourceParams['name'] . $sitePart . '_' . ++$index;
                    $data = [
                        'resource_path' => $resourcePath,
                        'resource_type' => $resourceType,
                        'resource_identifier' => trim($resourceIdentifier, '{}'),
                        'regex' => $baseRoutes[$sitePart]['base_regex'] . str_replace(array_keys($regexes), array_values($regexes), $resourcePath),
                        'spec' => $baseRoutes[$sitePart]['base_spec'] . str_replace(array_keys($specs), array_values($specs), $resourcePath),
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
                                'cleanurl_route' => $action,
                            ],
                        ],
                    ];
                    // Manage an exception. The item identifier is already checked.
                    if ($resourceType === 'media' && $data['resource_identifier'] === 'media_position') {
                        $data['item_identifier'] = $itemIdentifier;
                    }
                    $params['regex'][$routeName] = $data;
                }
                // TODO Add search and browse route (replace or remove last identifier).
            }
        }

        $settings->set('cleanurl_quick_settings', $params);
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
