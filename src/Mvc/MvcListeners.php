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
        if ($matchedRouteName !== 'clean-url') {
            return;
        }

        $forward = new RouteMatch($routeMatch->getParam('forward', []));
        $forward->setMatchedRouteName($routeMatch->getParam('forward_route_name'));
        $event->setRouteMatch($forward);
    }
}
