<?php

namespace App\Http\Requests;

use App\Rules\FileSignature;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Authorization is handled by DocumentPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Upload validation (spec §7).
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('agent-finance.documents.allowed_extensions', ['pdf']);
        $maxKb = (int) config('agent-finance.documents.max_size_kb', 20480);

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'extensions:'.implode(',', $extensions),
                new FileSignature,
            ],
            'document_type_id' => ['nullable', 'integer', 'exists:document_types,id'],
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file exceeds the maximum upload size of :max KB.',
            'file.extensions' => 'The file type is not supported.',
        ];
    }
}
