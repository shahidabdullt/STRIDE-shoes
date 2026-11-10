<?php

namespace App\Assistant\Tools;

use App\Assistant\ToolContext;

/**
 * One assistant tool, shared by text chat and (later) voice. The registry validates arguments with
 * rules() before handle() ever runs, so handlers can trust their input.
 */
interface AssistantTool
{
    public function name(): string;

    public function description(): string;

    /** JSON Schema (type object) sent to Gemini as the function's parameters. */
    public function parameters(): array;

    /** Laravel validation rules for the arguments; the authority, whatever the schema says. */
    public function rules(): array;

    /** @return array<string, mixed> JSON-serialisable result for the model. */
    public function handle(array $args, ToolContext $context): array;
}
