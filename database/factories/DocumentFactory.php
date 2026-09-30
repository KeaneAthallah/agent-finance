<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = 'agent-finance-fixture: '.fake()->unique()->uuid();

        return [
            'opd_id' => Opd::factory(),
            'document_type_id' => DocumentType::factory(),
            'uploaded_by' => User::factory(),
            'status' => DocumentStatus::Uploaded,
            'original_filename' => fake()->word().'.pdf',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => hash('sha256', $content),
            'fiscal_year' => 2026,
            'metadata' => null,
        ];
    }

    /**
     * Backfill metadata from the payload (Phase 5): amounts as plain numbers
     * and the fiscal year from extraction — used by validation tests.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->afterCreating(fn (Document $document) => $document->forceFill([
            'metadata' => $metadata,
        ])->save());
    }

    /**
     * Create the backing file on the documents disk.
     */
    public function withFile(string $content = 'fixture-pdf-content'): static
    {
        return $this->afterCreating(function (Document $document) use ($content) {
            $disk = config('agent-finance.documents.disk');

            Storage::disk($disk)
                ->put($document->file_path, $content);
        });
    }
}
