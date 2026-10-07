<?php

namespace App\Services\Ai\Tools;

use App\Models\Document;
use App\Models\User;

/**
 * Validation results and open anomalies for a document
 * (spec §19, §15). Read-only; OPD-scoped.
 */
final class ValidateDocumentTool implements ToolInterface
{
    public function name(): string
    {
        return 'validate_document';
    }

    public function description(): string
    {
        return 'Get the validation result and open anomaly indicators for a processed document.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'Agent Finance document ID',
                ],
            ],
            'required' => ['document_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        if (! is_numeric($arguments['document_id'] ?? null)) {
            throw new \InvalidArgumentException('document_id is required.');
        }

        $document = Document::query()
            ->accessibleBy($user)
            ->findOrFail((int) $arguments['document_id']);

        if (! $user->can('view', $document)) {
            throw new \LogicException('This document is not accessible to you.');
        }

        $validation = $document->validations()
            ->orderByDesc('version')
            ->first();

        $anomalies = $document->anomalies()
            ->where('status', 'open')
            ->get(['type', 'severity', 'title', 'detail']);

        return [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'status' => $document->status->value,
            'validation' => $validation !== null ? [
                'result' => $validation->result,
                'summary' => $validation->summary,
                'version' => $validation->version,
            ] : null,
            'open_anomalies' => $anomalies->all(),
            'refs' => [['type' => 'document', 'id' => $document->id]],
        ];
    }
}
