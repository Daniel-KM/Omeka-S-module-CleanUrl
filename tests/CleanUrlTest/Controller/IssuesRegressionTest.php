<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

use CleanUrlTest\CleanUrlTestTrait;
use CommonTest\AbstractHttpControllerTestCase;

/**
 * Regression tests for reported GitHub/GitLab issues.
 *
 * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues
 * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues
 */
class IssuesRegressionTest extends AbstractHttpControllerTestCase
{
    use CleanUrlTestTrait;

    protected $site;
    protected $item;
    protected $itemSet;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * GH #8: ONLY_FULL_GROUP_BY SQL error.
     *
     * The identifier lookup query used GROUP BY on a quoted alias ("identifier")
     * which is treated as a string literal in MySQL without ANSI_QUOTES mode.
     * Fixed by using the actual expression in GROUP BY instead of the alias.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/8
     */
    public function testGh8GroupByWithMultipleIdentifiers(): void
    {
        $this->item = $this->createItem('gh8-item-alpha', 'GH8 Item Alpha');
        $item2 = $this->createItem('gh8-item-beta', 'GH8 Item Beta');

        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');
        $getResourcesFromIdentifiers = $viewHelperManager->get('getResourcesFromIdentifiers');

        // This triggers the GROUP BY query with multiple identifiers.
        $result = $getResourcesFromIdentifiers(['gh8-item-alpha', 'gh8-item-beta'], 'items');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gh8-item-alpha', $result);
        $this->assertArrayHasKey('gh8-item-beta', $result);
        $this->assertNotNull($result['gh8-item-alpha']);
        $this->assertNotNull($result['gh8-item-beta']);
    }

    /**
     * GH #8: Same test with case-insensitive identifiers (LOWER() in GROUP BY).
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/8
     */
    public function testGh8GroupByCaseInsensitive(): void
    {
        $this->item = $this->createItem('GH8-UPPER-ID', 'GH8 Upper Item');

        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');
        $getResourcesFromIdentifiers = $viewHelperManager->get('getResourcesFromIdentifiers');

        // Case-insensitive lookup should find the resource.
        $result = $getResourcesFromIdentifiers(['gh8-upper-id'], 'items');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gh8-upper-id', $result);
        $this->assertNotNull($result['gh8-upper-id']);
    }

    /**
     * GH #17 / GL #23: Reserved words should not be captured as identifiers.
     *
     * "search" and other module route segments were captured as resource
     * identifiers, causing "resource removed" errors.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/17
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/23
     *
     * @dataProvider reservedWordsProvider
     */
    public function testGh17ReservedWordsNotCapturedAsIdentifiers(string $reserved): void
    {
        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');
        $getResourcesFromIdentifiers = $viewHelperManager->get('getResourcesFromIdentifiers');

        // A reserved word used as identifier should not match any resource.
        $result = $getResourcesFromIdentifiers([$reserved], 'items');

        $this->assertIsArray($result);
        // The key exists but should be null (no resource found).
        $this->assertArrayHasKey($reserved, $result);
        $this->assertNull($result[$reserved]);
    }

    /**
     * Data provider: reserved words from modules that had conflicts.
     */
    public function reservedWordsProvider(): array
    {
        return [
            'search (GH #17)' => ['search'],
            'collecting (GL #16)' => ['collecting'],
            'search-manager' => ['search-manager'],
            'solr' => ['solr'],
            'feed' => ['feed'],
            'guest' => ['guest'],
            'iiif' => ['iiif'],
            'map' => ['map'],
        ];
    }

    /**
     * GH #19: PHP 8.3 deprecated dynamic property creation on routes.
     *
     * The route stack assigns $priority on every registered route, so each
     * custom route class must declare it publicly to avoid the PHP 8.3
     * "Creation of dynamic property" deprecation.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/19
     *
     * @dataProvider routeClassProvider
     */
    public function testGh19RoutePriorityPropertyDeclared(string $routeClass): void
    {
        $rc = new \ReflectionClass($routeClass);

        $this->assertTrue(
            $rc->hasProperty('priority'),
            "$routeClass must declare \$priority to avoid PHP 8.3 deprecation"
        );

        $prop = $rc->getProperty('priority');
        $this->assertTrue(
            $prop->isPublic(),
            '$priority must be public as required by RouteInterface'
        );
    }

