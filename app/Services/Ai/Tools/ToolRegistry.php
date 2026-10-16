<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * Registry of the assistant's read-only tools (spec §19).
 * Tools are resolved from the container so their dependencies
 * (SIHANDAL client, hybrid search) stay injectable in tests.
 */
final class ToolRegistry
{
    /** @var array<int, class-string<ToolInterface>> */
    private const TOOLS = [
        GetBudgetSummaryTool::class,
        GetDocumentTextTool::class,
        SearchDocumentsTool::class,
        CompareDocumentWithSihandalTool::class,
        GetSihandalDataTool::class,
        ValidateDocumentTool::class,
    ];

    /** @return array<int, ToolInterface> */
    public function all(): array
    {
        return array_map(
            static fn (string $class): ToolInterface => app($class),
            self::TOOLS,
        );
    }

    /**
     * The tool definitions in provider tool-calling format.
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            static fn (ToolInterface $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
            $this->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments, User $user): array
    {
        foreach ($this->all() as $tool) {
            if ($tool->name() === $name) {
                return $tool->execute($arguments, $user);
            }
        }

        throw new \InvalidArgumentException("Unknown tool: {$name}");
    }
}
