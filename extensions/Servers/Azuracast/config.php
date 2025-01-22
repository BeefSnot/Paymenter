<?php

return [
    'name' => 'AzuraCast',
    'description' => 'AzuraCast server management extension',
    'version' => '1.0.0',
    'author' => 'Your Name',
    'fields' => [
        'host' => [
            'label' => 'AzuraCast URL',
            'type' => 'text',
            'default' => 'https://example.com/',
            'description' => 'AzuraCast URL',
            'required' => true,
            'validation' => 'url',
        ],
        'api_key' => [
            'label' => 'AzuraCast API Key',
            'type' => 'text',
            'default' => 'azc_abcdefgh12345678',
            'description' => 'AzuraCast API Key',
            'required' => true,
            'encrypted' => true,
        ],
    ],
];