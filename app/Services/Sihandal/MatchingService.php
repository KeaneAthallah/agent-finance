<?php

namespace App\Services\Sihandal;

use App\Enums\SihandalMatchStatus;
use App\Models\Document;
use App\Models\SihandalMatch;
use Illuminate\Database\Eloquent\Collection;

/**
 * Candidate matching against SIHANDAL (spec §17): reads the
 * system of record through the API contract and scores each
 * candidate deterministically — never a black-box AI verdict.
 *
 * Scoring (explainable, 0–100):
 *   +50 exact document number match
 *   +30 amount match
 *   +10 fiscal year match
 *   +10 OPD code match
 * Candidates below the signal threshold are discarded as noise.
 */
final class MatchingService
{
    /** Minimum score for a candidate to be surfaced to a human. */
    private const MIN_CONFIDENCE = 30;

    /** @return array<string, mixed> */
    private function context(Document $document): array
    {
        $payload = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first()->payload ?? [];

        $amount = $payload['amount'] ?? $payload['total_budget'] ?? null;

        return [
            'document_number' => $document->document_number ?? (is_string($payload['document_number'] ?? null) ? $payload['document_number'] : null),
            'amount' => is_numeric($amount) ? (float) $amount : null,
            'fiscal_year' => $document->fiscal_year,
            'opd_code' => $document->opd?->code,
        ];
    }

    /**
     * Produce match candidates for a document (spec §17).
     * Replaces prior pending candidates for the same processing
     * version — confirmed/rejected candidates are kept as history.
     *
     * @return Collection<int, SihandalMatch>
     */
    public function match(Document $document, SihandalApiInterface $sihandal, ?int $version = null): Collection
    {
        $context = $this->context($document);

        $version ??= (int) $document->processingRuns()->max('version');

        // Candidates only make sense for documents with a completed
        // extraction and identifiable fiscal context.
        if ($context['document_number'] === null && $context['amount'] === null) {
            return new Collection;
        }

        $belanja = $sihandal->belanja(array_filter([
            'fiscal_year' => $context['fiscal_year'],
        ]));

        $candidates = collect($belanja->items)
            ->map(fn (array $item): ?array => $this->score($item, $context))
            ->filter()
            ->sortByDesc(fn (array $candidate): int => $candidate['confidence'])
            ->values()
            ->all();

        if ($candidates === []) {
            return new Collection;
        }

        // Prior pending candidates for this version are stale —
        // replace them atomically (same idempotency rule as validation).
        SihandalMatch::query()
            ->where('document_id', $document->id)
            ->where('version', $version)
            ->where('status', SihandalMatchStatus::Pending->value)
            ->delete();

        $created = collect($candidates)
            ->map(fn (array $candidate): SihandalMatch => $document->matches()->create([
                'version' => $version,
                'entity_type' => $candidate['entity_type'],
                'entity_id' => (string) $candidate['entity_id'],
                'sihandal_data' => $candidate['sihandal_data'],
                'confidence' => $candidate['confidence'],
                'status' => SihandalMatchStatus::Pending->value,
            ]));

        return new Collection($created->all());
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function score(array $item, array $context): ?array
    {
        $score = 0;

        $itemNumber = is_string($item['document_number'] ?? null) ? $item['document_number'] : null;

        if ($context['document_number'] !== null
            && $itemNumber !== null
            && $itemNumber === $context['document_number']) {
            $score += 50;
        }

        $itemAmount = is_numeric($item['amount'] ?? null) ? (float) $item['amount'] : null;

        if ($context['amount'] !== null
            && $itemAmount !== null
            && abs($context['amount'] - $itemAmount) < 1.0) {
            $score += 30;
        }

        if ($context['fiscal_year'] !== null
            && is_numeric($item['fiscal_year'] ?? null)
            && (int) $item['fiscal_year'] === $context['fiscal_year']) {
            $score += 10;
        }

        if ($context['opd_code'] !== null
            && is_string($item['opd_code'] ?? null)
            && $item['opd_code'] === $context['opd_code']) {
            $score += 10;
        }

        if ($score < self::MIN_CONFIDENCE) {
            return null;
        }

        return [
            'entity_type' => 'belanja',
            'entity_id' => (string) ($item['id'] ?? ''),
            'sihandal_data' => $item,
            'confidence' => min(100, $score),
        ];
    }
}
