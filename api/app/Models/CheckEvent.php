<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['payload' => 'array', 'created_at' => 'immutable_datetime']; }
}

