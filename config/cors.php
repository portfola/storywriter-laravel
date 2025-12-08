<?php 
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:8081', // Your local Expo Web
        'https://storywriter.net', // Your AWS Live Web App
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // Important for allowing 'Authorization' header
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Keep this true even for tokens
];