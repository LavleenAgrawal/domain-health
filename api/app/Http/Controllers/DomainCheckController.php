<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CheckTool;
use App\Enums\ProcessingStatus;
use App\Http\Requests\SubmitCheckRequest;
use App\Jobs\ProcessDomainCheck;
use App\Models\CheckJob;
use App\Services\CheckResultCsvFormatter;
use App\Services\DomainNormalizer;
use App\Services\UploadParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DomainCheckController extends Controller
{
    public function __construct(private readonly DomainNormalizer $normalizer, private readonly UploadParser $uploads, private readonly CheckResultCsvFormatter $csv) {}

    public function store(SubmitCheckRequest $request): JsonResponse
    {
        $owner = $request->cookie('domain_health_owner') ?: Str::random(64);
        $rows = $this->requestRows($request);
        [$job, $queuedIds] = DB::transaction(function () use ($request, $owner, $rows): array {
            $job = CheckJob::create(['owner_token_hash' => hash('sha256', $owner), 'tool' => CheckTool::from($request->string('tool')->value()), 'options' => ['show_all_records' => $request->boolean('show_all_records')], 'expires_at' => now()->addDays((int) config('domain_health.retention_days'))]);
            $deduped = [];
            $resultRows = [];
            $inputRows = [];
            $queuedIds = [];
            $failedCount = 0;
            $totalRows = 0;
            $now = now();
            foreach ($rows as $row) {
                $totalRows++;
                try {
                    $domain = $this->normalizer->normalize($row['input']);
                    $selector = $this->normalizer->selector($row['selector'] ?? $request->input('selector'));
                    $key = hash('sha256', $job->tool->value.'|'.$domain.'|'.$selector.'|'.json_encode($job->options));
                    if (! isset($deduped[$key])) {
                        $resultId = (string) Str::ulid();
                        $deduped[$key] = $resultId;
                        $queuedIds[] = $resultId;
                        $resultRows[] = ['id' => $resultId, 'check_job_id' => $job->id, 'original_input' => $row['input'], 'normalized_domain' => $domain, 'dkim_selector' => $selector, 'dedupe_key' => $key, 'status' => ProcessingStatus::Queued->value, 'dns_records' => null, 'findings' => null, 'errors' => null, 'checked_at' => null, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    $inputRows[] = ['check_job_id' => $job->id, 'check_result_id' => $deduped[$key], 'row_number' => $row['row'] ?? $totalRows, 'original_input' => $row['input'], 'validation_errors' => null, 'created_at' => $now, 'updated_at' => $now];
                } catch (\InvalidArgumentException $e) {
                    $failedCount++;
                    $inputRows[] = ['check_job_id' => $job->id, 'check_result_id' => null, 'row_number' => $row['row'] ?? $totalRows, 'original_input' => $row['input'], 'validation_errors' => json_encode([['message' => $e->getMessage()]], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now];
                    $resultRows[] = ['id' => (string) Str::ulid(), 'check_job_id' => $job->id, 'original_input' => $row['input'], 'normalized_domain' => null, 'dkim_selector' => null, 'dedupe_key' => hash('sha256', 'invalid|'.$row['input'].'|'.Str::uuid()), 'status' => ProcessingStatus::Failed->value, 'dns_records' => null, 'findings' => null, 'errors' => json_encode([['check' => 'INPUT', 'status' => $e->getMessage()]], JSON_THROW_ON_ERROR), 'checked_at' => null, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            foreach (array_chunk($resultRows, 500) as $chunk) {
                DB::table('check_results')->insert($chunk);
            }
            foreach (array_chunk($inputRows, 500) as $chunk) {
                DB::table('check_input_rows')->insert($chunk);
            }
            $job->update(['total_rows' => $totalRows, 'unique_checks' => count($deduped) + $failedCount, 'failed_count' => $failedCount]);

            return [$job->fresh(), $queuedIds];
        });
        foreach (array_chunk($queuedIds, 500) as $ids) {
            Queue::bulk(array_map(fn (string $id) => new ProcessDomainCheck($id), $ids), '', 'domain-checks');
        }

        return response()->json($this->jobPayload($job), 202)->cookie('domain_health_owner', $owner, 60 * 24 * (int) config('domain_health.retention_days'), '/', null, false, true, false, 'lax');
    }

    public function previewUpload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,text/plain', 'max:'.config('domain_health.uploads.max_kb')]]);

        return response()->json($this->uploads->preview($request->file('file')->getRealPath()));
    }

    public function show(CheckJob $checkJob): JsonResponse
    {
        return response()->json($this->jobPayload($checkJob));
    }

    public function results(Request $request, CheckJob $checkJob): JsonResponse
    {
        $query = $checkJob->results()->orderByDesc('completed_at')->orderBy('id');
        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }
        if ($finding = $request->string('finding')->value()) {
            $allowed = $checkJob->tool === CheckTool::Blacklist
                ? ['CLEAN', 'LISTED']
                : ['Google Workspace', 'Microsoft 365', 'Other', 'Not Detected'];
            if (in_array($finding, $allowed, true)) {
                $path = $checkJob->tool === CheckTool::Blacklist ? 'findings->blacklist->status' : 'findings->provider->provider';
                $query->where($path, $finding);
            }
        }
        if ($search = $request->string('search')->value()) {
            $query->where('normalized_domain', 'like', '%'.$search.'%');
        }

        return response()->json(['data' => $query->paginate(min(100, max(10, $request->integer('per_page', 50))))]);
    }

    public function retry(CheckJob $checkJob): JsonResponse
    {
        $results = $checkJob->results()->where('status', ProcessingStatus::Failed)->whereNotNull('normalized_domain')->get();
        foreach ($results as $result) {
            $result->update(['status' => ProcessingStatus::Queued, 'errors' => null, 'completed_at' => null]);
            $checkJob->decrement('failed_count');
            ProcessDomainCheck::dispatch($result->id)->onQueue('domain-checks');
        }

        return response()->json(['retried' => $results->count()]);
    }

    public function export(Request $request, CheckJob $checkJob): StreamedResponse
    {
        $filename = 'domain-health-'.$checkJob->id.'-full-report.csv';

        return response()->streamDownload(function () use ($checkJob): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->csv->headers());
            foreach ($checkJob->results()->orderBy('id')->cursor() as $r) {
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $this->csv->row($r, $checkJob->tool)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function requestRows(SubmitCheckRequest $request): iterable
    {
        if ($file = $request->file('file')) {
            return $this->uploads->entries($file->getRealPath());
        }

        return [['input' => $request->string('input')->value(), 'selector' => $request->input('selector')]];
    }

    private function jobPayload(CheckJob $job): array
    {
        return ['id' => $job->id, 'tool' => $job->tool->value, 'progress' => ['total' => $job->unique_checks, 'processed' => $job->completed_count + $job->failed_count, 'completed' => $job->completed_count, 'failed' => $job->failed_count], 'expires_at' => $job->expires_at?->toIso8601String()];
    }
}
