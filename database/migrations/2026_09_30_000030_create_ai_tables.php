<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI operational tables (spec §32, §33): versioned prompts, per-call run
     * records, and token/cost usage. Every AI call writes a run row so the
     * system can answer "which prompt/model produced this value?" (§51).
     */
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version', 32);
            $table->text('system_prompt');
            $table->text('user_prompt')->nullable();
            $table->json('schema')->nullable();
            $table->string('model')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index(['name', 'is_active']);
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained('ai_prompts')->nullOnDelete();

            // classification | extraction | chat | embedding
            $table->string('stage')->index();
            $table->string('provider');
            $table->string('model')->nullable();

            // Denormalized so history survives prompt row changes (§33).
            $table->string('prompt_name')->nullable();
            $table->string('prompt_version', 32)->nullable();

            // running | completed | failed
            $table->string('status')->default('running');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['stage', 'status']);
            $table->index(['provider', 'model']);
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_estimate', 12, 8)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['provider', 'model']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_prompts');
    }
};
