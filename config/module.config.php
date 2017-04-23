<?php
namespace CleanUrl;

return [
    'controllers' => [
        'factories' => [
            Controller\Index::class => Service\Controller\IndexControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
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
];
