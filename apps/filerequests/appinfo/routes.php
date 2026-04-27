<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'request#index', 'url' => '/api/requests', 'verb' => 'GET'],
        ['name' => 'request#create', 'url' => '/api/requests', 'verb' => 'POST'],
        ['name' => 'request#show', 'url' => '/api/requests/{id}', 'verb' => 'GET'],
        ['name' => 'request#accept', 'url' => '/api/requests/{id}/accept', 'verb' => 'POST'],
        ['name' => 'request#reject', 'url' => '/api/requests/{id}/reject', 'verb' => 'POST'],
        ['name' => 'request#cancel', 'url' => '/api/requests/{id}/cancel', 'verb' => 'POST'],
        ['name' => 'request#fulfill', 'url' => '/api/requests/{id}/fulfill', 'verb' => 'POST'],
        ['name' => 'request#activity', 'url' => '/api/requests/{id}/activity', 'verb' => 'GET'],
        ['name' => 'request#searchUsers', 'url' => '/api/users', 'verb' => 'GET'],
        ['name' => 'request#stats', 'url' => '/api/stats', 'verb' => 'GET'],
    ]
];
