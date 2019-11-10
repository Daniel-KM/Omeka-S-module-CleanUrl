<?php
namespace CleanUrl;

// In order to have a main site without "/s/site-slug", fill your main site slug here.
// TODO Use the main default site slug from the settings.
const MAIN_SITE_SLUG = null;

return [
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
        'factories' => [
            Controller\Admin\CleanUrlController::class => Service\Controller\Admin\CleanUrlControllerFactory::class,
            Controller\Site\CleanUrlController::class => Service\Controller\Site\CleanUrlControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'top' => [
                'may_terminate' => true,
                // Same routes than "site", except initial "/" and default values.
                'child_routes' => [
                    'resource' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => ':controller[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller\Site',
                                '__SITE__' => true,
                                'site-slug' => MAIN_SITE_SLUG,
                                'action' => 'browse',
                            ],
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                        ],
                    ],
                    'resource-id' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => ':controller/:id[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller\Site',
                                '__SITE__' => true,
                                'site-slug' => MAIN_SITE_SLUG,
                                'action' => 'show',
                            ],
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                        ],
                    ],
                    'item-set' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => 'item-set/:item-set-id',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller\Site',
                                '__SITE__' => true,
                                'site-slug' => MAIN_SITE_SLUG,
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
                            'route' => 'page',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller\Site',
                                '__SITE__' => true,
                                'site-slug' => MAIN_SITE_SLUG,
                                'controller' => 'Page',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'page' => [
                        'type' => \Zend\Router\Http\Segment::class,
                        'options' => [
                            'route' => 'page/:page-slug',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller\Site',
                                '__SITE__' => true,
                                'site-slug' => MAIN_SITE_SLUG,
                                'controller' => 'Page',
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'cleanurl' => [
        'config' => [
            // 10 is the hard set id of "dcterms:identifier" in default install.
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
            'cleanurl_use_admin' => true,
            'cleanurl_display_admin_show_identifier' => true,
        ],
    ],
];
