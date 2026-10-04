<?php

namespace App\Services\Ai;

/**
 * One labeled statement of a chat answer (spec §20, §47).
 * Labels are the trust contract of the assistant:
 * FACT / CALCULATION / AI INTERPRETATION / WARNING.
 */
final class ChatStatement
{
    public const FACT = 'FACT';

    public const CALCULATION = 'CALCULATION';

    public const AI_INTERPRETATION = 'AI INTERPRETATION';

    public const WARNING = 'WARNING';

    /** @var array<int, string> */
    public const LABELS = [
        self::FACT,
        self::CALCULATION,
        self::AI_INTERPRETATION,
        self::WARNING,
    ];

    public function __construct(
        public readonly string $label,
        public readonly string $text,
        public readonly ?string $source = null,
    ) {}

    /**
     * @return array{label: string, text: string, source: string|null}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'text' => $this->text,
            'source' => $this->source,
        ];
    }
}
