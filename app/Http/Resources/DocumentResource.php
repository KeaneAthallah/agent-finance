<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 *
 * @property Document $resource
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_human' => $this->humanFileSize(),
            'page_count' => $this->page_count,
            'fiscal_year' => $this->fiscal_year,
            'document_number' => $this->document_number,
            'document_type' => $this->whenLoaded('documentType', fn () => $this->documentType?->only(['id', 'code', 'name'])),
            'opd' => $this->whenLoaded('opd', fn () => $this->opd->only(['id', 'code', 'name'])),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader->only(['id', 'name'])),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
