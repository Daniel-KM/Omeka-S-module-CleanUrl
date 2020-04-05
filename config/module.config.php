<?php
namespace CleanUrl;

return [
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
            'getResourceFullIdentifier' => Service\ViewHelper\GetResourceFullIdentifierFactory::class,
            'getResourceTypeIdentifiers' => Service\ViewHelper\GetResourceTypeIdentifiersFactory::class,
            'getResourceIdentifier' => Service\ViewHelper\GetResourceIdentifierFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            // Override the page controller used for the root url.
            'Omeka\Controller\Site\Page' => Controller\Site\PageController::class,
        ],
        'factories' => [
            Controller\Admin\CleanUrlController::class => Service\Controller\Admin\CleanUrlControllerFactory::class,
            Controller\Site\CleanUrlController::class => Service\Controller\Site\CleanUrlControllerFactory::class,
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
                            // Forbid any reserved words.
                            'regex' => SLUG_PAGE . '(?:files|s|item|item-set|media|page|api|api-context|admin|login|logout|create-password|forgot-password|maintenance|migrate|install'
                                // Capturing group ("-" cannot be used).
                                . '|(?P<page_slug>[a-zA-Z0-9_-]+))',
                            'spec' => '%page-slug%',
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
                'child_routes' => [
                    'page-browse' => [
                        'options' => [
                            'route' => '/' . (SLUG_PAGE ? rtrim(SLUG_PAGE, '/') : 'page'),
                        ],
                    ],
                    'page' => [
                        'type' => \CleanUrl\Router\Http\RegexPage::class,
                        'options' => [
                            // Forbid any reserved words.
                            // Warning: this is the same regex than for top page, but with an initial "/".
                            'regex' => '/' . SLUG_PAGE . '(?:files|s|item|item-set|media|page|api|api-context|admin|login|logout|create-password|forgot-password|maintenance|migrate|install'
                                // Capturing group ("-" cannot be used).
                                . '|(?P<page_slug>[a-zA-Z0-9_-]+))',
                            'spec' => '/%page-slug%',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'cleanurl' => [
        'config' => [
            // 10 is the hard coded id of "dcterms:identifier" in default install.
            'cleanurl_identifier_property' => 10,
            'cleanurl_identifier_prefix' => 'document:',
            'cleanurl_identifier_unspace' => false,
            'cleanurl_case_insensitive' => false,
            'cleanurl_main_path' => '',
            'cleanurl_item_set_regex' => '',
            'cleanurl_item_set_generic' => '',
            'cleanurl_item_default' => 'generic',
            'cleanurl_item_allowed' => ['generic', 'item_set'],
            'cleanurl_item_generic' => 'document/',
            'cleanurl_media_default' => 'generic',
            'cleanurl_media_allowed' => ['generic', 'item_set_item'],
            'cleanurl_media_generic' => 'medium/',
            'cleanurl_page_slug' => SLUG_PAGE,
            'cleanurl_use_admin' => true,
            'cleanurl_display_admin_show_identifier' => true,
        ],
    ],
];
