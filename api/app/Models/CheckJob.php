<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckTool;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckJob extends Model
{
    use HasUlids;
    protected $guarded = [];
    protected function casts(): array { return ['tool' => CheckTool::class, 'options' => 'array', 'completed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime']; }
    public function results(): HasMany { return $this->hasMany(CheckResult::class); }
    public function events(): HasMany { return $this->hasMany(CheckEvent::class); }
}

