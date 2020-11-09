<?php declare(strict_types=1);

namespace CleanUrl\Router\Http;

use Laminas\Router\Http\Regex;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\RequestInterface as Request;

/**
 * Regex route with a check for reserved words.
 */
class RegexPage extends Regex
{
    public function match(Request $request, $pathOffset = null)
    {
        if (!method_exists($request, 'getUri')) {
            return null;
        }

        $uri = $request->getUri();
        $path = $uri->getPath();

        $path = mb_substr($path, $pathOffset);

        $matches = [];
        preg_match('(^' . $this->regex . '$)', $path, $matches);

        if (empty($matches['page_slug'])) {
            return null;
        }

        $matchedLength = mb_strlen($matches[0]);
        $matches = [
            'page-slug' => rawurldecode($matches['page_slug']),
        ];

        return new RouteMatch(array_merge($this->defaults, $matches), $matchedLength);
    }
}
