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

        $characters = array_unique(preg_split('//u', $encoded, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        // A character is refused when it doesn't match after a character that
        // the pattern accepts. Checking each character on its own would flag
        // "_" or "-", that are usually refused as first character only, and
        // removing them one by one would find nothing as soon as an identifier
        // has two refused characters, like ":" and "%" in an encoded ark.
        $accepted = $this->acceptedCharacter($pattern);
        if ($accepted === null) {
            return [];
        }

        $result = [];
        foreach ($characters as $character) {
            if (!@preg_match('(^' . $pattern . '$)', $accepted . $character)) {
                $result[] = $character;
            }
        }
        return $result;
    }

    /**
     * Get the resource types that may have an identifier.
     */
    public function resourceTypes(): array
    {
        $resourceTypes = [
            'item_set' => 'item_sets',
            'item' => 'items',
            'media' => 'media',
        ];
        if (class_exists(\DigitalObject\Entity\DigitalObject::class)) {
            $resourceTypes['digital_object'] = 'digital_objects';
        }
        return $resourceTypes;
    }

    /**
     * Get the identifier modes used by the paths of a resource type.
     *
     * A path built with an id, like the default one for medias
     * ("document/{item_identifier}/{media_id}"), needs no identifier.
     *
     * @return bool[] False for the full identifier, true for the short one.
     */
    public function identifierModes(array $options, string $resourceType): array
    {
        $paths = $options['paths'] ?? [];
        $paths[] = $options['default'] ?? '';
        $paths[] = $options['short'] ?? '';

        $modes = [];
        foreach (array_filter($paths) as $path) {
            if (mb_strpos($path, '{' . $resourceType . '_identifier}') !== false) {
                $modes[0] = false;
            }
            if (mb_strpos($path, '{' . $resourceType . '_identifier_short}') !== false) {
                $modes[1] = true;
            }
        }
        return array_values($modes);
    }

    /**
     * Count the identifiers of a resource type that have no clean url.
     *
     * Like the identifier helper, only the first literal value of the
     * configured property is used for each resource, so the other values are
     * not checked: they are not used to build the url.
     *
     * The callback, when set, is called for each invalid identifier with the
     * resource id, the identifier and the characters to add to the pattern.
     *
     * @return array With keys "total", "invalid", "examples" and "characters".
     */
    public function checkResourceType(
        string $resourceName,
        array $options,
        bool $short = false,
        ?callable $onInvalid = null
    ): array {
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
            SELECT value.resource_id, value.value
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
                ->fetchAllKeyValue();
            foreach ($values as $resourceId => $value) {
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
                $characters = $this->offendingCharacters($identifier, $options, $short);
                foreach ($characters as $character) {
                    $result['characters'][$character] = $character;
                }
                if ($onInvalid) {
                    $onInvalid((int) $resourceId, $identifier, $characters);
                }
            }
            $offset += self::CHUNK_SIZE;
        } while (count($values) === self::CHUNK_SIZE);

        $result['characters'] = array_values($result['characters']);
        return $result;
    }

    /**
     * Get a single character accepted by the pattern, used to check the other
     * ones in a continuation position.
     */
    protected function acceptedCharacter(string $pattern): ?string
    {
        foreach (['a', 'A', '0', 'z', '9'] as $character) {
            if (@preg_match('(^' . $pattern . '$)', $character)) {
                return $character;
            }
        }
        return null;
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
