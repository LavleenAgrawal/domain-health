<?php

return ['default' => env('CACHE_STORE', 'database'), 'stores' => ['array' => ['driver' => 'array', 'serialize' => false], 'redis' => ['driver' => 'redis', 'connection' => 'cache'], 'database' => ['driver' => 'database', 'connection' => null, 'table' => 'cache', 'lock_connection' => null]], 'prefix' => env('CACHE_PREFIX', 'domain_health_cache')];

