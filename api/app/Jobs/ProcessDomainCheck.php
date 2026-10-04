<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CheckTool;
use App\Enums\ProcessingStatus;
use App\Models\CheckJob;
use App\Models\CheckResult;
use App\Services\BlacklistService;
use App\Services\CheckEventPublisher;
use App\Services\DnsInspectionService;
use App\Services\ProviderDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessDomainCheck implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3; public int $timeout = 30; public array $backoff = [3, 10, 30];
    public function __construct(public readonly string $resultId) {}
    public function uniqueId(): string { return $this->resultId; }
    public function middleware(): array { return [new RateLimited('domain-dns')]; }
    public function handle(DnsInspectionService $dns, BlacklistService $blacklists, ProviderDetectionService $providers, CheckEventPublisher $events): void
    {
        $result = CheckResult::with('job')->findOrFail($this->resultId); if ($result->status->isTerminal()) return;
        $result->update(['status' => ProcessingStatus::Checking]); $events->resultChanged($result->job, $result);
        try {
            $inspection = $dns->inspect($result->normalized_domain, $result->dkim_selector, (bool) ($result->job->options['show_all_records'] ?? false));
            $findings = $result->job->tool === CheckTool::Provider ? ['provider' => $providers->detect($inspection['records']['MX'] ?? [])] : ['blacklist' => $blacklists->check($inspection['records'], $result->normalized_domain)];
            DB::transaction(function () use ($result, $inspection, $findings): void {
                $locked = CheckResult::lockForUpdate()->findOrFail($result->id); if ($locked->status->isTerminal()) return;
                $locked->update(['status' => ProcessingStatus::Completed, 'dns_records' => $inspection['records'], 'findings' => $findings, 'errors' => $inspection['errors'], 'checked_at' => now(), 'completed_at' => now()]);
                CheckJob::whereKey($locked->check_job_id)->increment('completed_count');
            });
        } catch (Throwable $exception) { $this->failResult($result, $exception); }
        $events->resultChanged($result->job->fresh(), $result->fresh());
    }
    public function failed(Throwable $exception): void { if ($result = CheckResult::with('job')->find($this->resultId)) $this->failResult($result, $exception); }
    private function failResult(CheckResult $result, Throwable $exception): void { DB::transaction(function () use ($result, $exception): void { $locked = CheckResult::lockForUpdate()->findOrFail($result->id); if ($locked->status->isTerminal()) return; $locked->update(['status' => ProcessingStatus::Failed, 'errors' => [['check' => 'PROCESSING', 'status' => class_basename($exception)]], 'completed_at' => now()]); CheckJob::whereKey($locked->check_job_id)->increment('failed_count'); }); }
}
