<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CheckEvent;
use App\Models\CheckJob;
use App\Models\CheckResult;

class CheckEventPublisher
{
    public function resultChanged(CheckJob $job, CheckResult $result): void
    {
        CheckEvent::create(['check_job_id' => $job->id, 'type' => 'result.changed', 'payload' => ['result' => $result->fresh()->toArray(), 'progress' => $this->progress($job->fresh())]]);
    }
    public function progress(CheckJob $job): array { return ['total' => $job->unique_checks, 'completed' => $job->completed_count, 'failed' => $job->failed_count, 'processed' => $job->completed_count + $job->failed_count]; }
}

