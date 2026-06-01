<?php declare(strict_types=1);

namespace CleanUrl\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;

use const CleanUrl\SLUG_MAIN_SITE;

class MvcListeners extends AbstractListenerAggregate
{
    /**
     * @var MvcEvent
     */
    protected $event;

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectTo']
        );
    }

    public function redirectTo(MvcEvent $event): void
    {
        $routeMatch = $event->getRouteMatch();
        $matchedRouteName = $routeMatch->getMatchedRouteName();

        $normalizedName = $this->normalizeTopRouteName($matchedRouteName, (bool) SLUG_MAIN_SITE);
        if ($normalizedName !== null) {
            // Don't use setMatchedRouteName(): Laminas\Router\Http\RouteMatch
            // overrides it to prepend $name to the existing value instead of
            // replacing it, which produces "site/xxx/top/xxx" instead of
            // "site/xxx".
            $ref = new \ReflectionProperty(\Laminas\Router\RouteMatch::class, 'matchedRouteName');
            $ref->setAccessible(true);
            $ref->setValue($routeMatch, $normalizedName);
            return;
        }

        if ($matchedRouteName !== 'clean-url') {
            return;
        }

        $forward = new RouteMatch($routeMatch->getParam('forward', []));
        $forward->setMatchedRouteName($routeMatch->getParam('forward_route_name'));
        $event->setRouteMatch($forward);

        // Some modules call isSiteRequest() during bootstrap, before routing,
        // which caches incorrect values. Reset the cached status flags so they
        // are recalculated from the forwarded route match.
        $status = $event->getApplication()->getServiceManager()->get('Omeka\Status');
        $ref = new \ReflectionClass($status);
        foreach (['isSiteRequest', 'isAdminRequest', 'isApiRequest'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($status, null);
            }
        }
    }

    /**
     * Normalize a "top"/"top/*" route name to "site"/"site/*", or null when no
     * normalization applies.
     *
     * @see https://forum.omeka.org/t/active-page-not-set-when-clean-url-is-active/28149
     *
     * On the main site, pages are served via "top/*" (the "/s/site-slug/"
     * prefix is removed), but Laminas Navigation builds them with "site/*", so
     * isActive()/findActive() would fail (empty breadcrumbs GH #20, empty table
     * of contents GH #21). Normalizing "top" back to "site" fixes it.
     *
     * It must only happen when a main site exists: otherwise the top page
     * (sites list, when no default site is set) would be turned into a site
     * context with no site, breaking site settings ("Cannot manage settings
     * when no target ID is set", GL #26).
     */
    public function normalizeTopRouteName(string $matchedRouteName, bool $hasMainSite): ?string
    {
        if (!$hasMainSite) {
            return null;
        }
        if ($matchedRouteName === 'top') {
            return 'site';
        }
        if (strpos($matchedRouteName, 'top/') === 0) {
            return 'site/' . substr($matchedRouteName, 4);
        }
        return null;
    }
}
