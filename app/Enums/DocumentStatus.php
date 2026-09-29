<?php

namespace App\Enums;

/**
 * Document lifecycle states (spec §5).
 */
enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Processing = 'processing';
    case Ocr = 'ocr';
    case AiAnalysis = 'ai_analysis';
    case ExtractionComplete = 'extraction_complete';
    case Validation = 'validation';
    case ReviewRequired = 'review_required';
    case Approved = 'approved';
    case Synced = 'synced';
    case Completed = 'completed';

    case ProcessingFailed = 'processing_failed';
    case OcrFailed = 'ocr_failed';
    case AiFailed = 'ai_failed';
    case ValidationFailed = 'validation_failed';
    case SyncFailed = 'sync_failed';

    /**
     * States considered "in flight" for dashboard/queue counters.
     *
     * @return array<DocumentStatus>
     */
    public static function inFlight(): array
    {
        return [
            self::Queued,
            self::Processing,
            self::Ocr,
            self::AiAnalysis,
            self::Validation,
        ];
    }

    /**
     * Failure states.
     *
     * @return array<DocumentStatus>
     */
    public static function failures(): array
    {
        return [
            self::ProcessingFailed,
            self::OcrFailed,
            self::AiFailed,
            self::ValidationFailed,
            self::SyncFailed,
        ];
    }
}
