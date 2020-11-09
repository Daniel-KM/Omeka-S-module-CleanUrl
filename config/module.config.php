<?php declare(strict_types=1);

namespace CleanUrl;

// The check of "slugs_site" may avoid an issue when empty, after install or
// during/after upgrade. When empty, there must be a slug site (default "s/").
if (mb_strlen(SLUGS_SITE) || mb_strlen(SLUG_SITE)) {
    $slugSite = SLUG_SITE;
    $regexSite = SLUGS_SITE;
} else {
    $slugSite = SLUG_SITE_DEFAULT;
    $regexSite = '[a-zA-Z0-9_-]+';
}

// Prepare to get the slug of a page, that can be anything except reserved strings.
$regexSitePage = SLUG_PAGE
    . '(?:'
    . ($slugSite ? rtrim($slugSite . '/') . '|' : '')
    . (SLUG_PAGE ? rtrim(SLUG_PAGE . '/') . '|' : '')
    . SLUGS_CORE
    // Common modules and reserved strings.
    . SLUGS_RESERVED
    // Capturing group for page-slug ("-" cannot be used here).
    . '|(?P<page_slug>[a-zA-Z0-9_-]+))';

return [
    'service_manager' => [
        'invokables' => [
            'CleanUrl\MvcListeners' => Mvc\MvcListeners::class,
        ],
    ],
    'listeners' => [
        'CleanUrl\MvcListeners',
    ],
    'view_manager' => [
        'controller_map' => [
            Controller\Site\PageController::class => 'omeka/site/page',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'getResourceFromIdentifier' => View\Helper\GetResourceFromIdentifier::class,
            'url' => View\Helper\CleanUrl::class,
            'Url' => View\Helper\CleanUrl::class,
        ],
        'factories' => [
            'getIdentifiersFromResources' => Service\ViewHelper\GetIdentifiersFromResourcesFactory::class,
            'getMediaFromPosition' => Service\ViewHelper\GetMediaFromPositionFactory::class,
            'getResourcesFromIdentifiers' => Service\ViewHelper\GetResourcesFromIdentifiersFactory::class,
            'getResourceTypeIdentifiers' => Service\ViewHelper\GetResourceTypeIdentifiersFactory::class,
            'getResourceIdentifier' => Service\ViewHelper\GetResourceIdentifierFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            // Override the page controller used for the root url.
            'Omeka\Controller\Site\Page' => Controller\Site\PageController::class,
        ],
    ],
    'router' => [
        'routes' => [
            // Routes for the main site when "s/site-slug/" is skipped.
            // Clean routes for resources depend on settings and are added during bootstrap.
            'top' => [
                // Override the top controller in order to use the site homepage.
                'options' => [
                    'defaults' => [
                        // TODO Remove __SITE__ to allow the main setting for default site or not.
                        '__NAMESPACE__' => 'Omeka\Controller\Site',
                        '__SITE__' => true,
                        'site-slug' => SLUG_MAIN_SITE,
                        'controller' => 'Page',
                        'action' => 'show',
                    ],
                ],
                'may_terminate' => true,
                // Same routes than "site", except initial "/" and routes, without starting "/".
                // Allows to access main site resources and pages.
                // TODO Find a way to avoid to copy all the site routes, in particular for modules. Add "|" to the regex of site slug?
                'child_routes' => SLUG_MAIN_SITE ? [
                    'resource' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => ':controller[/:action]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'resource-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => ':controller/:id[/:action]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                'action' => 'show',
                            ],
                        ],
                    ],
                    'item-set' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => 'item-set/:item-set-id',
                            'defaults' => [
                                'controller' => 'Item',
                                'action' => 'browse',
                            ],
                            'constraints' => [
                                'item-set-id' => '\d+',
                            ],
                        ],
                    ],
                    'page-browse' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => SLUG_PAGE ? rtrim(SLUG_PAGE, '/') : 'page',
                            'defaults' => [
                                'controller' => 'Page',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'page' => [
                        'type' => \CleanUrl\Router\Http\RegexPage::class,
                        'options' => [
                            'regex' => $regexSitePage,
                            'spec' => SLUG_PAGE . '%page-slug%',
                            'defaults' => [
                                'controller' => 'Page',
                                'action' => 'show',
                            ],
                        ],
                    ],
                ] : [],
            ],
            // Override the default config to remove the slug for main site.
            'site' => [
                'type' => \CleanUrl\Router\Http\SegmentMain::class,
                'options' => [
                    'route' => '/' . $slugSite . ':site-slug',
                    'constraints' => [
                        'site-slug' => $regexSite,
                    ],
                ],
                'child_routes' => [
                    'page-browse' => [
                        'options' => [
                            'route' => '/' . (SLUG_PAGE ? rtrim(SLUG_PAGE, '/') : 'page'),
                        ],
                    ],
                    'page' => [
                        'type' => \CleanUrl\Router\Http\RegexPage::class,
                        'options' => [
                            // Note: this is the same regex than for top page, but with an initial "/".
                            'regex' => '/' . $regexSitePage,
                            'spec' => '/' . SLUG_PAGE . '%page-slug%',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'cleanurl' => [
        'config' => [
            'cleanurl_site_skip_main' => false,
            'cleanurl_site_slug' => $slugSite,
            'cleanurl_page_slug' => SLUG_PAGE,

            // 10 is the hard-coded id of "dcterms:identifier" in default install.
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => '',
            'cleanurl_identifier_short' => '',
            'cleanurl_identifier_prefix_part_of' => false,
            'cleanurl_identifier_case_sensitive' => false,

            'cleanurl_item_set_paths' => [],
            'cleanurl_item_set_default' => '',
            'cleanurl_item_set_pattern' => '',
            'cleanurl_item_set_pattern_short' => '',

            'cleanurl_item_paths' => [],
            'cleanurl_item_default' => '',
            'cleanurl_item_pattern' => '',
            'cleanurl_item_pattern_short' => '',

            'cleanurl_media_paths' => [],
            'cleanurl_media_default' => '',
            'cleanurl_media_pattern' => '',
            'cleanurl_media_pattern_short' => '',

            'cleanurl_admin_use' => true,
            'cleanurl_admin_reserved' => [],

            // Allow to save settings for quick routing.
            'cleanurl_quick_settings' => [],
        ],
    ],
];
