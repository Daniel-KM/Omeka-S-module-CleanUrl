<?php declare(strict_types=1);

namespace CleanUrl;

/**
 * Shared resource name/class conversion methods used across CleanUrl helpers.
 */
trait ResourceNameTrait
{
    protected function convertNameToResourceClass(?string $resourceName): ?string
    {
        $resourceClasses = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'resources' => '',
            'resource' => '',
            'resource:item' => \Omeka\Entity\Item::class,
            'resource:itemset' => \Omeka\Entity\ItemSet::class,
            'resource:media' => \Omeka\Entity\Media::class,
            // Avoid a check and make the plugin more flexible.
            \Omeka\Api\Representation\ItemRepresentation::class => \Omeka\Entity\Item::class,
            \Omeka\Api\Representation\ItemSetRepresentation::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Api\Representation\MediaRepresentation::class => \Omeka\Entity\Media::class,
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
            \Omeka\Entity\Resource::class => '',
            'o:item' => \Omeka\Entity\Item::class,
            'o:item_set' => \Omeka\Entity\ItemSet::class,
            'o:media' => \Omeka\Entity\Media::class,
            // Other resource types.
            'item' => \Omeka\Entity\Item::class,
            'item_set' => \Omeka\Entity\ItemSet::class,
            'item-set' => \Omeka\Entity\ItemSet::class,
            'itemset' => \Omeka\Entity\ItemSet::class,
            'resource:item_set' => \Omeka\Entity\ItemSet::class,
            'resource:item-set' => \Omeka\Entity\ItemSet::class,
        ];
        return $resourceClasses[$resourceName] ?? null;
    }

    protected function convertResourceClassToResourceName(?string $resourceClass): string
    {
        $resourceNames = [
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\Media::class => 'media',
        ];
        return $resourceNames[$resourceClass] ?? 'resources';
    }

    /**
     * Normalize a controller name to its short form.
     *
     * @param string $name
     * @return string|null
     */
    protected function controllerName(string $name): ?string
    {
        static $controllers;
        if ($controllers === null) {
            $controllers = [
                'item-set' => 'item-set',
                'item' => 'item',
                'media' => 'media',
                'item_sets' => 'item-set',
                'items' => 'item',
                'Omeka\Controller\Admin\ItemSet' => 'item-set',
                'Omeka\Controller\Admin\Item' => 'item',
                'Omeka\Controller\Admin\Media' => 'media',
                'Omeka\Controller\Site\ItemSet' => 'item-set',
                'Omeka\Controller\Site\Item' => 'item',
                'Omeka\Controller\Site\Media' => 'media',
                \Omeka\Entity\ItemSet::class => 'item-set',
                \Omeka\Entity\Item::class => 'item',
                \Omeka\Entity\Media::class => 'media',
            ];
        }
        return $controllers[$name] ?? null;
    }

    /**
     * Encode a string for use in a URL path.
     *
     * Avoids raw-url-encoding characters that don't need it.
     *
     * @see \Laminas\Router\Http\Segment::encode()
     *
     * @param string $value
     * @param bool $keepSlash
     * @return string
     */
    protected function encode($value, $keepSlash = false): string
    {
        static $urlencodeCorrectionMap;

        if ($urlencodeCorrectionMap === null) {
            $urlencodeCorrectionMap = [];
            $urlencodeCorrectionMap[false] = [
                '%21' => '!', // sub-delims
                '%24' => '$', // sub-delims
                '%26' => '&', // sub-delims
                '%27' => "'", // sub-delims
                '%28' => '(', // sub-delims
                '%29' => ')', // sub-delims
                '%2A' => '*', // sub-delims
                '%2B' => '+', // sub-delims
                '%2C' => ',', // sub-delims
                // '%2D' => '-', // unreserved - not touched by rawurlencode
                // '%2E' => '.', // unreserved - not touched by rawurlencode
                '%3A' => ':', // pchar
                '%3B' => ';', // sub-delims
                '%3D' => '=', // sub-delims
                '%40' => '@', // pchar
                // '%5F' => '_', // unreserved - not touched by rawurlencode
                // '%7E' => '~', // unreserved - not touched by rawurlencode
            ];
            $urlencodeCorrectionMap[true] = $urlencodeCorrectionMap[false];
            $urlencodeCorrectionMap[true]['%2F'] = '/';
        }

        return strtr(rawurlencode((string) $value), $urlencodeCorrectionMap[$keepSlash]);
    }
}
