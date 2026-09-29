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
        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('image_path')->nullable();      // rendered page image (OCR input)
            $table->longText('extracted_text')->nullable(); // embedded or OCR text
            $table->unsignedTinyInteger('ocr_confidence')->nullable(); // 0-100
            $table->string('text_source')->nullable();     // embedded|ocr
            $table->string('language', 10)->nullable();
            $table->string('processing_status')->default('pending'); // pending|done|failed|skipped
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'page_number']);
            $table->index(['document_id', 'processing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_pages');
    }
};
