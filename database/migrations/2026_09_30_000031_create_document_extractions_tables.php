<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extraction storage (spec §10–§13): one extraction row per processing
     * version (historical versions kept, §38), and per-field rows carrying
     * value, confidence, and evidence (page, bbox, source text) so the UI can
     * jump to the document location and reviewers see why the AI decided.
     */
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();

            $table->unsignedInteger('version');
            $table->string('schema_name');
            $table->string('schema_version', 32)->default('1.0');

            // Full schema-valid payload (§10), set when the pass completes;
            // nullable because rows are created in `running` state.
            $table->json('payload')->nullable();

            // running | completed | failed
            $table->string('status')->default('running')->index();
            $table->unsignedTinyInteger('confidence')->nullable(); // 0-100
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
            $table->index(['document_id', 'status']);
        });

        Schema::create('document_extraction_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_extraction_id')->constrained()->cascadeOnDelete();

            $table->string('field');
            $table->text('value')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable(); // 0-100

            // Evidence (§12): {page, bbox: [x0,y0,x1,y1], source_text}.
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['document_extraction_id', 'field']);
            $table->index(['document_extraction_id', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extraction_fields');
        Schema::dropIfExists('document_extractions');
    }
};
