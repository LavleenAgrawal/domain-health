<?php

declare(strict_types=1);

return [
    'dns' => [
        'resolver_url' => env('DNS_RESOLVER_URL', 'https://cloudflare-dns.com/dns-query'),
        // Do not inherit machine-wide proxy variables. Set this explicitly when a deployment requires a proxy.
        'http_proxy' => env('DNS_HTTP_PROXY', ''),
        // Optional PEM bundle for PHP installations that do not have a system CA store configured.
        'ca_bundle' => env('DNS_CA_BUNDLE'),
        'timeout_seconds' => (int) env('DNS_TIMEOUT_SECONDS', 4),
        'deadline_seconds' => (int) env('DNS_DOMAIN_DEADLINE_SECONDS', 20),
        'cache_seconds' => 300,
        'dkim_selectors' => array_filter(explode(',', (string) env('DKIM_SELECTORS', ''))),
    ],
    'uploads' => ['max_kb' => (int) env('UPLOAD_MAX_KB', 5120), 'max_rows' => (int) env('UPLOAD_MAX_ROWS', 10000)],
    'retention_days' => (int) env('RESULT_RETENTION_DAYS', 30),
    // DNSBLs use the deployment's attributable system resolver. Enable only sources your use is eligible for.
    'blacklists' => [
        'surbl' => ['enabled' => (bool) env('SURBL_ENABLED', false), 'kind' => 'domain', 'zone' => 'multi.surbl.org'],
        'spamhaus_zen' => ['enabled' => (bool) env('SPAMHAUS_ENABLED', false), 'kind' => 'ip', 'zone' => 'zen.spamhaus.org'],
        'spamrats' => ['enabled' => (bool) env('SPAMRATS_ENABLED', false), 'kind' => 'ip', 'zone' => 'all.spamrats.com'],
    ],
];
