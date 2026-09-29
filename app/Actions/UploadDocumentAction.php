<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Events\DocumentUploaded;
use App\Exceptions\DuplicateDocumentException;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ingests a financial document (spec §7, §8): validates the binary, hashes,
 * rejects duplicates, stores on the private documents disk and records the
 * document row. Deliberately synchronous; queue processing is a separate
 * action (Phase 3) triggered by DocumentUploaded.
 */
final class UploadDocumentAction
{
    /**
     * @throws DuplicateDocumentException when the SHA-256 hash already exists
     * @throws RuntimeException when storage fails
     */
    public function execute(
        UploadedFile $file,
        User $uploader,
        ?DocumentType $documentType,
        ?int $fiscalYear,
    ): Document {
        $sha256 = hash_file('sha256', $file->getRealPath(), false);

        if (! is_string($sha256)) {
            throw new RuntimeException('Unable to hash the uploaded file.');
        }

        $existing = Document::query()->where('file_hash', $sha256)->first();

        if ($existing !== null) {
            throw new DuplicateDocumentException($existing);
        }

        $disk = (string) config('agent-finance.documents.disk', 'documents');

        $path = sprintf(
            'opd-%d/%s/%s%s',
            $uploader->opd_id ?? 0,
            now()->format('Y/m'),
            Str::uuid()->toString(),
            '.'.$file->getClientOriginalExtension(),
        );

        $stored = $file->storeAs(dirname($path), basename($path), ['disk' => $disk]);

        if ($stored === false) {
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        return DB::transaction(function () use ($file, $uploader, $documentType, $fiscalYear, $sha256, $stored, $disk): Document {
            $document = Document::query()->create([
                'opd_id' => $uploader->opd_id,
                'document_type_id' => $documentType?->id,
                'uploaded_by' => $uploader->id,
                'status' => DocumentStatus::Uploaded,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $stored,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'file_hash' => $sha256,
                'page_count' => $this->countPages($file, $disk, $stored),
                'fiscal_year' => $fiscalYear,
            ]);

            DocumentUploaded::dispatch($document, $uploader);

            return $document;
        });
    }

    /**
     * Page count for PDFs (cross-reference "/Type /Page" markers) — image
     * formats are single-page; other formats null until Phase 3 extraction.
     */
    private function countPages(UploadedFile $file, string $disk, string $stored): ?int
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return null;
        }

        $stream = Storage::disk($disk)->readStream($stored);

        if (! is_resource($stream)) {
            return null;
        }

        $head = (string) stream_get_contents($stream, 512 * 1024);
        fclose($stream);

        $count = preg_match_all('/\/Type\s*\/Page[^s]/', $head);

        return $count > 0 ? $count : null;
    }
}
