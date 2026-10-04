<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RAG layer (spec §21, §45): searchable chunks of whitelisted
     * document content with provider embeddings. Metadata filters
     * (OPD, fiscal year, document type) are real columns so the
     * hybrid search stays portable across SQLite (tests) and
     * MySQL (production) — vector similarity is computed in PHP.
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Processing version the chunk was produced from.
            $table->unsignedInteger('version');
            $table->unsignedInteger('chunk_index');

            $table->text('content');

            // Provider embedding vector (JSON array of floats).
            $table->json('embedding')->nullable();

            // Metadata filters: OPD isolation, fiscal year, type.
            $table->foreignId('opd_id')->nullable()->constrained('opds')->nullOnDelete();
            $table->unsignedInteger('fiscal_year')->nullable();
            $table->string('document_type')->nullable();

            $table->timestamps();

            $table->unique(['document_id', 'version', 'chunk_index']);
            $table->index(['opd_id', 'fiscal_year']);
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