    /**
     * Data provider: all custom route classes registered in the route stack.
     */
    public function routeClassProvider(): array
    {
        return [
            'CleanRoute' => [\CleanUrl\Router\Http\CleanRoute::class],
            'SegmentMain' => [\CleanUrl\Router\Http\SegmentMain::class],
            'RegexPage' => [\CleanUrl\Router\Http\RegexPage::class],
        ];
    }

    /**
     * GH #19: CleanRoute must declare every property assigned by its factory.
     *
     * The factory injects services (api, entityManager, helpers…) as object
     * properties; any undeclared one would trigger the PHP 8.3 dynamic property
     * deprecation. They must all be declared on the class.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/19
     */
    public function testGh19CleanRouteInjectedPropertiesDeclared(): void
    {
        $rc = new \ReflectionClass(\CleanUrl\Router\Http\CleanRoute::class);

        $injected = [
            'api', 'entityManager', 'getMediaFromPosition',
            'getResourceFromIdentifier', 'getResourceIdentifier',
            'routes', 'routeAliases',
        ];
        foreach ($injected as $name) {
            $this->assertTrue(
                $rc->hasProperty($name),
                "CleanRoute must declare \$$name to avoid PHP 8.3 deprecation"
            );
        }
    }

    /**
     * GH #17: Verify SLUGS_RESERVED contains common module route segments.
     *
     * Ensures the reserved list stays up-to-date with known module routes.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/17
     */
    public function testReservedSlugsContainModuleRoutes(): void
    {
        $reserved = '|' . \CleanUrl\SLUGS_CORE . \CleanUrl\SLUGS_RESERVED . '|';

        $expectedReserved = [
            // Core routes
            'admin', 'api', 'login', 'logout', 'item', 'media', 'page',
            // Common module routes (GH #17, GL #23, GL #16)
            'search', 'collecting', 'guest', 'iiif', 'map', 'feed',
            'solr', 'saml', 'download', 'export', 'import',
        ];

        foreach ($expectedReserved as $word) {
            $this->assertNotFalse(
                mb_stripos($reserved, '|' . $word . '|'),
                "Reserved list should contain '$word'"
            );
        }
    }

    /**
     * GH #8 / GL #14: Identifier lookup should work with ARK-style identifiers.
     *
     * ARK identifiers with slashes triggered GROUP BY issues in some MySQL
     * configurations.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/8
     */
    public function testGh8ArkIdentifierLookup(): void
    {
        $this->item = $this->createItem('ark:/12345/test001', 'ARK Test Item');

        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');
        $getResourceIdentifier = $viewHelperManager->get('getResourceIdentifier');

        $identifier = $getResourceIdentifier($this->item, false, false);
        $this->assertEquals('ark:/12345/test001', $identifier);
    }

    /**
     * Permalink: "force_canonical" must not collapse to the site root.
     *
     * When a resource has no clean url (e.g. an item without identifier, an
     * "ark"-less item) or on the main site (slug removed), the router assembles
     * an empty path. With "force_canonical" that empty path was
     * wrapped into the site root ("https://host/") and returned as the
     * resource url (so `$resource->siteUrl(null, true)` rendered the home
     * page). The helper now assembles relative urls and only prepends the
     * server url for a non-empty path, so an empty (failed) assembly is never
     * turned into the site root.
     *
     * @see \CleanUrl\View\Helper\CleanUrl::applyCanonical()
     */
    public function testForceCanonicalDoesNotCollapseToSiteRoot(): void
    {
        $services = $this->getServiceLocator();
        $url = $services->get('ViewHelperManager')->get('url');
        $url->setView($services->get('ViewRenderer'));

        $applyCanonical = new \ReflectionMethod($url, 'applyCanonical');
        $applyCanonical->setAccessible(true);

        // A relative url is returned unchanged when no canonical url is asked.
        $this->assertSame(
            '/s/test/document/x',
            $applyCanonical->invoke($url, '/s/test/document/x', false)
        );

        // An empty path (failed assembly) is never turned into the site root.
        $this->assertSame('', $applyCanonical->invoke($url, '', true));

        // A non-empty path is made absolute via the server url.
        $absolute = $applyCanonical->invoke($url, '/s/test/document/x', true);
        $this->assertStringStartsWith('http', $absolute);
        $this->assertStringEndsWith('/s/test/document/x', $absolute);
    }

