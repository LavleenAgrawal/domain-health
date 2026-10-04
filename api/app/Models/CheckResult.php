<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResult extends Model
{
    use HasUlids;
    protected $guarded = [];
    protected function casts(): array { return ['status' => ProcessingStatus::class, 'dns_records' => 'array', 'findings' => 'array', 'errors' => 'array', 'checked_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime']; }
    public function job(): BelongsTo { return $this->belongsTo(CheckJob::class, 'check_job_id'); }
}

