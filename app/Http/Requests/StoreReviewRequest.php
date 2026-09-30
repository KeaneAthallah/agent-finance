<?php

namespace App\Http\Requests;

use App\Enums\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * Authorization is handled by DocumentPolicy::review on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Review decision validation (spec §14).
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:'.implode(',', ReviewDecision::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'A review decision is required.',
            'decision.in' => 'The decision must be one of: '.implode(', ', ReviewDecision::values()).'.',
        ];
    }
}
