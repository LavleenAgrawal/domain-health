<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\DnsResolver;
use App\Services\DohDnsResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->bind(DnsResolver::class, DohDnsResolver::class); }
    public function boot(): void { RateLimiter::for('domain-health', fn (Request $request) => Limit::perMinute(30)->by($request->ip())); RateLimiter::for('domain-dns', fn () => Limit::perMinute(240)); }
}

