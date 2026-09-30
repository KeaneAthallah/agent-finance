<?php

namespace App\Services\Validation;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;

/**
 * One rule finding, neutral of persistence: the engine turns findings into
 * document_anomalies rows.
 */
final class ValidationFinding
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function __construct(
        public readonly AnomalyType $type,
        public readonly AnomalySeverity $severity,
        public readonly string $title,
        public readonly ?string $detail = null,
        public readonly ?array $evidence = null,
    ) {}
}
