<?php declare(strict_types=1);

namespace CleanUrlTest;

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;

/**
 * Shared test helpers for CleanUrl module tests.
 *
 * This trait is designed to work with CommonTest\AbstractHttpControllerTestCase.
 * It uses methods provided by that class: api(), getApplicationServiceLocator(),
 * loginAsAdmin(), etc.
 */
trait CleanUrlTestTrait
{
    /**
     * @var array IDs of resources created during tests (for cleanup).
     */
    protected array $createdResources = [];

    /**
     * @var array Original settings to restore after test.
     */
    protected array $originalSettings = [];

    /**
     * Get the service locator (alias for compatibility).
     */
    protected function getServiceLocator()
    {
        return $this->getApplicationServiceLocator();
    }

    /**
     * Ensure admin is logged in for API operations.
     *
     * This is needed because api() calls in setUp() happen before dispatch(),
     * which is when AbstractHttpControllerTestCase normally logs in.
     */
    protected function ensureLoggedIn(): void
    {
        $services = $this->getServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            return;
        }

        $em = $services->get('Omeka\EntityManager');
        $adminUser = $em->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        if ($adminUser) {
            $auth->getStorage()->write($adminUser);
        }
    }

    /**
     * Get a setting value.
     */
    protected function getSetting(string $name)
    {
        return $this->getServiceLocator()->get('Omeka\Settings')->get($name);
    }

    /**
     * Set a setting value, saving the original for restoration.
     */
    protected function setSetting(string $name, $value): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        if (!array_key_exists($name, $this->originalSettings)) {
            $this->originalSettings[$name] = $settings->get($name);
        }
        $settings->set($name, $value);
    }

    /**
     * Set multiple settings at once.
     */
    protected function setSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            $this->setSetting($name, $value);
        }
    }

    /**
     * Restore original settings.
     */
    protected function restoreSettings(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        foreach ($this->originalSettings as $name => $value) {
            $settings->set($name, $value);
        }
        $this->originalSettings = [];
    }

    /**
     * Create a test site.
     */
    protected function createSite(string $slug, string $title = null): SiteRepresentation
    {
        $this->ensureLoggedIn();
        $title = $title ?? ucfirst($slug);
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => 1,
        ]);
        $site = $response->getContent();
        $this->createdResources[] = ['type' => 'sites', 'id' => $site->id()];
        return $site;
    }

    /**
     * Create a test item set.
     *
     * @param string $identifier The dcterms:identifier value
     * @param string $title Optional title
     * @param array $additionalData Additional data to merge
     */
    protected function createItemSet(string $identifier, string $title = null, array $additionalData = []): ItemSetRepresentation
    {
        $this->ensureLoggedIn();
        $title = $title ?? "Item Set: $identifier";
        $data = array_merge([
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => 10,
                    '@value' => $identifier,
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
        ], $additionalData);

        $response = $this->api()->create('item_sets', $data);
        $itemSet = $response->getContent();
        $this->createdResources[] = ['type' => 'item_sets', 'id' => $itemSet->id()];
        return $itemSet;
    }

    /**
     * Create a test item.
     *
     * @param string $identifier The dcterms:identifier value
     * @param string $title Optional title
     * @param array $itemSetIds Optional item set IDs to attach
     * @param array $additionalData Additional data to merge
     */
    protected function createItem(string $identifier, string $title = null, array $itemSetIds = [], array $additionalData = []): ItemRepresentation
    {
        $this->ensureLoggedIn();
        $title = $title ?? "Item: $identifier";
        $data = array_merge([
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => 10,
                    '@value' => $identifier,
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => $title,
                ],
            ],
            'o:item_set' => array_map(fn($id) => ['o:id' => $id], $itemSetIds),
        ], $additionalData);

        $response = $this->api()->create('items', $data);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];
        return $item;
    }

    /**
     * Create a test item with ARK identifier.
     *
     * @param string $naan The Name Assigning Authority Number (e.g., "12345")
     * @param string $name The name portion of the ARK
     * @param string $title Optional title
     */
    protected function createItemWithArk(string $naan, string $name, string $title = null): ItemRepresentation
    {
        $arkIdentifier = "ark:/$naan/$name";
        return $this->createItem($arkIdentifier, $title ?? "ARK Item: $name");
    }

    /**
     * Create a test item set with ARK identifier.
     *
     * @param string $naan The Name Assigning Authority Number
     * @param string $name The name portion of the ARK
     * @param string $title Optional title
     */
    protected function createItemSetWithArk(string $naan, string $name, string $title = null): ItemSetRepresentation
    {
        $arkIdentifier = "ark:/$naan/$name";
        return $this->createItemSet($arkIdentifier, $title ?? "ARK Item Set: $name");
    }

    /**
     * Create a test item with media.
     *
     * @param string $identifier Item identifier
     * @param string $mediaIdentifier Media identifier
     * @param string $mediaUrl URL for media ingestion
     */
    protected function createItemWithMedia(
        string $identifier,
        string $mediaIdentifier,
        string $mediaUrl = 'http://example.com/test.jpg'
    ): ItemRepresentation {
        $this->ensureLoggedIn();
        $data = [
            'dcterms:identifier' => [
                [
                    'type' => 'literal',
                    'property_id' => 10,
                    '@value' => $identifier,
                ],
            ],
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => 1,
                    '@value' => "Item: $identifier",
                ],
            ],
            'o:media' => [
                [
                    'o:ingester' => 'url',
                    'ingest_url' => $mediaUrl,
                    'dcterms:identifier' => [
                        [
                            'type' => 'literal',
                            'property_id' => 10,
                            '@value' => $mediaIdentifier,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->api()->create('items', $data);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];
        return $item;
    }

    /**
     * Get default CleanUrl settings for testing.
     */
    protected function getDefaultCleanUrlSettings(): array
    {
        return [
            'cleanurl_site_skip_main' => false,
            'cleanurl_site_slug' => 's/',
            'cleanurl_page_slug' => 'page/',
            'cleanurl_item_set' => [
                'default' => 'collection/{item_set_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'properties' => [10], // dcterms:identifier
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_item' => [
                'default' => 'document/{item_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'properties' => [10], // dcterms:identifier
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_media' => [
                'default' => 'document/{item_identifier}/{media_id}',
                'short' => '',
                'paths' => [],
                'pattern' => '',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_admin_use' => false,
            'cleanurl_admin_reserved' => [],
        ];
    }

    /**
     * Get CleanUrl settings configured for ARK identifiers.
     *
     * @param string $naan The Name Assigning Authority Number
     * @param bool $prefixPartOf Whether the prefix is part of the identifier
     */
    protected function getArkCleanUrlSettings(string $naan = '12345', bool $prefixPartOf = true): array
    {
        $prefix = "ark:/$naan/";
        return [
            'cleanurl_site_skip_main' => false,
            'cleanurl_site_slug' => 's/',
            'cleanurl_page_slug' => 'page/',
            'cleanurl_item_set' => [
                'default' => 'ark:/' . $naan . '/{item_set_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9/_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => $prefix,
                'prefix_part_of' => $prefixPartOf,
                'keep_slash' => true,
                'case_sensitive' => false,
            ],
            'cleanurl_item' => [
                'default' => 'ark:/' . $naan . '/{item_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9/_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => $prefix,
                'prefix_part_of' => $prefixPartOf,
                'keep_slash' => true,
                'case_sensitive' => false,
            ],
            'cleanurl_media' => [
                'default' => 'ark:/' . $naan . '/{item_identifier}/{media_id}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9/_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => $prefix,
                'prefix_part_of' => $prefixPartOf,
                'keep_slash' => true,
                'case_sensitive' => false,
            ],
            'cleanurl_admin_use' => false,
            'cleanurl_admin_reserved' => [],
        ];
    }

    /**
     * Get CleanUrl settings for item set / item / media hierarchy.
     */
    protected function getHierarchicalCleanUrlSettings(): array
    {
        return [
            'cleanurl_site_skip_main' => false,
            'cleanurl_site_slug' => 's/',
            'cleanurl_page_slug' => 'page/',
            'cleanurl_item_set' => [
                'default' => 'collection/{item_set_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_item' => [
                'default' => 'collection/{item_set_identifier}/{item_identifier}',
                'short' => 'document/{item_identifier}',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_media' => [
                'default' => 'collection/{item_set_identifier}/{item_identifier}/{media_identifier}',
                'short' => 'document/{item_identifier}/{media_identifier}',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'properties' => [10],
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],
            'cleanurl_admin_use' => false,
            'cleanurl_admin_reserved' => [],
        ];
    }

    /**
     * Get settings for case-sensitive identifiers.
     */
    protected function getCaseSensitiveCleanUrlSettings(): array
    {
        $settings = $this->getDefaultCleanUrlSettings();
        $settings['cleanurl_item_set']['case_sensitive'] = true;
        $settings['cleanurl_item']['case_sensitive'] = true;
        $settings['cleanurl_media']['case_sensitive'] = true;
        return $settings;
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete in reverse order (media before items, items before item sets).
        $this->createdResources = array_reverse($this->createdResources);
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];

        // Restore settings.
        $this->restoreSettings();
    }

    /**
     * Get the path to the fixtures directory.
     */
    protected function getFixturesPath(): string
    {
        return dirname(__DIR__) . '/fixtures';
    }

    /**
     * Get a fixture file content.
     */
    protected function getFixture(string $name): string
    {
        $path = $this->getFixturesPath() . '/' . $name;
        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: $path");
        }
        return file_get_contents($path);
    }

    /**
     * Build a clean URL path for testing.
     *
     * @param string $siteSlug Site slug
     * @param string $resourcePath Resource path (e.g., "document/item1")
     * @param string $sitePrefix Site prefix (default "s/")
     */
    protected function buildCleanUrl(string $siteSlug, string $resourcePath, string $sitePrefix = 's/'): string
    {
        return '/' . $sitePrefix . $siteSlug . '/' . ltrim($resourcePath, '/');
    }

    /**
     * Build an ARK URL path.
     *
     * @param string $siteSlug Site slug
     * @param string $naan NAAN
     * @param string $name ARK name
     */
    protected function buildArkUrl(string $siteSlug, string $naan, string $name): string
    {
        return "/s/$siteSlug/ark:/$naan/$name";
    }
}
