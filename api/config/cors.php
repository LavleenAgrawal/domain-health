<?php

$origins = explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL', 'http://localhost:3000')));

return ['paths' => ['api/*'], 'allowed_methods' => ['*'], 'allowed_origins' => array_values(array_filter(array_unique(array_map('trim', $origins)))), 'allowed_origins_patterns' => [], 'allowed_headers' => ['*'], 'exposed_headers' => [], 'max_age' => 600, 'supports_credentials' => true];
