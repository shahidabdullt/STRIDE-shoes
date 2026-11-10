<?php

namespace App\Assistant;

use App\Assistant\Tools\AddToCartTool;
use App\Assistant\Tools\AssistantTool;
use App\Assistant\Tools\CheckSizeAvailabilityTool;
use App\Assistant\Tools\GetProductTool;
use App\Assistant\Tools\RemoveFromCartTool;
use App\Assistant\Tools\ReportKnowledgeGapTool;
use App\Assistant\Tools\SearchKnowledgeTool;
use App\Assistant\Tools\SearchProductsTool;
use App\Assistant\Tools\ViewCartTool;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The only door between the model and the application. Tool arguments are untrusted: they are validated
 * against each tool's Laravel rules, and unknown keys are dropped, before a handler runs. Failures come back
 * as {error: ...} results so the model can recover instead of the turn crashing.
 */
class ToolRegistry
{
    /** Deliberately no tool creates orders, changes prices or stock, or reads admin or other visitors' data. */
    private const TOOLS = [
        SearchProductsTool::class,
        GetProductTool::class,
        CheckSizeAvailabilityTool::class,
        SearchKnowledgeTool::class,
        ViewCartTool::class,
        AddToCartTool::class,
        RemoveFromCartTool::class,
        ReportKnowledgeGapTool::class,
    ];

    /** @var array<string, AssistantTool>|null */
    private ?array $tools = null;

    /** @return array<string, AssistantTool> */
    public function tools(): array
    {
        return $this->tools ??= collect(self::TOOLS)
            ->map(fn (string $class) => app($class))
            ->keyBy(fn (AssistantTool $tool) => $tool->name())
            ->all();
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools());
    }

    /** Function declarations in the Interactions API format. @return list<array<string, mixed>> */
    public function interactionsDeclarations(): array
    {
        return array_values(array_map(fn (AssistantTool $tool) => [
            'type' => 'function',
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->parameters(),
        ], $this->tools()));
    }

    /**
     * The same tools as Live API functionDeclarations: Gemini Schema types (upper case, the form verified in
     * Phase 0 S1/S3) and `behavior: BLOCKING`, because async calling is the default on gemini-3.8-live.
     *
     * @return list<array<string, mixed>>
     */
    public function liveDeclarations(): array
    {
        return array_values(array_map(function (AssistantTool $tool) {
            $declaration = ['name' => $tool->name(), 'description' => $tool->description(), 'behavior' => 'BLOCKING'];
            $parameters = $tool->parameters();

            // A tool without arguments (view_cart) is declared without parameters.
            if (! empty((array) ($parameters['properties'] ?? []))) {
                $declaration['parameters'] = $this->toGeminiSchema($parameters);
            }

            return $declaration;
        }, $this->tools()));
    }

    /** Tool results are stored for the admin, capped at about 8 KB. */
    public static function forStorage(array $result): array
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        return strlen($json) <= 8192 ? $result : ['truncated' => true, 'preview' => mb_strcut($json, 0, 8000)];
    }

    private function toGeminiSchema(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === 'type' && is_string($value)) {
                $schema[$key] = strtoupper($value);
            } elseif ($key === 'properties' && is_array($value)) {
                $schema[$key] = array_map(fn (array $p) => $this->toGeminiSchema($p), $value);
            } elseif ($key === 'items' && is_array($value)) {
                $schema[$key] = $this->toGeminiSchema($value);
            }
        }

        return $schema;
    }

    /**
     * @return array{result: array<string, mixed>, status: 'ok'|'error', error_code: ?string, args: array<string, mixed>}
     */
    public function execute(string $name, mixed $args, ToolContext $context): array
    {
        $tool = $this->tools()[$name] ?? null;

        if ($tool === null) {
            return $this->failure('unknown_tool', "There is no tool called {$name}.", []);
        }

        if (! is_array($args)) {
            return $this->failure('invalid_arguments', 'Arguments must be an object.', []);
        }

        $validator = Validator::make($args, $tool->rules());

        if ($validator->fails()) {
            return $this->failure('invalid_arguments', 'Invalid arguments: '.implode(' ', $validator->errors()->all()), $args);
        }

        $clean = $validator->validated();

        try {
            $result = $tool->handle($clean, $context);
        } catch (Throwable $e) {
            report($e);

            return $this->failure('tool_failed', 'The tool failed. Apologise and suggest the help page.', $clean);
        }

        $isError = isset($result['error']);

        return ['result' => $result, 'status' => $isError ? 'error' : 'ok', 'error_code' => $isError ? 'tool_error' : null, 'args' => $clean];
    }

    /** @return array{result: array<string, mixed>, status: 'error', error_code: string, args: array<string, mixed>} */
    private function failure(string $code, string $message, array $args): array
    {
        return ['result' => ['error' => $message], 'status' => 'error', 'error_code' => $code, 'args' => $args];
    }
}
