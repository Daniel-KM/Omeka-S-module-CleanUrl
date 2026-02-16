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
     * GH #19: PHP 8.3 deprecated dynamic property creation on CleanRoute.
     *
     * The $priority property must be explicitly declared in CleanRoute.
     *
     * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/19
     */
    public function testGh19CleanRoutePriorityPropertyDeclared(): void
    {
        $rc = new \ReflectionClass(\CleanUrl\Router\Http\CleanRoute::class);

        $this->assertTrue(
            $rc->hasProperty('priority'),
            'CleanRoute must declare $priority to avoid PHP 8.3 deprecation'
        );

        $prop = $rc->getProperty('priority');
        $this->assertTrue(
            $prop->isPublic(),
            '$priority must be public as required by RouteInterface'
        );
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
     * ARK identifiers with slashes triggered GROUP BY issues in some
     * MySQL configurations.
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
}
