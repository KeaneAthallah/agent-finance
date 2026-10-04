<?php

namespace App\Services\Ai\Tools;

use App\Models\Document;
use App\Models\User;
use App\Services\Sihandal\MatchingService;
use App\Services\Sihandal\SihandalApiInterface;

/**
 * Compares a document's extracted facts against the SIHANDAL
 * system of record (spec §19, §17). Read-only: it reports the
 * comparison, it never writes to SIHANDAL.
 */
final class CompareDocumentWithSihandalTool implements ToolInterface
{
    public function __construct(
        private readonly SihandalApiInterface $sihandal,
        private readonly MatchingService $matching,
    ) {}

    public function name(): string
    {
        return 'compare_document_with_sihandal';
    }

    public function description(): string
    {
        return 'Compare a document\'s extracted financial facts against its best SIHANDAL match candidates (amount, document number, fiscal year differences).';
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

        $extraction = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first();

        $facts = $extraction->payload ?? [];

        $candidates = $this->matching->match($document, $this->sihandal);

        $comparison = $candidates->map(fn ($match): array => [
            'entity_type' => $match->entity_type,
            'entity_id' => $match->entity_id,
            'confidence' => $match->confidence,
            'sihandal_amount' => $match->sihandal_data['amount'] ?? null,
            'extracted_amount' => $facts['amount'] ?? null,
            'amount_difference' => isset($match->sihandal_data['amount'], $facts['amount'])
                ? (float) $facts['amount'] - (float) $match->sihandal_data['amount']
                : null,
            'sihandal_document_number' => $match->sihandal_data['document_number'] ?? null,
            'extracted_document_number' => $facts['document_number'] ?? $document->document_number,
            'status' => $match->status->value,
        ]);

        return [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'extraction' => $facts,
            'candidates' => $comparison->all(),
        ];
    }
}
