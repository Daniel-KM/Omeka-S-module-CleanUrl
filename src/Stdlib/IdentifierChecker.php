<?php declare(strict_types=1);

namespace CleanUrl\Stdlib;

use CleanUrl\ResourceNameTrait;
use Doctrine\DBAL\Connection;

use const CleanUrl\SLUGS_CORE;
use const CleanUrl\SLUGS_RESERVED;

/**
 * Check that identifiers can be used to build and to match a clean url.
 *
 * The router applies the pattern on the encoded path, not on the raw value, so
 * an identifier is usable only when its encoded form matches the pattern. For
 * example a dot is not encoded by rawurlencode(), so it appears as is in the
 * url and it must be part of the pattern. The checks below replicate the router
 * and the identifier helper in order to avoid any divergence.
 *
 * @see \CleanUrl\Router\Http\CleanRoute::matchClean()
 * @see \CleanUrl\View\Helper\GetResourceIdentifier
 */
class IdentifierChecker
{
    use ResourceNameTrait;

    /**
     * Number of values fetched by query.
     *
     * @var int
     */
    const CHUNK_SIZE = 1000;

    /**
     * Number of invalid identifiers kept as examples.
     *
     * @var int
     */
    const EXAMPLE_SIZE = 5;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Check that an identifier can be matched back by the router.
     */
    public function isValidIdentifier(string $identifier, array $options, bool $short = false): bool
    {
        if (!mb_strlen($identifier)) {
            return false;
        }

        $pattern = $this->pattern($options, $short);
        if (!mb_strlen($pattern)) {
            return false;
        }

        // Use the same delimiters than the router, so a pattern that contains a
        // delimiter behaves the same way here and there.
        $encoded = $this->encode($identifier, !empty($options['keep_slash']));
        if (!@preg_match('(^' . $pattern . '$)', $encoded)) {
            return false;
        }

        // The router checks reserved words on the decoded value, without case.
        return mb_stripos('|' . SLUGS_CORE . SLUGS_RESERVED . '|', '|' . $identifier . '|') === false;
    }

    /**
     * List the characters whose removal would make the identifier match.
     *
     * This points the characters that are missing in the pattern, for example
     * the dot in "test.output" with the default pattern.
     *
     * @return string[]
     */
    public function offendingCharacters(string $identifier, array $options, bool $short = false): array
    {
        $pattern = $this->pattern($options, $short);
        if (!mb_strlen($pattern)) {
            return [];
        }

        $encoded = $this->encode($identifier, !empty($options['keep_slash']));

        // No character is missing when the pattern already matches: either the
        // identifier is valid, or it is refused as a reserved word.
        if (@preg_match('(^' . $pattern . '$)', $encoded)) {
            return [];
        }

        $characters = preg_split('//u', $encoded, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $result = [];
        foreach (array_unique($characters) as $character) {
            $stripped = str_replace($character, '', $encoded);
            if (mb_strlen($stripped) && @preg_match('(^' . $pattern . '$)', $stripped)) {
                $result[] = $character;
            }
        }
        return $result;
    }

    /**
     * Count the identifiers of a resource type that have no clean url.
     *
     * Like the identifier helper, only the first literal value of the
     * configured property is used for each resource, so the other values are
     * not checked: they are not used to build the url.
     *
     * @return array With keys "total", "invalid", "examples" and "characters".
     */
    public function checkResourceType(string $resourceName, array $options, bool $short = false): array
    {
        $result = [
            'total' => 0,
            'invalid' => 0,
            'examples' => [],
            'characters' => [],
        ];

        $propertyId = (int) ($options['property'] ?? 0);
        $resourceClass = $this->convertNameToResourceClass($resourceName);
        if (!$propertyId || !$resourceClass) {
            return $result;
        }

        $prefix = (string) ($options['prefix'] ?? '');
        $lengthPrefix = mb_strlen($prefix);

        $bind = [
            'property_id' => $propertyId,
            'resource_type' => $resourceClass,
        ];

        // An identifier is always literal: it identifies a resource inside the
        // base. It can't be an external uri or a linked resource.
        $wherePrefix = '';
        if ($lengthPrefix) {
            $wherePrefix = ' AND value.value LIKE :prefix';
            $bind['prefix'] = addcslashes($prefix, '%_') . '%';
        }

        $sql = <<<SQL
            SELECT value.value
            FROM value
            INNER JOIN (
                SELECT MIN(id) AS id
                FROM value
                WHERE property_id = :property_id
                    AND type = 'literal'$wherePrefix
                GROUP BY resource_id
            ) AS first ON first.id = value.id
            INNER JOIN resource ON resource.id = value.resource_id
            WHERE resource.resource_type = :resource_type
            ORDER BY value.id
            SQL;

        $offset = 0;
        do {
            $values = $this->connection
                ->executeQuery($sql . ' LIMIT ' . self::CHUNK_SIZE . ' OFFSET ' . $offset, $bind)
                ->fetchFirstColumn();
            foreach ($values as $value) {
                // The short identifier is the value without the prefix.
                $identifier = $short && $lengthPrefix
                    ? trim(mb_substr((string) $value, $lengthPrefix))
                    : (string) $value;
                ++$result['total'];
                if ($this->isValidIdentifier($identifier, $options, $short)) {
                    continue;
                }
                ++$result['invalid'];
                if (count($result['examples']) < self::EXAMPLE_SIZE) {
                    $result['examples'][] = $identifier;
                }
                foreach ($this->offendingCharacters($identifier, $options, $short) as $character) {
                    $result['characters'][$character] = $character;
                }
            }
            $offset += self::CHUNK_SIZE;
        } while (count($values) === self::CHUNK_SIZE);

        $result['characters'] = array_values($result['characters']);
        return $result;
    }

    /**
     * Get the pattern used for the full or the short identifier.
     */
    protected function pattern(array $options, bool $short): string
    {
        return $short
            ? (string) (($options['pattern_short'] ?? '') ?: ($options['pattern'] ?? ''))
            : (string) ($options['pattern'] ?? '');
    }
}
