<?php
return [
    'controllers' => [
        'factories' => [
            'CleanUrl\Controller\Index' => 'CleanUrl\Service\Controller\IndexControllerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'getResourceFromIdentifier' => 'CleanUrl\View\Helper\GetResourceFromIdentifier',
            'getResourceFullIdentifier' => 'CleanUrl\View\Helper\GetResourceFullIdentifier',
            'getResourceTypeIdentifiers' => 'CleanUrl\View\Helper\GetResourceTypeIdentifiers',
            'getResourceIdentifier' => 'CleanUrl\View\Helper\GetResourceIdentifier',
        ],
    ],
];
