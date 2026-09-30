<?php

namespace App\Services\Validation;

use App\Enums\AnomalyStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentAnomaly;
use App\Models\DocumentExtraction;
use App\Models\DocumentValidation;
use App\Services\Extraction\ExtractionSchemaRegistry;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic validation engine (spec §15, §25): runs the registered rules
 * over the latest completed extraction and persists the pass/fail outcome
 * plus per-rule anomalies. Fully separate from the AI layer — rules are pure
 * functions; this class owns persistence and the lifecycle decision
 * (review_required vs approved per the auto-approve policy).
 */
final class ValidationEngine
{
    public const ENGINE_VERSION = '1';

    /**
     * @param  array<int, ValidationRule>  $rules
     */
    public function __construct(
        private readonly array $rules,
        private readonly float $highConfidence,
        private readonly float $lowConfidence,
        private readonly ExtractionSchemaRegistry $registry,
    ) {}

    /**
     * Validate the document's latest completed extraction and route the
     * document: clean + confident → approved; otherwise → review_required.
     */
    public function validate(Document $document): DocumentValidation
    {
        $extraction = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first();

        $startedAt = now();

        $validation = new DocumentValidation([
            'version' => $this->nextVersion($document),
            'engine_version' => self::ENGINE_VERSION,
            'result' => 'passed',
        ]);
        $validation->document()->associate($document);

        if ($extraction === null) {
            $validation->fill([
                'result' => 'failed',
                'summary' => ['error' => 'No completed extraction to validate.'],
                'duration_ms' => (int) ($startedAt->diffInMilliseconds(now())),
            ]);
            $validation->save();

            $document->update(['status' => DocumentStatus::ValidationFailed]);

            return $validation;
        }

        $fields = $extraction->fields()->get()->keyBy('field');

        $context = new ValidationContext(
            document: $document,
            extraction: $extraction,
            fields: $fields,
            highConfidence: $this->highConfidence,
            lowConfidence: $this->lowConfidence,
            requiredFields: $this->requiredFieldNames($extraction),
        );

        $findings = [];

        foreach ($this->rules as $rule) {
            $findings = array_merge($findings, $rule->check($context));
        }

        $this->persist($document, $validation, $extraction, $findings, $startedAt);

        // Auto-approve policy: a document with ANY open anomaly (even an
        // informational one) goes to human review; only truly clean, confident
        // extractions are approved automatically.
        $this->route($document, $findings !== [], $extraction->version);

        return $validation;
    }

    /**
     * Persist the validation row and its anomaly rows atomically, replacing
     * the previous open set (re-validation is idempotent). Findings are
     * grouped by rule type — one anomaly per type per document, with the
     * worst severity winning and the field-level detail in evidence.
     *
     * @param  array<int, ValidationFinding>  $findings
     */
    private function persist(
        Document $document,
        DocumentValidation $validation,
        DocumentExtraction $extraction,
        array $findings,
        CarbonImmutable $startedAt,
    ): void {
        DB::transaction(function () use ($document, $validation, $extraction, $findings, $startedAt): void {
            $document->anomalies()->open()->delete();

            $validation->document_extraction_id = $extraction->id;
            $validation->result = $findings === [] ? 'passed' : 'failed';
            $validation->summary = [
                'anomaly_count' => count($findings),
                'by_type' => collect($findings)
                    ->countBy(fn (ValidationFinding $f) => $f->type->value)
                    ->all(),
            ];
            $validation->duration_ms = (int) $startedAt->diffInMilliseconds(now());
            $validation->save();

            $grouped = collect($findings)
                ->groupBy(fn (ValidationFinding $f) => $f->type->value);

            $severityRank = [
                'info' => 0,
                'warning' => 1,
                'high' => 2,
                'critical' => 3,
            ];

            foreach ($grouped as $type => $typeFindings) {
                $worst = $typeFindings
                    ->sortByDesc(fn (ValidationFinding $f) => $severityRank[$f->severity->value])
                    ->first();

                \assert($worst instanceof ValidationFinding);

                DocumentAnomaly::query()->create([
                    'document_id' => $document->id,
                    'document_validation_id' => $validation->id,
                    'type' => $worst->type,
                    'severity' => $worst->severity,
                    'status' => AnomalyStatus::Open,
                    'title' => $typeFindings->count() > 1
                        ? sprintf('%s (%d fields)', $worst->title, $typeFindings->count())
                        : $worst->title,
                    'detail' => $worst->detail,
                    'evidence' => [
                        'findings' => collect($typeFindings)
                            ->map(fn (ValidationFinding $f) => $f->evidence ?? ['title' => $f->title])
                            ->all(),
                    ],
                ]);
            }

            $document->forceFill(['validated_at' => now()])->save();
        });

        AuditLogger::record(
            action: 'document.validated',
            subject: $document,
            actorType: ActorType::System,
            properties: [
                'validation_id' => $validation->id,
                'result' => $validation->result,
                'anomaly_count' => count($findings),
                'extraction_version' => $extraction->version,
            ],
        );

        Log::info('Document validated', [
            'document_id' => $document->id,
            'validation_id' => $validation->id,
            'result' => $validation->result,
            'anomalies' => count($findings),
        ]);
    }

    /**
     * Lifecycle routing (spec §5, §14): documents with findings wait for a
     * human; clean + confident documents are approved automatically, recorded
     * as a system decision so the audit trail stays complete.
     */
    private function route(Document $document, bool $hasFindings, int $extractionVersion): void
    {
        if ($hasFindings) {
            $document->update(['status' => DocumentStatus::ReviewRequired]);

            AuditLogger::record(
                action: 'document.status_changed',
                subject: $document,
                actorType: ActorType::System,
                properties: [
                    'from' => DocumentStatus::ExtractionComplete->value,
                    'to' => DocumentStatus::ReviewRequired->value,
                    'reason' => 'anomalies_detected',
                ],
            );

            return;
        }

        $document->update(['status' => DocumentStatus::Approved]);

        AuditLogger::record(
            action: 'document.auto_approved',
            subject: $document,
            actorType: ActorType::System,
            properties: [
                'reason' => 'clean_validation',
                'extraction_version' => $extractionVersion,
            ],
        );

        $document->reviews()->create([
            'decision' => 'approved',
            'notes' => 'Auto-approved: validation passed with no blocking anomalies and confidence at or above threshold.',
            'extraction_version' => $extractionVersion,
        ]);
    }

    private function nextVersion(Document $document): int
    {
        return (int) $document->validations()->max('version') + 1;
    }

    /**
     * Required field names for the extraction's schema (spec §10): resolved
     * through the registry so the engine never hardcodes document types.
     *
     * @return array<int, string>
     */
    private function requiredFieldNames(DocumentExtraction $extraction): array
    {
        $schema = $this->registry->forType($extraction->schema_name);

        return collect($schema->fields)
            ->filter(fn ($field) => $field->required)
            ->pluck('name')
            ->all();
    }
}
