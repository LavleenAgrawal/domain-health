<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckInputRow extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['validation_errors' => 'array']; }
}
