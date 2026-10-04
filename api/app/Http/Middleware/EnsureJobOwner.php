<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CheckJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJobOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var CheckJob $job */ $job = $request->route('checkJob');
        if (!$job || !hash_equals($job->owner_token_hash, hash('sha256', (string) $request->cookie('domain_health_owner')))) abort(404);
        return $next($request);
    }
}

