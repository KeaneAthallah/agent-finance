<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version'); // processing version (spec §31, §38)
            $table->string('stage');            // text_extraction|classification|...
            $table->string('status');           // pending|running|completed|failed
            $table->string('job_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'version']);
            $table->index(['document_id', 'stage', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_processing_runs');
    }
};
