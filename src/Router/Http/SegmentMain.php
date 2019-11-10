<?php
namespace CleanUrl\Router\Http;

use const CleanUrl\MAIN_SITE_SLUG;

use Zend\Router\Http\Segment;

/**
 * Segment route with a check for main site to remove "/s/site-slug".
 */
class SegmentMain extends Segment
{
    public function assemble(array $params = [], array $options = [])
    {
        return MAIN_SITE_SLUG && isset($params['site-slug']) && $params['site-slug'] === MAIN_SITE_SLUG
            ? ''
            : parent::assemble($params, $options);
    }
}
