<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Sihandal\SihandalApiInterface;

/**
 * OPD (work unit) master data from the SIHANDAL
 * system of record (spec §19). Read-only.
 */
final class GetOpdTool implements ToolInterface
{
    public function __construct(
        private readonly SihandalApiInterface $sihandal,
    ) {}

    public function name(): string
    {
        return 'get_opd';
    }

    public function description(): string
    {
        return 'Get OPD (work unit) master data from SIHANDAL, optionally filtered by OPD code.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional OPD code, e.g. 4.01.0',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        $filters = [];

        $code = is_string($arguments['code'] ?? null) ? trim($arguments['code']) : '';

        if ($code !== '') {
            $filters['code'] = $code;
        }

        $opds = $this->sihandal->opds($filters)->items;

        return [
            'count' => count($opds),
            'opds' => $opds,
        ];
    }
}
