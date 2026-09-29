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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opd_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            // Lifecycle status (spec §5) — App\Enums\DocumentStatus.
            $table->string('status')->default('uploaded')->index();

            // File facts.
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->unique();
            $table->unsignedInteger('page_count')->nullable();

            // Context metadata.
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->string('document_number')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['opd_id', 'status']);
            $table->index(['document_type_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
