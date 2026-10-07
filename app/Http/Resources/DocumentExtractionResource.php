<?php

namespace App\Http\Resources;

use App\Models\DocumentExtraction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
            // Resolved here: Inertia wraps nested resources in a {"data": ...} envelope.
            'fields' => $this->whenLoaded(
                'fields',
                fn (EloquentCollection $fields): array => DocumentExtractionFieldResource::collection($fields)->resolve($request),
                [],
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
