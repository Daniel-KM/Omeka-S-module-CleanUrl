<?php declare(strict_types=1);

namespace CleanUrlTest\Controller;

use CleanUrlTest\CleanUrlTestTrait;
use CommonTest\AbstractHttpControllerTestCase;

/**
 * Tests to access a resource with its identifier instead of its internal id.
 *
 * Two independant features, both disabled by default:
 * - the api can be read with an identifier ("/api/items/{identifier}");
 * - a dereferenceable uri identifies the resource ("/id/{identifier}").
 *
 * @see https://github.com/Daniel-KM/Omeka-S-module-CleanUrl/issues/7
 */
class IdentifierAccessTest extends AbstractHttpControllerTestCase
{
    use CleanUrlTestTrait;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation
     */
    protected $item;

    public function setUp(): void
    {
        parent::setUp();
        $this->setSettings($this->getDefaultCleanUrlSettings());
        // A site is required to redirect to the public page of a resource.
        $this->site = $this->createSite('identifier-access-site');
        $this->setSetting('default_site', $this->site->id());
        $this->item = $this->createItem('identifier-access-001', 'Item to dereference');
    }

    public function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $this->cleanupResources();
        $this->restoreSettings();
        parent::tearDown();
    }

    /**
     * Without the option, the api answers only to the internal id.
     */
    public function testApiIgnoresIdentifierByDefault(): void
    {
        $this->setSetting('cleanurl_api_identifier', false);

        $this->dispatch('/api/items/identifier-access-001');
        $this->assertResponseStatusCode(404);
    }

    /**
     * With the option, the api answers to the identifier.
     */
    public function testApiReadsWithIdentifier(): void
    {
        $this->setSetting('cleanurl_api_identifier', true);

        $this->dispatch('/api/items/identifier-access-001');
        $this->assertResponseStatusCode(200);

        $json = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame($this->item->id(), $json['o:id']);
    }

    /**
     * The internal id is always used, even when the option is set.
     */
    public function testApiKeepsIdWithIdentifierOption(): void
    {
        $this->setSetting('cleanurl_api_identifier', true);

        $this->dispatch('/api/items/' . $this->item->id());
        $this->assertResponseStatusCode(200);

        $json = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame($this->item->id(), $json['o:id']);
    }

    /**
     * An unknown identifier is a not found, not an error.
     */
    public function testApiWithUnknownIdentifier(): void
    {
        $this->setSetting('cleanurl_api_identifier', true);

        $this->dispatch('/api/items/this-identifier-does-not-exist');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Without the option, the dereferenceable uri is not available.
     */
    public function testIdentifierRouteDisabledByDefault(): void
    {
        $this->setSetting('cleanurl_identifier_route', false);

        $this->dispatch('/id/identifier-access-001');
        $this->assertResponseStatusCode(404);
    }

    /**
     * A browser is redirected to the page of the resource.
     */
    public function testIdentifierRouteRedirectsToPage(): void
    {
        $this->setSetting('cleanurl_identifier_route', true);

        // The dispatch resets the request, so the header is set in the server
        // environment, that is used to build it.
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->dispatch('/id/identifier-access-001');

        $this->assertResponseStatusCode(303);
        $location = $this->getResponse()->getHeaders()->get('Location')->getFieldValue();
        $this->assertStringNotContainsString('/api/', $location);
        // The clean route is not rebuilt during the test, so the url may be the
        // standard one of the site.
        $this->assertStringContainsString($this->site->slug(), $location);
    }

    /**
     * A machine is redirected to the json-ld description of the resource.
     */
    public function testIdentifierRouteRedirectsToApi(): void
    {
        $this->setSetting('cleanurl_identifier_route', true);

        $_SERVER['HTTP_ACCEPT'] = 'application/ld+json';
        $this->dispatch('/id/identifier-access-001');

        $this->assertResponseStatusCode(303);
        $location = $this->getResponse()->getHeaders()->get('Location')->getFieldValue();
        $this->assertStringContainsString('/api/items/' . $this->item->id(), $location);
    }

    /**
     * An unknown identifier is a not found.
     */
    public function testIdentifierRouteWithUnknownIdentifier(): void
    {
        $this->setSetting('cleanurl_identifier_route', true);

        $this->dispatch('/id/this-identifier-does-not-exist');
        $this->assertResponseStatusCode(404);
    }
}
