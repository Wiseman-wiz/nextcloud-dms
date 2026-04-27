<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'api#list', 'url' => '/api/logs', 'verb' => 'GET'],
        ['name' => 'api#stats', 'url' => '/api/stats', 'verb' => 'GET'],
        ['name' => 'api#export', 'url' => '/api/export', 'verb' => 'GET'],
    ]
];
