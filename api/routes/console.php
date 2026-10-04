<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('domain-health:prune', function (): void {
    \App\Models\CheckJob::where('expires_at', '<', now())->each(fn ($job) => $job->delete());
})->purpose('Remove expired domain health jobs and their results');

Schedule::command('domain-health:prune')->daily();

