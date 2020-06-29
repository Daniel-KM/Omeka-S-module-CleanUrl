<?php
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

// pmf([$slugSite, $regexSite]);

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
            'url' => View\Helper\CleanUrl::class,
            'Url' => View\Helper\CleanUrl::class,
        ],
        'factories' => [
            'getIdentifiersFromResources' => Service\ViewHelper\GetIdentifiersFromResourcesFactory::class,
            'getResourceFromIdentifier' => Service\ViewHelper\GetResourceFromIdentifierFactory::class,
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
                // TODO Find a way to avoid to copy all the site routes, in particular for modules. Add "|" to the regex of site slug?
                // Allows to access main site resources and pages.
                // Same routes than "site", except initial "/" and routes,
                // without starting "/".
                'child_routes' => SLUG_MAIN_SITE ? [
                    'resource' => [
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Literal::class,
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
                            // Warning: this is the same regex than for top page, but with an initial "/".
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
            // 10 is the hard coded id of "dcterms:identifier" in default install.
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => 'document:',
            'cleanurl_identifier_unspace' => false,
            'cleanurl_identifier_undefined' => 'default',
            'cleanurl_main_path' => '',
            'cleanurl_main_path_2' => '',
            'cleanurl_main_path_3' => '',
            'cleanurl_main_short' => 'no',
            'cleanurl_item_set_generic' => '',
            'cleanurl_item_set_keep_raw' => false,
            'cleanurl_item_default' => 'generic_item',
            'cleanurl_item_allowed' => [
                'generic_item',
                'item_set_item',
            ],
            'cleanurl_item_generic' => 'document/',
            'cleanurl_item_keep_raw' => false,
            'cleanurl_item_item_set_included' => 'no',
            'cleanurl_item_item_set_undefined' => 'item_set_id',
            'cleanurl_media_default' => 'generic_media',
            'cleanurl_media_allowed' => [
                'generic_media',
                'item_set_item_media',
            ],
            'cleanurl_media_generic' => 'medium/',
            'cleanurl_media_keep_raw' => false,
            'cleanurl_media_item_set_included' => 'no',
            'cleanurl_media_item_included' => 'no',
            'cleanurl_media_item_set_undefined' => 'item_set_id',
            'cleanurl_media_item_undefined' => 'item_id',
            'cleanurl_media_media_undefined' => 'item_id',
            'cleanurl_media_format_position' => 'p%d',
            'cleanurl_admin_use' => true,
            'cleanurl_admin_show_identifier' => true,
            'cleanurl_admin_reserved' => [],
            // Allow to save settings for quick routing.
            'cleanurl_quick_settings' => [],
        ],
    ],
];
