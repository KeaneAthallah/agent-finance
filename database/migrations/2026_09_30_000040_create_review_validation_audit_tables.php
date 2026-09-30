<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Human review + validation quality tables (spec §14, §15, §25) and the
     * platform-wide audit trail (§35): every state transition and manual
     * action records actor type (human|ai|system) so "who decided this?" is
     * always answerable.
     */
    public function up(): void
    {
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            // pending | approved | rejected | reanalysis_requested
            $table->string('decision')->index();
            $table->text('notes')->nullable();

            // What the reviewer decided on: extraction version + payload snapshot.
            $table->unsignedInteger('extraction_version')->nullable();
            $table->json('extraction_snapshot')->nullable();

            $table->timestamps();

            $table->index(['document_id', 'decision']);
        });

        Schema::create('document_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_extraction_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('version');
            $table->string('engine_version', 16)->default('1');
            // passed | failed
            $table->string('result')->index();

            $table->json('summary')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
            $table->index(['document_id', 'result']);
        });

        Schema::create('document_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_validation_id')->nullable()->constrained()->nullOnDelete();

            // total_mismatch | fiscal_year_mismatch | missing_document_number |
            // low_confidence | duplicate
            $table->string('type')->index();

            // info | warning | high | critical
            $table->string('severity')->index();

            // open | resolved | dismissed
            $table->string('status')->default('open')->index();

            $table->string('title');
            $table->text('detail')->nullable();

            // Rule evidence: field, expected/actual, confidence, page, duplicate_document_id.
            $table->json('evidence')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'type', 'status'], 'document_anomalies_unique_open');
            $table->index(['document_id', 'severity']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // human | ai | system
            $table->string('actor_type')->default('system')->index();
            $table->string('action')->index();

            $table->nullableMorphs('subject');

            // IP/user agent for human actions; arbitrary context otherwise.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_anomalies');
        Schema::dropIfExists('document_validations');
        Schema::dropIfExists('document_reviews');
    }
};
