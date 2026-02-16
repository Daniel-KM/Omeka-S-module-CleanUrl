<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

use CleanUrlTest\CleanUrlTestTrait;
use CommonTest\AbstractHttpControllerTestCase;

/**
 * Basic tests for CleanUrl module.
 *
 * These tests verify the module is properly installed and routes work.
 * They use the default configuration without trying to modify settings.
 */
class BasicCleanUrlTest extends AbstractHttpControllerTestCase
{
    use CleanUrlTestTrait;

    protected $site;
    protected $itemSet;
    protected $item;

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
     * Test that a site can be created.
     */
    public function testCreateSite(): void
    {
        $this->site = $this->createSite('test-site');

        $this->assertNotNull($this->site);
        $this->assertEquals('test-site', $this->site->slug());
    }

    /**
     * Test that an item can be created with an identifier.
     */
    public function testCreateItemWithIdentifier(): void
    {
        $this->item = $this->createItem('test-item-001');

        $this->assertNotNull($this->item);
        $identifier = $this->item->value('dcterms:identifier');
        $this->assertEquals('test-item-001', (string) $identifier);
    }

    /**
     * Test that an item set can be created with an identifier.
     */
    public function testCreateItemSetWithIdentifier(): void
    {
        $this->itemSet = $this->createItemSet('test-collection-001');

        $this->assertNotNull($this->itemSet);
        $identifier = $this->itemSet->value('dcterms:identifier');
        $this->assertEquals('test-collection-001', (string) $identifier);
    }

    /**
     * Test that the url helper is overridden by CleanUrl.
     */
    public function testUrlViewHelperOverridden(): void
    {
        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelperManager->has('url'));
        $urlHelper = $viewHelperManager->get('url');
        $this->assertInstanceOf(\CleanUrl\View\Helper\CleanUrl::class, $urlHelper);
    }

    /**
     * Test that GetResourceIdentifier helper exists.
     */
    public function testGetResourceIdentifierHelperExists(): void
    {
        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelperManager->has('getResourceIdentifier'));
    }

    /**
     * Test that GetResourceFromIdentifier helper exists.
     */
    public function testGetResourceFromIdentifierHelperExists(): void
    {
        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelperManager->has('getResourceFromIdentifier'));
    }

    /**
     * Test item with ARK-like identifier can be created.
     */
    public function testCreateItemWithArkIdentifier(): void
    {
        $arkIdentifier = 'ark:/12345/abc123';
        $this->item = $this->createItem($arkIdentifier, 'ARK Test Item');

        $this->assertNotNull($this->item);
        $identifier = $this->item->value('dcterms:identifier');
        $this->assertEquals($arkIdentifier, (string) $identifier);
    }

    /**
     * Test item set with ARK-like identifier can be created.
     */
    public function testCreateItemSetWithArkIdentifier(): void
    {
        $arkIdentifier = 'ark:/12345/collection001';
        $this->itemSet = $this->createItemSet($arkIdentifier, 'ARK Collection');

        $this->assertNotNull($this->itemSet);
        $identifier = $this->itemSet->value('dcterms:identifier');
        $this->assertEquals($arkIdentifier, (string) $identifier);
    }

    /**
     * Test GetResourceIdentifier returns identifier for item.
     */
    public function testGetResourceIdentifierForItem(): void
    {
        $this->item = $this->createItem('my-unique-item');

        $services = $this->getServiceLocator();
        $viewHelperManager = $services->get('ViewHelperManager');
        $getResourceIdentifier = $viewHelperManager->get('getResourceIdentifier');

        // The helper returns the identifier without encoding.
        $identifier = $getResourceIdentifier($this->item, false, false);

        $this->assertEquals('my-unique-item', $identifier);
    }

    /**
     * Test item in item set relation.
     */
    public function testItemInItemSet(): void
    {
        $this->itemSet = $this->createItemSet('parent-collection');
        $this->item = $this->createItem('child-item', 'Child Item', [$this->itemSet->id()]);

        $itemSets = $this->item->itemSets();
        $itemSetsArray = iterator_to_array($itemSets);
        $this->assertCount(1, $itemSetsArray);
        $this->assertEquals($this->itemSet->id(), reset($itemSetsArray)->id());
    }

    /**
     * Test settings can be read.
     */
    public function testReadCleanUrlSettings(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        // These settings should exist after module installation.
        $siteSlug = $settings->get('cleanurl_site_slug');
        $pageSlug = $settings->get('cleanurl_page_slug');

        // Default values.
        $this->assertEquals('s/', $siteSlug);
        $this->assertEquals('page/', $pageSlug);
    }
}