    /**
     * Permalink: a canonical resource url equals its relative url made
     * absolute, never the bare site root.
     *
     * Integration counterpart of testForceCanonicalDoesNotCollapseToSiteRoot:
     * the canonical url assembled by the helper for a real resource must be the
     * relative url prepended with the server url.
     */
    public function testForceCanonicalMatchesRelativeUrl(): void
    {
        $this->site = $this->createSite('canonical-site');
        $this->item = $this->createItem('canonical-item', 'Canonical Item');

        $this->dispatch('/s/canonical-site/item/' . $this->item->id());

        $services = $this->getApplicationServiceLocator();
        $viewHelpers = $services->get('ViewHelperManager');
        $url = $viewHelpers->get('url');
        $serverUrl = $viewHelpers->get('serverUrl');

        $params = [
            'site-slug' => 'canonical-site',
            'controller' => 'item',
            'id' => $this->item->id(),
        ];
        $relative = $url('site/resource-id', $params, ['force_canonical' => false]);
        $canonical = $url('site/resource-id', $params, ['force_canonical' => true]);

        $this->assertNotSame('', $relative);
        $this->assertNotSame('/', $relative);
        $this->assertSame($serverUrl($relative), $canonical);
    }

    /**
     * GL #24: $site->url() must not return an empty string with CleanUrl.
     *
     * The "site" route is assembled through the overridden SegmentMain route.
     * It returned an empty string for the main site (slug removed), so
     * $site->url() rendered nothing in themes. Fixed by returning "/" when the
     * route is assembled standalone (commit 9b3554f). This test guards the
     * non-main case (SLUG_MAIN_SITE is fixed to false in the test bootstrap, so
     * the main-site branch cannot be exercised here).
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/24
     */
    public function testGl24SiteUrlNotEmptyWithCleanUrl(): void
    {
        $this->site = $this->createSite('gl24-site');

        $siteUrl = $this->site->siteUrl('gl24-site');

        $this->assertNotSame('', $siteUrl);
        $this->assertStringEndsWith('/s/gl24-site', $siteUrl);
    }

    /**
     * GL #24: SegmentMain assembles a non-empty path standalone.
     *
     * Unit check of the route override: a standalone assembly (no child) must
     * never yield an empty string. With SLUG_MAIN_SITE disabled in tests, the
     * route delegates to the parent Segment, which yields "/s/{slug}".
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/24
     */
    public function testGl24SegmentMainStandaloneNotEmpty(): void
    {
        $services = $this->getServiceLocator();
        $router = $services->get('HttpRouter');

        $path = $router->assemble(
            ['site-slug' => 'gl24-segment'],
            ['name' => 'site']
        );

        $this->assertNotSame('', $path);
        $this->assertStringEndsWith('/s/gl24-segment', $path);
    }

    /**
     * GH #20/#21 + GL #26: "top"->"site" normalization is bound to the main
     * site.
     *
     * On the main site, pages are served via "top/*" (the "/s/site-slug/"
     * prefix is removed) while Laminas Navigation builds them with "site/*", so
     * the listener normalizes "top" back to "site" to keep
     * isActive()/findActive() working (breadcrumbs GH #20, table of contents GH
     * #21). But it must only happen with a main site (GH #20/#21); without one,
     * normalizing the top page (sites list when no default site is set) into a
     * site context with no site breaks site settings ("Cannot manage settings
     * when no target ID is set", GL #26). Tested as a pure function for both
     * cases, independently of SLUG_MAIN_SITE (which is fixed in the bootstrap).
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/20
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/21
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/26
     *
     * @dataProvider topRouteProvider
     */
    public function testGl26TopRouteNormalizationBoundToMainSite(
        string $routeName,
        ?string $withMainSite
    ): void {
        $listener = new \CleanUrl\Mvc\MvcListeners();

        // With a main site: "top"/"top/*" become "site"/"site/*" (GH #20/#21).
        $this->assertSame(
            $withMainSite,
            $listener->normalizeTopRouteName($routeName, true)
        );
        // Without a main site: never normalized (GL #26).
        $this->assertNull($listener->normalizeTopRouteName($routeName, false));
    }

