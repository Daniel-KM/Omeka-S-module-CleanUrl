<?php declare(strict_types=1);

namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;

use Laminas\Router\Http\Segment;

/**
 * Segment route with a check for main site to remove "/s/site-slug".
 */
class SegmentMain extends Segment
{
    /**
     * Priority used for route stacks.
     *
     * The property should be public.
     * @see \Laminas\Router\RouteInterface
     *
     * @var int
     */
    public $priority;

    public function assemble(array $params = [], array $options = [])
    {
        if (SLUG_MAIN_SITE && isset($params['site-slug']) && $params['site-slug'] === SLUG_MAIN_SITE) {
            // When assembling as part of a child route (e.g. "site/page"),
            // return empty so child path "/page/slug" is not prefixed.
            // When assembling standalone (e.g. "site"), return "/" so the
            // site URL is valid (not an empty string).
            return empty($options['has_child']) ? '/' : '';
        }
        return parent::assemble($params, $options);
    }
}
