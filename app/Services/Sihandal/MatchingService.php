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
 *   +30 amount match (document amount vs belanja pagu)
 *   +10 fiscal year match (tahun_anggaran_id)
 *   +10 OPD match (resolved OPD id)
 * Candidates below the signal threshold are discarded as noise.
 */
final class MatchingService
{
    /** Minimum score for a candidate to be surfaced to a human. */
    private const MIN_CONFIDENCE = 30;

    public function __construct(
        private readonly TahunAnggaranService $tahunAnggaran,
    ) {}

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

        // The API filters belanja by tahun_anggaran_id (the internal
        // year id), not by the year itself — resolve it first.
        $tahunAnggaranId = $context['fiscal_year'] !== null
            ? $this->tahunAnggaran->idForYear((int) $context['fiscal_year'], $sihandal)
            : null;

        $belanja = $sihandal->belanja(array_filter([
            'tahun_anggaran_id' => $tahunAnggaranId,
            // The full fiscal year is the matching universe —
            // a single 20-row page would hide the real candidate.
            'all' => 1,
        ]));

        // Resolve the document's OPD code to the SIHANDAL OPD id
        // (operators keep the OPD catalog aligned with SIHANDAL codes).
        $opdId = $this->resolveOpdId($context['opd_code'], $sihandal);

        $candidates = collect($belanja->items)
            ->map(fn (array $item): ?array => $this->score($item, [
                'document_number' => $context['document_number'],
                'amount' => $context['amount'],
                'tahun_anggaran_id' => $tahunAnggaranId,
                'opd_id' => $opdId,
            ]))
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
     * Resolve an Agent Finance OPD code to the SIHANDAL OPD id.
     */
    private function resolveOpdId(?string $opdCode, SihandalApiInterface $sihandal): ?int
    {
        if ($opdCode === null) {
            return null;
        }

        foreach ($sihandal->opds(['all' => 1])->items as $opd) {
            if (($opd['kode'] ?? null) === $opdCode) {
                return (int) $opd['id'];
            }
        }

        return null;
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

        $itemAmount = is_numeric($item['pagu'] ?? null) ? (float) $item['pagu'] : null;

        if ($context['amount'] !== null
            && $itemAmount !== null
            && abs($context['amount'] - $itemAmount) < 1.0) {
            $score += 30;
        }

        if ($context['tahun_anggaran_id'] !== null
            && is_numeric($item['tahun_anggaran_id'] ?? null)
            && (int) $item['tahun_anggaran_id'] === $context['tahun_anggaran_id']) {
            $score += 10;
        }

        if ($context['opd_id'] !== null
            && $this->itemOpdId($item) === $context['opd_id']) {
            $score += 10;
        }

        if ($score < self::MIN_CONFIDENCE) {
            return null;
        }

        return [
            'entity_type' => 'belanja',
            'entity_id' => (string) ($item['id'] ?? ''),
            'sihandal_data' => $this->normalize($item),
            'confidence' => min(100, $score),
        ];
    }

    /**
     * The OPD a belanja line belongs to — either directly on the
     * line or through its sub-kegiatan's kegiatan.
     *
     * @param  array<string, mixed>  $item
     */
    private function itemOpdId(array $item): ?int
    {
        $direct = $item['opd_id'] ?? null;

        if (is_numeric($direct)) {
            return (int) $direct;
        }

        $kegiatan = $item['sub_kegiatan']['kegiatan'] ?? [];

        return is_numeric($kegiatan['opd_id'] ?? null) ? (int) $kegiatan['opd_id'] : null;
    }

    /**
     * The candidate payload kept on the match: the full SIHANDAL
     * record plus the display fields the workspace and the sync
     * payload read (amount from pagu, uraian, rekening kode).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalize(array $item): array
    {
        $rekening = is_array($item['rekening'] ?? null) ? $item['rekening'] : [];
        $subKegiatan = is_array($item['sub_kegiatan'] ?? null) ? $item['sub_kegiatan'] : [];

        $uraian = $subKegiatan['nama_sub_kegiatan'] ?? null;

        if (! is_string($uraian) || $uraian === '') {
            $uraian = $rekening['nama'] ?? null;
        }

        return [
            ...$item,
            'amount' => is_numeric($item['pagu'] ?? null) ? (float) $item['pagu'] : null,
            'uraian' => $uraian,
            'rekening' => is_string($rekening['kode'] ?? null) ? $rekening['kode'] : null,
        ];
    }
}