    /**
     * Data provider: route name and its "site" equivalent when a main site
     * exists (null when it is not a "top" route).
     */
    public function topRouteProvider(): array
    {
        return [
            'top => site' => ['top', 'site'],
            'top/page => site/page' => ['top/page', 'site/page'],
            'top/resource-id => site/resource-id' => ['top/resource-id', 'site/resource-id'],
            'site/page (non-top) => null' => ['site/page', null],
        ];
    }

    /**
     * GL #25 / MR !9: the page controller override must use a factory.
     *
     * On Omeka S 4.1.x the site page controller requires the current theme as a
     * constructor argument, so the historical invokable override broke
     * installation. CleanUrl overrides "Omeka\Controller\Site\Page" through a
     * factory building its own PageController with the current theme.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/merge_requests/9
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/25
     */
    public function testGl25PageControllerOverriddenViaFactory(): void
    {
        $controllerManager = $this->getServiceLocator()->get('ControllerManager');

        $controller = $controllerManager->get('Omeka\Controller\Site\Page');

        $this->assertInstanceOf(
            \CleanUrl\Controller\Site\PageController::class,
            $controller
        );
    }

    /**
     * GL #29: dynamic route data is cached in a file, not read from the
     * database during getConfig().
     *
     * The routes of the main site depend on SLUG_MAIN_SITE, defined in
     * getConfig() from the dynamic route data. To avoid a fragile database read
     * at that bootstrap stage (a failed read disabled the main-site routes,
     * causing "error-router-no-match"), the data is computed by
     * cacheCleanData() with the service manager and cached in a file;
     * readRouteData() only reads that file. A missing cache yields an empty
     * array (the default routing applies until the cache is rebuilt), never a
     * database access.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/29
     */
    public function testGl29RouteDataReadFromFileCacheNotDatabase(): void
    {
        $module = new \CleanUrl\Module();
        $read = new \ReflectionMethod($module, 'readRouteData');
        $read->setAccessible(true);
        $write = new \ReflectionMethod($module, 'writeRouteDataCache');
        $write->setAccessible(true);
        $getPath = new \ReflectionMethod($module, 'getRouteDataCachePath');
        $getPath->setAccessible(true);
        $path = $getPath->invoke($module);

        $backup = is_readable($path) ? file_get_contents($path) : null;
        try {
            $data = [
                'main_site' => 'my-main-site',
                'site' => 's/',
                'page' => 'page/',
                'sites' => 'foo|bar',
            ];
            $write->invoke($module, $data);
            $this->assertSame($data, $read->invoke($module));

            // A missing cache yields an empty array, not a database access.
            @unlink($path);
            $this->assertSame([], $read->invoke($module));
        } finally {
            if ($backup !== null) {
                file_put_contents($path, $backup);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * GL #29: the route data cache is rebuilt when the main site changes.
     *
     * The main site is the general Omeka setting "default_site", which can
     * change at any time outside the module config. Omeka 4.2 triggers
     * "setting.update"/"setting.insert" on the settings service; the module
     * listens to it and rebuilds the cache, so the main-site routes stay
     * correct.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/29
     */
    public function testGl29CacheRebuiltWhenMainSiteSettingChanges(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        if (!method_exists($settings, 'getEventManager')) {
            $this->markTestSkipped('Settings events require Omeka S 4.2+.');
        }

        $module = new \CleanUrl\Module();
        $getPath = new \ReflectionMethod($module, 'getRouteDataCachePath');
        $getPath->setAccessible(true);
        $path = $getPath->invoke($module);

        $backup = is_readable($path) ? file_get_contents($path) : null;
        try {
            @unlink($path);
            $this->assertFileDoesNotExist($path);

            // Changing a routing-related general setting must rebuild the
            // cache.
            $this->setSetting('default_site', (string) ($this->getSetting('default_site') ?: '1'));

            $this->assertFileExists($path);
        } finally {
            if ($backup !== null) {
                file_put_contents($path, $backup);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * GL #16: module routes work on the main site (conflict with Collecting).
     *
     * When "s/site-slug/" is skipped for the main site, CleanUrl copies the
     * child routes of "site" to "top". A module route without an explicit
     * controller (Collecting) inherited the "top" home controller "Page" and
     * resolved to a non-existent "...\Page" controller (page not found on
     * submit). The copy must instead carry the site default controller
     * ("Index"), while routes with an explicit controller keep it.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/16
     */
    public function testGl16ModuleRoutesCopiedToTopKeepSiteController(): void
    {
        $module = new \CleanUrl\Module();
        $copy = new \ReflectionMethod($module, 'copyChildRoutesToTop');
        $copy->setAccessible(true);

        $config = [
            'router' => [
                'routes' => [
                    'site' => [
                        'options' => ['defaults' => ['controller' => 'Index']],
                        'child_routes' => [
                            // No explicit controller (relies on the parent's).
                            'collecting' => [
                                'options' => [
                                    'route' => '/collecting/:form-id/:action',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Collecting\Controller\Site',
                                    ],
                                ],
                            ],
                            // Explicit controller, must be preserved.
                            'comment-id' => [
                                'options' => [
                                    'route' => '/comment/:id',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Comment\Controller\Site',
                                        'controller' => 'Comment\Controller\Site\CommentController',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'top' => ['child_routes' => []],
                ],
            ],
        ];

        $top = $copy->invoke($module, $config)['router']['routes']['top']['child_routes'];

        // Collecting inherits the site default controller, not "Page".
        $this->assertSame('Index', $top['collecting']['options']['defaults']['controller']);
        // Leading "/" is stripped for the top route.
        $this->assertSame('collecting/:form-id/:action', $top['collecting']['options']['route']);
        // A route with an explicit controller keeps it untouched.
        $this->assertSame(
            'Comment\Controller\Site\CommentController',
            $top['comment-id']['options']['defaults']['controller']
        );
    }

    /**
     * GL #22: a canonical link to the clean url is added only when the current
     * url is not already the clean one.
     *
     * On a resource/page public view, CleanUrl adds
     * <link rel="canonical" href="{clean url}"> so search engines do not index
     * the original and clean urls as duplicate pages. To avoid a useless
     * self-referencing link, nothing is added when the current url already is
     * the clean one (path compared, ignoring query string and trailing slash),
     * nor when the resource has no clean url.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/22
     */
    public function testGl22CanonicalUrlOnlyWhenNotCleanUrl(): void
    {
        $module = new \CleanUrl\Module();

        // Current url is the standard one, clean url differs: use the clean
        // url.
        $this->assertSame(
            'https://ex.org/document/id',
            $module->canonicalUrl('https://ex.org/document/id', 'https://ex.org/item/123')
        );
        // Current url is already the clean one: no self-referencing canonical.
        $this->assertNull(
            $module->canonicalUrl('https://ex.org/document/id', 'https://ex.org/document/id')
        );
        // Trailing slash and query string on the current url are ignored.
        $this->assertNull(
            $module->canonicalUrl('https://ex.org/document/id', 'https://ex.org/document/id/?page=2')
        );
        // No clean url (resource without identifier): nothing added.
        $this->assertNull($module->canonicalUrl(null, 'https://ex.org/item/123'));
        $this->assertNull($module->canonicalUrl('', 'https://ex.org/item/123'));
    }

    /**
     * GL #30: match() must guard against reentrancy to avoid an infinite loop.
     *
     * To resolve an identifier into a resource id, match() runs an api search.
     * Its listeners (for example AdvancedSearch) may call
     * Status::isSiteRequest(), which re-triggers the whole routing while the
     * first match() has not returned yet. As the route match is not memoized
     * until match() returns, this leads to an infinite recursion. A reentrant
     * call must therefore return null immediately.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-CleanUrl/-/issues/30
     */
    public function testGl30MatchGuardsAgainstReentrancy(): void
    {
        $rc = new \ReflectionClass(\CleanUrl\Router\Http\CleanRoute::class);
        $route = $rc->newInstanceWithoutConstructor();

        $prop = $rc->getProperty('matching');
        $prop->setAccessible(true);
        $prop->setValue($route, true);

        $request = $this->createMock(\Laminas\Stdlib\RequestInterface::class);

        $this->assertNull(
            $route->match($request),
            'A reentrant match() must return null to avoid infinite recursion'
        );
    }
}
