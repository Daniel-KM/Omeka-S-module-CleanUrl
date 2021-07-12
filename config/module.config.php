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
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
    ],
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'view_manager' => [
        'controller_map' => [
            Controller\Site\PageController::class => 'omeka/site/page',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'getIdentifiersFromResources' => View\Helper\GetIdentifiersFromResources::class,
            'getResourceFromIdentifier' => View\Helper\GetResourceFromIdentifier::class,
            'url' => View\Helper\CleanUrl::class,
            'Url' => View\Helper\CleanUrl::class,
        ],
        'factories' => [
            'getIdentifiersFromResourcesOfType' => Service\ViewHelper\GetIdentifiersFromResourcesOfTypeFactory::class,
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
                        'page-slug' => null,
                        'controller' => 'Page',
                        'action' => 'show',
                    ],
                ],
                'may_terminate' => true,
                // Same routes than "site", except initial "/" and routes, without starting "/".
                // Allows to access main site resources and pages.
                // TODO Find a way to avoid to copy all the site routes, in particular for modules. Add "|" to the regex of site slug?
                'child_routes' => SLUG_MAIN_SITE ? [
                    'page-browse' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'priority' => 5,
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
                        // The priority avoids to exclude the slug page as a controller in top/resource and top/resource-id.
                        'priority' => 5,
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

            'cleanurl_item_set' => [
                'default' => 'collection/{item_set_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                // 10 is the hard-coded id of "dcterms:identifier" in default install.
                'property' => 10,
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],

            'cleanurl_item' => [
                'default' => 'document/{item_identifier}',
                'short' => '',
                'paths' => [],
                'pattern' => '[a-zA-Z0-9][a-zA-Z0-9_-]*',
                'pattern_short' => '',
                'property' => 10,
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],

            'cleanurl_media' => [
                'default' => 'document/{item_identifier}/{media_id}',
                'short' => '',
                'paths' => [],
                'pattern' => '',
                'pattern_short' => '',
                'property' => 10,
                'prefix' => '',
                'prefix_part_of' => false,
                'keep_slash' => false,
                'case_sensitive' => false,
            ],

            'cleanurl_admin_use' => false,
            'cleanurl_admin_reserved' => [],

            // Allow to save settings for quick routing. Filled during install and config.
            'cleanurl_settings' => [
                'routes' => [],
                'route_aliases' => [],
            ],
        ],
    ],
];
