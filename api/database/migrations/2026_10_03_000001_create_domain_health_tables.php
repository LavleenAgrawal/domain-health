<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('check_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('owner_token_hash', 64)->index();
            $table->string('tool', 24)->index();
            $table->json('options');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('unique_checks')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('check_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('check_job_id')->constrained()->cascadeOnDelete();
            $table->string('original_input', 1024);
            $table->string('normalized_domain', 253)->nullable()->index();
            $table->string('dkim_selector', 63)->nullable();
            $table->string('dedupe_key', 64);
            $table->string('status', 24)->default('queued')->index();
            $table->json('dns_records')->nullable();
            $table->json('findings')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['check_job_id', 'dedupe_key']);
            $table->index(['check_job_id', 'status', 'id']);
        });
        Schema::create('check_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('check_job_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['check_job_id', 'id']);
        });
        Schema::create('check_input_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('check_job_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('check_result_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('original_input', 1024);
            $table->json('validation_errors')->nullable();
            $table->timestamps();
            $table->index(['check_job_id', 'row_number']);
        });
    }
    public function down(): void { Schema::dropIfExists('check_input_rows'); Schema::dropIfExists('check_events'); Schema::dropIfExists('check_results'); Schema::dropIfExists('check_jobs'); }
};
