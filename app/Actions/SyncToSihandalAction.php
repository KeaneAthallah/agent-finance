<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Models\Document;
use App\Models\SihandalMatch;
use App\Models\SihandalSync;
use App\Models\User;
use App\Services\Sihandal\SihandalApiInterface;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Human-confirmed sync to SIHANDAL (spec §17, §18, §48, §49).
 * The write path to the system of record: only approved documents,
 * only confirmed candidates, only explicit user action. The full
 * request/response is persisted so every write is replayable.
 */
final class SyncToSihandalAction
{
    public function __construct(
        private readonly SihandalApiInterface $sihandal,
    ) {}

    /**
     * Confirm a match candidate and sync the document to SIHANDAL.
     *
     * @throws \LogicException when the document is not approved or
     *                         the candidate is not pending.
     */
    public function execute(
        Document $document,
        SihandalMatch $match,
        User $user,
        ?Request $request = null,
    ): SihandalSync {
        if ($document->status !== DocumentStatus::Approved) {
            throw new \LogicException('Only approved documents can be synced to SIHANDAL.');
        }

        if ($match->document_id !== $document->id) {
            throw new \LogicException('The match candidate does not belong to this document.');
        }

        if ($match->status !== SihandalMatchStatus::Pending) {
            throw new \LogicException('The match candidate has already been decided.');
        }

        $sync = $document->syncs()->create([
            'sihandal_match_id' => $match->id,
            'user_id' => $user->id,
            'endpoint' => '/api/v1/pengeluaran',
            'method' => 'POST',
            'request' => $this->payload($document, $match),
            'status' => SihandalSyncStatus::Pending->value,
            'attempts' => 1,
        ]);

        return $this->dispatch($document, $match, $sync, $user, $request);
    }

    /**
     * Retry a pending (SIHANDAL was unreachable) or failed sync
     * with the exact same request payload.
     *
     * @throws \LogicException when the sync is not retryable.
     */
    public function retry(SihandalSync $sync, User $user, ?Request $request = null): SihandalSync
    {
        if (! in_array($sync->status, [SihandalSyncStatus::Pending, SihandalSyncStatus::Failed], true)) {
            throw new \LogicException('Only pending or failed syncs can be retried.');
        }

        $document = $sync->document;

        if ($document->status === DocumentStatus::Synced) {
            throw new \LogicException('The document is already synced.');
        }

        $match = $sync->match;

        if ($match !== null && $match->status !== SihandalMatchStatus::Pending) {
            throw new \LogicException('The match candidate has already been decided.');
        }

        $sync->update([
            'status' => SihandalSyncStatus::Pending->value,
            'error' => null,
            'attempts' => $sync->attempts + 1,
        ]);

        return $this->dispatch($document, $match, $sync, $user, $request);
    }

    /**
     * Push the payload through the API and record the outcome.
     * Connection failures stay pending (retryable, spec §50);
     * API errors fail the sync and the document loudly.
     */
    private function dispatch(
        Document $document,
        ?SihandalMatch $match,
        SihandalSync $sync,
        User $user,
        ?Request $request,
    ): SihandalSync {
        try {
            $created = $this->sihandal->syncDocument((array) $sync->request);

            $sync->update([
                'status' => SihandalSyncStatus::Success->value,
                'response' => $created,
                'http_status' => 201,
                'error' => null,
            ]);

            if ($match !== null) {
                $match->update([
                    'status' => SihandalMatchStatus::Confirmed->value,
                    'confirmed_by' => $user->id,
                    'confirmed_at' => now(),
                ]);
            }

            $document->update([
                'status' => DocumentStatus::Synced,
                'synced_at' => now(),
            ]);

            AuditLogger::record(
                action: 'document.synced',
                subject: $document,
                actor: $user,
                actorType: ActorType::Human,
                request: $request,
                properties: [
                    'sihandal_sync_id' => $sync->id,
                    'sihandal_match_id' => $match?->id,
                    'endpoint' => $sync->endpoint,
                    'sihandal_resource_id' => $created['id'] ?? null,
                ],
            );
        } catch (ConnectionException $exception) {
            // SIHANDAL unreachable: keep the sync pending so it can be
            // retried; the document is never lost (spec §50).
            $sync->update([
                'status' => SihandalSyncStatus::Pending->value,
                'error' => 'SIHANDAL unreachable: '.$exception->getMessage(),
                'attempts' => $sync->attempts + 1,
            ]);

            Log::warning('SIHANDAL sync pending (unreachable)', [
                'document_id' => $document->id,
                'sihandal_sync_id' => $sync->id,
                'error' => $exception->getMessage(),
            ]);
        } catch (RequestException $exception) {
            $sync->update([
                'status' => SihandalSyncStatus::Failed->value,
                'response' => $exception->response->json(),
                'http_status' => $exception->response->status(),
                'error' => $exception->getMessage(),
            ]);

            $document->update(['status' => DocumentStatus::SyncFailed]);

            AuditLogger::record(
                action: 'document.sync_failed',
                subject: $document,
                actorType: ActorType::System,
                properties: [
                    'sihandal_sync_id' => $sync->id,
                    'http_status' => $exception->response->status(),
                    'error' => $exception->getMessage(),
                ],
            );
        }

        return $sync->refresh();
    }

    /**
     * The payload pushed to SIHANDAL: the matched belanja
     * line's full hierarchy (SIHANDAL enforces consistency
     * across these ids), the realized amount and the document
     * identity for traceability.
     *
     * @return array<string, mixed>
     */
    private function payload(Document $document, SihandalMatch $match): array
    {
        $payload = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first()->payload ?? [];

        $candidate = $match->sihandal_data;

        $subKegiatan = is_array($candidate['sub_kegiatan'] ?? null) ? $candidate['sub_kegiatan'] : [];

        $amount = $document->metadata['extracted_amount'] ?? $document->metadata['extracted_total_budget'] ?? null;

        return array_filter([
            'opd_id' => $candidate['opd_id'] ?? null,
            'kegiatan_id' => $subKegiatan['kegiatan_id'] ?? null,
            'sub_kegiatan_id' => $candidate['sub_kegiatan_id'] ?? $subKegiatan['id'] ?? null,
            'belanja_id' => $candidate['id'] ?? null,
            'rekening_id' => $candidate['rekening_id'] ?? null,
            'sumber_dana_id' => $candidate['sumber_dana_id'] ?? null,
            'tahun_anggaran_id' => $candidate['tahun_anggaran_id'] ?? null,
            'realisasi' => is_numeric($amount) ? (float) $amount : null,
            'tanggal' => is_string($payload['document_date'] ?? null) ? $payload['document_date'] : null,
            'keterangan' => $document->document_number ?? $document->original_filename,
            'document_number' => $document->document_number,
            'source_document_id' => $document->id,
            'agent_finance_file' => $document->original_filename,
            'matched_at' => $match->created_at->toIso8601String(),
        ], static fn ($value): bool => $value !== null);
    }
}
