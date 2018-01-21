<?php
namespace CleanUrl;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
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
            Controller\Site\CleanUrlController::class => Service\Controller\Site\CleanUrlControllerFactory::class,
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
            'cleanurl_media_generic' => 'media/',
            'cleanurl_display_admin_show_identifier' => true,
        ],
    ],
];
