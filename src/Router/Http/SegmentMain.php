<?php declare(strict_types=1);

namespace CleanUrl\Router\Http;

use const CleanUrl\SLUG_MAIN_SITE;

use Laminas\Router\Http\Segment;

/**
 * Segment route with a check for main site to remove "/s/site-slug".
 */
class SegmentMain extends Segment
{
    public function assemble(array $params = [], array $options = [])
    {
        return SLUG_MAIN_SITE && isset($params['site-slug']) && $params['site-slug'] === SLUG_MAIN_SITE
            ? ''
            : parent::assemble($params, $options);
    }
}
