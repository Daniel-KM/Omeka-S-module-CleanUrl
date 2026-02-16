<?php declare(strict_types=1);

namespace CleanUrl\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;

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

        /** @see https://forum.omeka.org/t/active-page-not-set-when-clean-url-is-active/28149 */
        // Normalize "top" and "top/*" routes to "site" and "site/*" so that
        // Laminas Navigation isActive() works on the main site.
        // Navigation pages are built with route "site/page", but when the main
        // site prefix "/s/site-slug/" is removed, pages are matched by "top/page"
        // instead, causing isActive() to always return false.
        if ($matchedRouteName === 'top' || strpos($matchedRouteName, 'top/') === 0) {
            $normalizedName = $matchedRouteName === 'top'
                ? 'site'
                : 'site/' . substr($matchedRouteName, 4);
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
}
