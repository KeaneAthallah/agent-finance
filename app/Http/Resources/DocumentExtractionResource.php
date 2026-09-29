<?php

namespace App\Http\Resources;

use App\Models\DocumentExtraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentExtraction
 */
class DocumentExtractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'schema_name' => $this->schema_name,
            'schema_version' => $this->schema_version,
            'status' => $this->status,
            'confidence' => $this->confidence,
            'error' => $this->error,
            'payload' => $this->payload,
            'fields' => DocumentExtractionFieldResource::collection(
                $this->whenLoaded('fields'),
            ),
            'ai_run' => $this->whenLoaded('run', fn () => $this->run !== null ? [
                'id' => $this->run->id,
                'provider' => $this->run->provider,
                'model' => $this->run->model,
                'prompt_name' => $this->run->prompt_name,
                'prompt_version' => $this->run->prompt_version,
            ] : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
