<?php declare(strict_types=1);

namespace CleanUrl;

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

use CleanUrl\Controller;
use CleanUrl\Form\ConfigForm;
use CleanUrl\Stdlib\IdentifierChecker;
use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

/**
 * Clean Url
 *
 * Allows to have links like https://example.net/collection/dcterms:identifier.
 *
 * @copyright Daniel Berthereau, 2012-2026
 * @copyright BibLibre, 2016-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    /**
     * Maximum number of warnings about identifiers in the same request.
     *
     * @var int
     */
    const IDENTIFIER_WARNINGS = 5;

    /**
     * Number of warnings about identifiers in the current request.
     *
     * @var int
     */
    protected $identifierWarnings = 0;

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

        $config = $this->copyChildRoutesToTop($config);

        $configListener->setMergedConfig($config);
    }

    /**
     * Copy child routes of site to top for main site: skip s/slug/, remove leading /.
     *
     * Should be a specific function to manage tests.
     *
     * The "top" route serves the main site home with controller "Page", but
     * child routes inherit the controller from their parent. Under "site" they
     * inherit "Index" (the site default controller); module routes without an
     * explicit controller (for example Collecting) must keep it under "top"
     * too, otherwise they resolve to a "...\Page" controller that does not
     * exist (page not found on submit with module Collecting).
     */
    protected function copyChildRoutesToTop(array $config): array
    {
        $siteController = $config['router']['routes']['site']['options']['defaults']['controller'] ?? null;
        foreach ($config['router']['routes']['site']['child_routes'] as $routeName => $options) {
            // Skip some routes for pages that are set directly in the config.
            if (isset($config['router']['routes']['top']['child_routes'][$routeName])) {
                continue;
            }
            if ($siteController !== null && !isset($options['options']['defaults']['controller'])) {
                $options['options']['defaults']['controller'] = $siteController;
            }
            $config['router']['routes']['top']['child_routes'][$routeName] = $options;
            $config['router']['routes']['top']['child_routes'][$routeName]['options']['route'] =
                ltrim($options['options']['route'], '/');
        }
        return $config;
    }

    public function getConfig()
    {
        require_once __DIR__ . '/config/cleanurl.config.php';

        // Dynamic route constants are stored in a setting and defined here
        // because the service manager is not yet available at this stage.
        if (!defined('CleanUrl\SLUG_MAIN_SITE')) {
            $data = $this->readRouteData();
            define('CleanUrl\SLUG_MAIN_SITE', $data['main_site'] ?? false);
            define('CleanUrl\SLUG_SITE', $data['site'] ?? 's/');
            define('CleanUrl\SLUG_PAGE', $data['page'] ?? 'page/');
            define('CleanUrl\SLUGS_SITE', $data['sites'] ?? '');
        }

        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @see https://forum.omeka.org/t/csv-import-error-call-to-a-member-function-getparam/28675 */
        // In CLI context (background jobs), set a minimal RouteMatch on the
        // MvcEvent to prevent Status::getRouteMatch() from falling back to
        // $router->match(), which can match CleanUrl routes and flag the
        // request as a site request. This avoids "Call to a member function
        // getParam() on null" in siteUrl() when modules serialize
        // representations during jobs (e.g. CSVImport).
        // TODO Remove once integrated in Omeka (https://github.com/omeka/omeka-s/pull/2439).
        if (PHP_SAPI === 'cli' && !$event->getRouteMatch()) {
            $event->setRouteMatch(new \Laminas\Router\Http\RouteMatch([]));
        }

        // The page controller is already allowed, because it's an override.
        $this->addRoutes();

        // The dereferenceable uri "/id/{identifier}" is public, like the pages
        // of the resources it redirects to.
        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(null, Controller\IdentifierController::class);

        // Rebuild the route data cache when it is missing, typically right
        // after a deployment of this version: the file does not exist yet and
        // is otherwise only (re)built on a relevant event (install, upgrade,
        // config or site save). This paid once keeps the main site routes
        // available without a database read in getConfig().
        if (!is_readable($this->getRouteDataCachePath())) {
            $this->cacheCleanData();
        }
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');

        $errors = [];

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
            $errors[] = (string) new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.91'
            );
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/cleanurl')) {
            $errors[] = (string) (new PsrMessage(
                'The directory "{directory}" is not writeable.', // @translate
                ['directory' => $basePath . '/xsl']
            ))->setTranslator($translator);
        }

        if ($errors) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(implode("\n", $errors));
        }
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
        $defaultSettings = [
            'routes' => [],
            'route_aliases' => [],
        ];
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

        // The main site is the general Omeka setting "default_site", and the
        // site prefixes are module settings; both can change at any time. Omeka
        // 4.2 triggers "setting.update"/"setting.insert" on the settings
        // service, so the route data cache is rebuilt when a relevant setting
        // changes (no effect on Omeka < 4.2, where the event is not triggered).
        $sharedEventManager->attach(
            \Omeka\Settings\Settings::class,
            'setting.update',
            [$this, 'handleMainSettingChange']
        );
        $sharedEventManager->attach(
            \Omeka\Settings\Settings::class,
            'setting.insert',
            [$this, 'handleMainSettingChange']
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

        // Add the check of identifiers to the tasks of module Easy Admin.
        $sharedEventManager->attach(
            \EasyAdmin\Form\CheckAndFixForm::class,
            'form.add_elements',
            [$this, 'handleEasyAdminJobsForm']
        );
        $sharedEventManager->attach(
            \EasyAdmin\Controller\Admin\CheckAndFixController::class,
            'easyadmin.job',
            [$this, 'handleEasyAdminJobs']
        );

        // Warn when a saved resource has an identifier without clean url.
        foreach ([
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
        ] as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.create.post',
                [$this, 'handleCheckResourceIdentifier']
            );
            $sharedEventManager->attach(
                $adapter,
                'api.update.post',
                [$this, 'handleCheckResourceIdentifier']
            );
        }

        // Add a canonical link to the clean url on public resource and page
        // pages, so search engines do not index the duplicate (original and
        // clean) urls as separate pages.
        foreach ([
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
            'Omeka\Controller\Site\Page',
            'DigitalObject\Controller\Site\DigitalObject',
        ] as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'handleCanonicalUrl']
            );
        }

        // Check the identifiers during the audit of a template (module Advanced
        // Resource Template).
        $sharedEventManager->attach(
            'AdvancedResourceTemplate',
            'advancedresourcetemplate.audit.checkers',
            [$this, 'handleAuditChecker']
        );

        // Allow to read a resource with its identifier through the api.
        foreach ([
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            'DigitalObject\Api\Adapter\DigitalObjectAdapter',
        ] as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.read.pre',
                [$this, 'handleApiReadIdentifier']
            );
        }
    }

    /**
     * Read a resource with its identifier, not only with its internal id.
     *
     * Only the read is managed: an identifier is a metadata that can be edited
     * or duplicated, so it is not a safe target for an update or a delete.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/7
     */
    public function handleApiReadIdentifier(Event $event): void
    {
        $services = $this->getServiceLocator();
        if (!$services->get('Omeka\Settings')->get('cleanurl_api_identifier')) {
            return;
        }

        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $id = $request->getId();

        // An internal id is always numeric, so keep the standard process, that
        // is quicker and that avoids any ambiguity with a numeric identifier.
        if (is_numeric($id) || !is_string($id) || !strlen($id)) {
            return;
        }

        // Only the id is searched, without building any representation: an api
        // request cannot run another one inside itself safely.
        $resourceName = $event->getTarget()->getResourceName();
        $helper = $services->get('ViewHelperManager')->get('getResourcesFromIdentifiers');
        [$result, $variants] = $helper->searchIdsFromIdentifiers(
            [$this->trimIdentifier($id) => null],
            $resourceName,
            $event->getTarget()->getEntityClass()
        );

        $matching = array_intersect_key($result, $variants);
        if ($matching) {
            $request->setId((int) reset($matching));
        }
    }

    /**
     * Clean an identifier like the view helpers do.
     */
    protected function trimIdentifier($identifier): string
    {
        return trim(rawurldecode((string) $identifier), " \t\n\r\0\x0B\u{a0}\u{feff}");
    }

    /**
     * Report the identifiers that cannot be used to build a clean url.
     *
     * Nothing is fixed: an identifier is a metadata with an external meaning
     * (ark, shelf mark), so the remediation belongs to the administrator, by
     * widening the pattern or by normalizing the values with module Bulk Edit.
     */
    public function handleAuditChecker(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $easyMeta = $services->get('Common\EasyMeta');
        $checker = new IdentifierChecker($services->get('Omeka\Connection'));

        // Prepare the options of each resource type once.
        $config = [];
        foreach ($checker->resourceTypes() as $resourceType => $resourceName) {
            $options = $settings->get('cleanurl_' . $resourceType);
            if (!is_array($options) || empty($options['property'])) {
                continue;
            }
            $term = $easyMeta->propertyTerm((int) $options['property']);
            if (!$term) {
                continue;
            }
            $config[$resourceName] = [
                'options' => $options,
                'term' => $term,
                'modes' => $checker->identifierModes($options, $resourceType),
            ];
        }
        if (!$config) {
            return;
        }

        $checkers = (array) $event->getParam('checkers');
        $checkers[] = function ($resource) use ($checker, $config): array {
            $resourceName = $resource->resourceName();
            if (!isset($config[$resourceName])) {
                return [];
            }

            $options = $config[$resourceName]['options'];
            $prefix = (string) ($options['prefix'] ?? '');
            $lengthPrefix = mb_strlen($prefix);

            // The identifier is the first literal value of the property, like
            // in the job that checks all the identifiers.
            $identifierValue = null;
            foreach ($resource->value($config[$resourceName]['term'], ['all' => true, 'type' => 'literal']) as $value) {
                $val = (string) $value->value();
                if ($lengthPrefix && mb_strpos($val, $prefix) !== 0) {
                    continue;
                }
                $identifierValue = $val;
                break;
            }
            if ($identifierValue === null) {
                return [];
            }

            $issues = [];
            foreach ($config[$resourceName]['modes'] as $short) {
                $identifier = $short && $lengthPrefix
                    ? trim(mb_substr($identifierValue, $lengthPrefix))
                    : $identifierValue;
                if ($checker->isValidIdentifier($identifier, $options, $short)) {
                    continue;
                }
                $characters = $checker->offendingCharacters($identifier, $options, $short);
                $issues[] = [
                    'message' => 'Resource #{resource_id}: the identifier "{identifier}" has no clean url. Characters to add to the pattern: {characters}', // @translate
                    'context' => [
                        'resource_id' => $resource->id(),
                        'identifier' => $identifier,
                        'characters' => $characters ? implode(' ', $characters) : '-',
                    ],
                ];
            }
            return $issues;
        };
        $event->setParam('checkers', $checkers);
    }

    /**
     * Add a canonical link to the clean url, only when the current url is not
     * already the clean one (no self-referencing link).
     *
     * @param Event $event
     */
    public function handleCanonicalUrl(Event $event): void
    {
        $view = $event->getTarget();
        if (!$view->setting('cleanurl_canonical')) {
            return;
        }

        // Item/media show use "resource", page show uses "page", digital object
        // show uses "digitalObject".
        $resource = $view->resource ?? $view->page ?? null;
        if (!$resource) {
            return;
        }

        $canonical = $this->canonicalUrl(
            $resource->siteUrl(null, true),
            (string) $view->serverUrl(true)
        );
        if ($canonical === null) {
            return;
        }

        // Don't add a second canonical link when the theme already set one.
        $headLink = $view->headLink();
        foreach ($headLink->getContainer() as $link) {
            if (($link->rel ?? null) === 'canonical') {
                return;
            }
        }
        $headLink(['rel' => 'canonical', 'href' => $canonical]);
    }

    /**
     * Return the clean url to use as canonical, or null when the current url is
     * already the clean one (so no self-referencing canonical is added) or when
     * there is no clean url.
     *
     * The comparison is done on the path only (ignoring the query string and a
     * trailing slash).
     */
    public function canonicalUrl(?string $cleanUrl, string $currentUrl): ?string
    {
        if (!$cleanUrl) {
            return null;
        }
        $cleanPath = rtrim((string) parse_url($cleanUrl, PHP_URL_PATH), '/');
        $currentPath = rtrim((string) parse_url($currentUrl, PHP_URL_PATH), '/');
        return $cleanPath === $currentPath ? null : $cleanUrl;
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
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

        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return null;
        }

        $settings = $services->get('Omeka\Settings');
        $this->initDataToPopulate($settings, 'config');
        $data = $this->prepareDataToPopulate($settings, 'config');
        if ($data === null) {
            return null;
        }

        /** @var \CleanUrl\Form\ConfigForm $form */
        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($data);
        $form->prepare();

        // The tabs are declared by the form, each element carrying its own tab.
        // @see \CleanUrl\Form\ConfigForm
        return $html
            . $renderer->formTabs($form, [], 'cleanurl.config.section_nav');
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

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $hasError = false;

        // TODO Move the formatters and validators inside the config form.

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
                $message = new PsrMessage(
                    'Set a default site if you want to remove the part "/s/site-slug".' // @translate
                );
                $messenger->addError($message);
                return false;
            }

            // Check all pages of the default site.
            // TODO Manage the case where the default site is updated after (rare).
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site ORDER BY id ASC;')->fetchFirstColumn();
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new PsrMessage(
                    'The sites "{site_slugs}" use a reserved string which prevents "/s/site-slug" from being removed. Rename these sites if you want to skip "/s/site-slug". See the {link}list of reserved strings{link_end}.', // @translate
                    ['site_slugs' => htmlspecialchars(implode('", "', $result), ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
                );
                $message->setEscapeHtml(false);
                $messenger->addError($message);
                $hasError = true;
            }
            $slugs = $connection->executeQuery('SELECT slug FROM site_page ORDER BY id ASC;')->fetchFirstColumn();
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|') !== false) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new PsrMessage(
                    'The sites pages "{page_slugs}" use a reserved string or a site slug which prevents "/s/site-slug" from being removed. Rename these pages if you want to skip "/s/site-slug". See the {link}list of reserved strings{link_end}.', // @translate
                    ['page_slugs' => htmlspecialchars(implode('", "', $result), ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
                );
                $message->setEscapeHtml(false);
                $messenger->addError($message);
                $hasError = true;
            }
        }

        // Check the option site slug.
        if (mb_strlen($siteSlug)
            && $siteSlug !== 's/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($siteSlug, '/') . '|') !== false
        ) {
            $message = new PsrMessage(
                'The prefix "{slug}" is reserved, which prevents from being used as a prefix. Use another prefix. See the {link}list of reserved strings{link_end}.', // @translate
                ['slug' => htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
            );
            $message->setEscapeHtml(false);
            $messenger->addError($message);
            $hasError = true;
        } elseif (mb_strlen($siteSlug)
            && $siteSlug !== 's/'
            && mb_stripos('|' . SLUGS_SITE . '|', '|' . trim($siteSlug, '/') . '|') !== false
        ) {
            $message = new PsrMessage(
                'The prefix "{slug}" is already set for a site, which prevents from being used as a prefix. Use another prefix or rename the site. See the {link}list of reserved strings{link_end}.', // @translate
                ['slug' => htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
            );
            $message->setEscapeHtml(false);
            $messenger->addError($message);
            $hasError = true;
        } else {
            // Check the existing slugs with reserved slugs.
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site ORDER by id ASC;')->fetchFirstColumn();
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if (count($result)) {
                $message = new PsrMessage(
                    'The sites "{site_slugs}" use a reserved string which prevents the prefix for site from being removed. Rename these sites if you want to skip the prefix. See the {link}list of reserved strings{link_end}.', // @translate
                    ['site_slugs' => htmlspecialchars(implode('", "', $result), ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
                );
                $message->setEscapeHtml(false);
                $messenger->addError($message);
                $hasError = true;
            }
        }

        // Check the option page slug.
        if (mb_strlen($pageSlug)
            && $pageSlug !== 'page/'
            && mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . trim($pageSlug, '/') . '|') !== false
        ) {
            $message = new PsrMessage(
                'The prefix "{slug}" is reserved, which prevents from being used as a prefix for pages. Use another prefix. See the {link}list of reserved strings{link_end}.', // @translate
                ['slug' => htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
            );
            $message->setEscapeHtml(false);
            $messenger->addError($message);
            $hasError = true;
        } elseif (mb_strlen($pageSlug)
            && $pageSlug !== 'page/'
            && mb_stripos('|' . SLUGS_SITE . '|', '|' . trim($pageSlug, '/') . '|') !== false
        ) {
            $message = new PsrMessage(
                'The prefix "{slug}" is already set for a site, which prevents from being used as a prefix for pages. Use another prefix or rename the site. See the {link}list of reserved strings{link_end}.', // @translate
                ['slug' => htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
            );
            $message->setEscapeHtml(false);
            $messenger->addError($message);
            $hasError = true;
        } else {
            // Check the existing slugs with reserved slugs.
            $result = [];
            $slugs = $connection->executeQuery('SELECT slug FROM site_page ORDER BY id ASC;')->fetchFirstColumn();
            foreach ($slugs as $slug) {
                if (mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|' . SLUGS_SITE . '|', '|' . trim($slug, '/') . '|')) {
                    $result[] = $slug;
                }
            }
            if ($result) {
                $message = new PsrMessage(
                    'The sites pages "{page_slugs}" use a reserved string which prevents the prefix for pages from being removed. Rename these pages if you want to skip the prefix. See the {link}list of reserved strings{link_end}.', // @translate
                    ['page_slugs' => htmlspecialchars(implode('", "', $result), ENT_QUOTES, 'UTF-8'), 'link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/blob/master/config/cleanurl.config.php"  target="_blank" rel="noopener">', 'link_end' => '</a>']
                );
                $message->setEscapeHtml(false);
                $messenger->addError($message);
                $hasError = true;
            }
        }

        $resourceTypes = ['item_set', 'item', 'media'];
        if (class_exists('DigitalObject\Module', false)) {
            $resourceTypes[] = 'digital_object';
        }

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
                        $message = new PsrMessage(
                            'A pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}".', // @translate
                            ['resource_type' => $resource, 'path' => $path]
                        );
                        $messenger->addError($message);
                        $hasError = true;
                    }
                    if (!$hasPattern[$resource]['full'] && !$hasPattern[$resource]['short'] && mb_strpos($path, "{{$resource}_identifier_short}") !== false) {
                        $message = new PsrMessage(
                            'A pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}".', // @translate
                            ['resource_type' => $resource, 'path' => $path]
                        );
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
        $this->checkIdentifiers($params);

        // The post is read again, because $params was reduced to the settings.
        if (!empty($controller->getRequest()->getPost()['cleanurl_check']['process_check'])) {
            $this->dispatchCheckIdentifiers($controller);
        }

        return true;
    }

    /**
     * Run the full check of identifiers as a background job.
     */
    protected function dispatchCheckIdentifiers(AbstractController $controller): void
    {
        $services = $this->getServiceLocator();

        $job = $services->get(\Omeka\Job\Dispatcher::class)
            ->dispatch(\CleanUrl\Job\CheckIdentifiers::class);

        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $message = new PsrMessage(
            'Checking identifiers in a background job ({link_job}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
            [
                'link_job' => sprintf('<a href="%1$s">', htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', htmlspecialchars($urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                    : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
            ]
        );
        $message->setEscapeHtml(false);
        $controller->messenger()->addSuccess($message);
    }

    /**
     * Add the check of identifiers to the tasks of module Easy Admin.
     */
    public function handleEasyAdminJobsForm(Event $event): void
    {
        /**
         * @var \EasyAdmin\Form\CheckAndFixForm $form
         * @var \Laminas\Form\Element\Radio $process
         */
        $form = $event->getTarget();
        $fieldset = $form->get('module_tasks');

        $process = $fieldset->get('process');
        $valueOptions = $process->getValueOptions();
        $valueOptions['cleanurl_check_identifiers'] = 'Clean Url: Check identifiers'; // @translate
        $process->setValueOptions($valueOptions);

        // Describe the task for the check-and-fix ui (recent Easy Admin only).
        if (method_exists($form, 'addTaskSubjects')) {
            $form->addTaskSubjects([
                'cleanurl_check_identifiers' => [
                    'name' => 'Clean Url: Check identifiers', // @translate
                    'description' => 'List the identifiers that have no clean url, because they don’t match the pattern or because they are a reserved word, in a tabular file saved in the directory "files/cleanurl".', // @translate
                    'actions' => [
                        'cleanurl_check_identifiers' => 'Check', // @translate
                    ],
                ],
            ]);
        }

        // The task is not flagged as dangerous, unlike most of the tasks of
        // Easy Admin: it only reads the identifiers and writes a report, and
        // never modifies any resource.
    }

    /**
     * Run the check of identifiers from the tasks of module Easy Admin.
     */
    public function handleEasyAdminJobs(Event $event): void
    {
        $process = $event->getParam('process');
        if ($process === 'cleanurl_check_identifiers') {
            $event->setParam('job', \CleanUrl\Job\CheckIdentifiers::class);
            $event->setParam('args', []);
        }
    }

    /**
     * Warn about identifiers that cannot be used to build a clean url.
     *
     * The check is done only for the resource types whose paths use an
     * identifier: a path built with an id, like the default one for medias
     * ("document/{item_identifier}/{media_id}"), needs no identifier.
     */
    protected function checkIdentifiers(array $params): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $checker = new Stdlib\IdentifierChecker($services->get('Omeka\Connection'));

        foreach ($checker->resourceTypes() as $resourceType => $resourceName) {
            $options = $params['cleanurl_' . $resourceType] ?? null;
            if (!$options) {
                continue;
            }
            foreach ($checker->identifierModes($options, $resourceType) as $short) {
                $check = $checker->checkResourceType($resourceName, $options, $short);
                if (!$check['invalid']) {
                    continue;
                }
                $messenger->addWarning(new PsrMessage(
                    '{resource_name}: {count} identifiers on {total} have no clean url, because they don’t match the pattern (for example "{identifier}"). Characters to add to the pattern: {characters}', // @translate
                    [
                        'resource_name' => $resourceName,
                        'count' => $check['invalid'],
                        'total' => $check['total'],
                        'identifier' => (string) reset($check['examples']),
                        'characters' => $check['characters'] ? implode(' ', $check['characters']) : '-',
                    ]
                ));
            }
        }
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
     * Rebuild the route data cache when a routing-related setting changes.
     *
     * The main site comes from the general Omeka setting "default_site" and the
     * prefixes from the module settings; both feed cacheCleanData(). The
     * computed setting "cleanurl_route_data" is excluded to avoid an infinite
     * loop, since cacheCleanData() writes it.
     *
     * @param Event $event
     */
    public function handleMainSettingChange(Event $event): void
    {
        $relevant = [
            'default_site',
            'cleanurl_site_skip_main',
            'cleanurl_site_slug',
            'cleanurl_page_slug',
        ];
        if (in_array($event->getParam('id'), $relevant, true)) {
            $this->cacheCleanData();
        }
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
     * Warn when a saved resource has an identifier without clean url.
     */
    public function handleCheckResourceIdentifier(Event $event): void
    {
        // A batch edit saves many resources in the same request, so limit the
        // number of warnings.
        if ($this->identifierWarnings >= self::IDENTIFIER_WARNINGS) {
            return;
        }

        $services = $this->getServiceLocator();

        // Warn only in the admin interface: the api and the bulk processes may
        // save thousands of resources and would flood the messenger. The route
        // match is read directly from the mvc event, because
        // Status::isAdminRequest() runs the router again when the route match
        // is not set yet, for example during a job.
        $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
        if (!$routeMatch || !$routeMatch->getParam('__ADMIN__')) {
            return;
        }

        $checker = new Stdlib\IdentifierChecker($services->get('Omeka\Connection'));
        $resourceTypes = array_flip($checker->resourceTypes());
        $resourceName = $event->getTarget()->getResourceName();
        if (!isset($resourceTypes[$resourceName])) {
            return;
        }
        $resourceType = $resourceTypes[$resourceName];

        $options = $services->get('Omeka\Settings')->get('cleanurl_' . $resourceType);
        if (!is_array($options)) {
            return;
        }
        $modes = $checker->identifierModes($options, $resourceType);
        if (!$modes) {
            return;
        }

        $resource = $event->getParam('response')->getContent();
        if (!is_object($resource)) {
            return;
        }

        $getResourceIdentifier = $services->get('ViewHelperManager')->get('getResourceIdentifier');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        foreach ($modes as $short) {
            $identifier = (string) $getResourceIdentifier($resource, false, $short);
            // Without identifier, the url is built with the resource id, so
            // there is nothing to check.
            if (!mb_strlen($identifier)
                || $checker->isValidIdentifier($identifier, $options, $short)
            ) {
                continue;
            }
            $characters = $checker->offendingCharacters($identifier, $options, $short);
            ++$this->identifierWarnings;
            $messenger->addWarning(new PsrMessage(
                'The identifier "{identifier}" has no clean url, because it doesn’t match the pattern. Characters to add to the pattern: {characters}', // @translate
                [
                    'identifier' => $identifier,
                    'characters' => $characters ? implode(' ', $characters) : '-',
                ]
            ));
        }
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

        $data['o:slug'] .= '_' . substr(strtr(base64_encode(random_bytes(128)), ['+' => '', '/' => '', '=' => '']), 0, 4);
        $request->setContent($data);

        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        $message = new PsrMessage(
            'The slug "{slug}" is used or reserved. A random string has been automatically appended.', // @translate
            ['slug' => $slug]
        );
        $messenger->addWarning($message);
        // throw new \Omeka\Api\Exception\ValidationException((string) $message);
    }

    /**
     * Cache dynamic route data (site slugs, prefixes) in a setting.
     */
    protected function cacheCleanData()
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Compute main site.
        $default = $settings->get('default_site', '');
        $skip = $settings->get('cleanurl_site_skip_main');
        $siteSlug = $settings->get('cleanurl_site_slug');
        $pageSlug = $settings->get('cleanurl_page_slug');

        $skip = $skip
            || !($siteSlug . $pageSlug);
        $mainSite = false;
        if ($skip && $default) {
            try {
                $mainSite = $services->get('Omeka\ApiManager')
                    ->read('sites', ['id' => $default])->getContent()->slug();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $mainSite = false;
            }
        }

        // Site prefix.
        $siteSlug = trim($settings->get('cleanurl_site_slug', ''), ' /');
        $siteSlug = mb_strlen($siteSlug) ? $siteSlug . '/' : '';

        // Page prefix.
        $pageSlug = trim($settings->get('cleanurl_page_slug', ''), ' /');
        $pageSlug = mb_strlen($pageSlug) ? $pageSlug . '/' : '';

        // All site slugs as regex.
        $slugs = $services->get('Omeka\Connection')
            ->executeQuery('SELECT slug FROM site ORDER BY id ASC;')
            ->fetchFirstColumn();
        $slugsSite = $this->prepareRegex($slugs);

        $data = [
            'main_site' => $mainSite ?: false,
            'site' => $siteSlug,
            'page' => $pageSlug,
            'sites' => $slugsSite,
        ];
        $settings->set('cleanurl_route_data', $data);
        $this->writeRouteDataCache($data);

        return true;
    }

    /**
     * Path of the file caching the dynamic route data (site slugs, prefixes).
     *
     * The data is computed by cacheCleanData() with the service manager, then
     * cached in this file so getConfig() can read it without any database
     * access, since neither the service manager nor the database connection are
     * available at that bootstrap stage.
     */
    private function getRouteDataCachePath(): string
    {
        return OMEKA_PATH . '/files/cleanurl/route-data.json';
    }

    /**
     * Read dynamic route data from the file cache.
     *
     * Returns an empty array when the cache is missing (fresh install, or right
     * after an upgrade before cacheCleanData() runs): the default routing then
     * applies until the cache is (re)built in onBootstrap() or on config save.
     */
    private function readRouteData(): array
    {
        $path = $this->getRouteDataCachePath();
        if (!is_readable($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Write the dynamic route data to the file cache.
     */
    private function writeRouteDataCache(array $data): void
    {
        $path = $this->getRouteDataCachePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $path,
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
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
        $resourceTypes = [
            'item-set' => 'item_set',
            'item' => 'item',
            'media' => 'media',
        ];
        $hasDigitalObject = class_exists('DigitalObject\Module', false);
        if ($hasDigitalObject) {
            $resourceTypes['digital-object'] = 'digital_object';
        }

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
            'page_slug' => $settings->get('cleanurl_page_slug', 'page/'),
            'resource' => $settings->get('cleanurl_resource', $defaults) + $defaults,
            'item_set' => $settings->get('cleanurl_item_set', $defaults) + $defaults,
            'item' => $settings->get('cleanurl_item', $defaults) + $defaults,
            'media' => $settings->get('cleanurl_media', $defaults) + $defaults,
            'digital_object' => $settings->get('cleanurl_digital_object', $defaults) + $defaults,
            'admin_use' => $settings->get('cleanurl_admin_use', true),
            'admin_reserved' => $settings->get('cleanurl_admin_reserved', []),
            'routes' => [],
            'route_aliases' => [],
        ];

        // TODO Save the slug sites with the updated slugs_sites (but when the config is edited, the sites don't change).

        // Default, short and core urls are merged to manage paths simpler,
        // Set the default route the first in stacks if any for performance.
        // foreach (['resource' => 'resource', 'item_set' => 'item-set', 'item'
        // => 'item', 'media' => 'media'] as $resourceType => $controller) {
        $normalizeTypes = ['resource', 'item_set', 'item', 'media'];
        if ($hasDigitalObject) {
            $normalizeTypes[] = 'digital_object';
        }
        foreach ($normalizeTypes as $resourceType) {
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
                        'digital_object' => 'DigitalObject\Controller\Site\DigitalObject',
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
                    'route_name' => 'admin/id',
                    'namespace' => 'Omeka\Controller\Admin',
                    'controller' => [
                        'item_set' => 'Omeka\Controller\Admin\ItemSet',
                        'item' => 'Omeka\Controller\Admin\Item',
                        'media' => 'Omeka\Controller\Admin\Media',
                        'digital_object' => 'DigitalObject\Controller\Admin\DigitalObject',
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
                        'digital_object' => 'DigitalObject\Controller\Site\DigitalObject',
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
            '{digital_object_id}' => '(?P<digital_object_id>\d+)',
            '{digital_object_identifier}' => '(?P<digital_object_identifier>' . $params['digital_object']['pattern'] . ')',
            '{digital_object_identifier_short}' => '(?P<digital_object_identifier_short>' . $params['digital_object']['pattern_short'] . ')',
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
            '{digital_object_id}' => '%digital_object_id%',
            '{digital_object_identifier}' => '%digital_object_identifier%',
            '{digital_object_identifier_short}' => '%digital_object_identifier_short%',
        ];

        $trimSlash = function ($v) {
            return trim((string) $v, "/ \t\n\r\0\x0B");
        };

        if ($displayMessages) {
            /** @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger */
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
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
                $messager(new PsrMessage(
                    'The path "{path}" for item sets should contain one and only one item set identifier.', // @translate
                    ['path' => $path]
                ));
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
                    $messager(new PsrMessage(
                        'The path "{path}" for item sets should not contain identifier "{identifier}".', // @translate
                        ['path' => $path, 'identifier' => $check]
                    ));
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
                $messager(new PsrMessage(
                    'The path "{path}" for items should contain one and only one item identifier.', // @translate
                    ['path' => $path]
                ));
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
                    $messager(new PsrMessage(
                        'The path "{path}" for items should not contain identifier "{identifier}".', // @translate
                        ['path' => $path, 'identifier' => $check]
                    ));
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
                $messager(new PsrMessage(
                    'The path "{path}" for medias should contain one and only one item identifier.', // @translate
                    ['path' => $path]
                ));
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
                    $messager(new PsrMessage(
                        'The path "{path}" for medias should not contain identifier "{identifier}".', // @translate
                        ['path' => $path, 'identifier' => $check]
                    ));
                    return null;
                }
            }
            $resourceIdentifier = reset($resourceIdentifier);
            if ($resourceIdentifier === '{media_position}') {
                $itemIdentifier = $getItemIdentifierName($path);
                if (!$itemIdentifier) {
                    $messager(new PsrMessage(
                        'The path "{path}" for medias should contain an item identifier.', // @translate
                        ['path' => $path]
                    ));
                    return null;
                }
            }
            return $resourceIdentifier;
        };

        $checkPathDigitalObject = function (string $path) use ($messager): ?string {
            $checks = [
                '{digital_object_id}',
                '{digital_object_identifier}',
                '{digital_object_identifier_short}',
            ];
            $resourceIdentifier = array_filter($checks, function ($v) use ($path) {
                return mb_strpos($path, $v) !== false;
            });
            if (count($resourceIdentifier) !== 1) {
                $messager(new PsrMessage(
                    'The path "{path}" for digital objects should contain one and only one digital object identifier.', // @translate
                    ['path' => $path]
                ));
                return null;
            }
            $checks = [
                '{site_slug}',
                '{resource_id}',
                '{resource_identifier}',
                '{resource_identifier_short}',
                '{item_set_id}',
                '{item_set_identifier}',
                '{item_set_identifier_short}',
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
                    $messager(new PsrMessage(
                        'The path "{path}" for digital objects should not contain identifier "{identifier}".', // @translate
                        ['path' => $path, 'identifier' => $check]
                    ));
                    return null;
                }
            }
            return reset($resourceIdentifier);
        };

        $checkPatterns = function (string $path) use ($resourceTypes, $params, $messager): bool {
            foreach ($resourceTypes as $resourceType) {
                if (mb_strpos($path, "{{$resourceType}_identifier}") !== false && !$params[$resourceType]['pattern']) {
                    $messager(new PsrMessage(
                        'A pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}".', // @translate
                        ['resource_type' => $resourceType, 'path' => $path]
                    ));
                    return false;
                } elseif (mb_strpos($path, "{{$resourceType}_identifier_short}") !== false && !$params[$resourceType]['pattern_short']) {
                    $messager(new PsrMessage(
                        'A short pattern for "{resource_type}", for example "[a-zA-Z0-9_-]+", is required to use the path "{path}".', // @translate
                        ['resource_type' => $resourceType, 'path' => $path]
                    ));
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
            if (mb_strpos($path, '{digital_object_id}') !== false
                || mb_strpos($path, '{digital_object_identifier}') !== false
                || mb_strpos($path, '{digital_object_identifier_short}') !== false
            ) {
                $route .= '-digital-object';
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
        if ($hasDigitalObject) {
            $resourcesParams['digital_object'] = [
                'check' => $checkPathDigitalObject,
                'controller' => 'digital-object',
                'name' => 'digital_objects',
            ];
        }

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
                    $spec = $baseRoutes[$sitePart]['base_spec'] . strtr($resourcePath, $specs);
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
                        'regex' => $baseRoutes[$sitePart]['base_regex'] . strtr($resourcePath, $regexes),
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
            return strtr(preg_quote($v), ['\\-' => '-']);
        }, $list);

        // To avoid a bug with identifiers that contain a "/", that is not
        // escaped with preg_quote().
        return strtr(implode('|', $listRegex), ['/' => '\/']);
    }
}
