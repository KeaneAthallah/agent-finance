<?php

namespace App\Http\Resources;

use App\Models\DocumentExtractionField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentExtractionField
 */
class DocumentExtractionFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->field,
            'value' => $this->value,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
        ];
    }
}
