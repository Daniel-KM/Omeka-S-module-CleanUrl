<?php declare(strict_types=1);

namespace CleanUrl\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager as ApiManager;

/**
 * Dereference a resource from a stable uri built with its identifier.
 *
 * The uri "/id/{identifier}" depends neither on the type of the resource nor on
 * the way it is displayed, so it can be published as the identifier of the
 * resource itself: it redirects to the representation the client asks for, the
 * json-ld one for a machine, the html one for a browser.
 *
 * @see https://www.w3.org/TR/cooluris/
 */
class IdentifierController extends AbstractActionController
{
    /**
     * @var PhpRenderer
     */
    protected $viewRenderer;

    /**
     * @var ApiManager
     */
    protected $api;

    public function __construct(PhpRenderer $viewRenderer, ApiManager $api)
    {
        $this->viewRenderer = $viewRenderer;
        $this->api = $api;
    }

    public function indexAction()
    {
        if (!$this->settings()->get('cleanurl_identifier_route')) {
            return $this->notFoundAction();
        }

        $identifier = $this->params('identifier');
        if (!strlen((string) $identifier)) {
            return $this->notFoundAction();
        }

        $resource = $this->viewRenderer->getResourceFromIdentifier($identifier);
        if (!$resource) {
            return $this->notFoundAction();
        }

        $url = null;
        if (!$this->wantsDescription()) {
            $siteSlug = $this->defaultSiteSlug();
            $url = $siteSlug
                ? $resource->siteUrl($siteSlug, true)
                : null;
        }

        // A "303 See Other" states that the uri identifies the resource itself,
        // not the document that describes it, unlike a "302 Found".
        return $this->redirect()
            ->toUrl($url ?: $resource->apiUrl())
            ->setStatusCode(303);
    }

    /**
     * Check if the client asks for the description of the resource (json-ld)
     * instead of a page to display (html).
     */
    protected function wantsDescription(): bool
    {
        $accept = $this->getRequest()->getHeaders()->get('Accept');
        if (!$accept) {
            return false;
        }

        // The priorities are managed by the header itself, so the first known
        // type is the preferred one of the client.
        foreach ($accept->getPrioritized() as $type) {
            $raw = strtolower($type->getTypeString());
            if ($raw === 'text/html' || $raw === 'application/xhtml+xml') {
                return false;
            }
            if ($raw === 'application/ld+json' || $raw === 'application/json') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the slug of the site used to display the resource.
     */
    protected function defaultSiteSlug(): ?string
    {
        $defaultSiteId = (int) $this->settings()->get('default_site');
        if ($defaultSiteId) {
            try {
                return $this->api->read('sites', ['id' => $defaultSiteId])->getContent()->slug();
            } catch (\Exception $e) {
                // Continue with the first site below.
            }
        }

        $sites = $this->api->search('sites', ['limit' => 1, 'sort_by' => 'id'])->getContent();
        return $sites ? reset($sites)->slug() : null;
    }
}
