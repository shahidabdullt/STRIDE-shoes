<?php

namespace App\Http\Controllers\Voice;

use App\Assistant\ToolContext;
use App\Assistant\ToolRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Voice\ToolCallRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Runs a Live toolCall the browser forwards (BLUEPRINT §5.2): same registry, same validation and the same
 * permissions as the text chat and the shop UI. Tool-level failures still return 200 with {error} inside
 * `response`, so the model can recover.
 */
class ToolCallController extends Controller
{
    public function store(ToolCallRequest $request, Conversation $conversation, ToolRegistry $registry): JsonResponse
    {
        if (! $conversation->isActive()) {
            return response()->json(['error' => 'session_ended'], 409);
        }

        if ($conversation->voiceTimeIsUp()) {
            $conversation->end('max_duration');

            return response()->json(['error' => 'session_ended'], 409);
        }

        $startedAt = microtime(true);
        $name = $request->validated('name');
        $context = new ToolContext($conversation);
        $outcome = $registry->execute($name, $request->validated('args') ?? [], $context);

        $conversation->messages()->create([
            'role' => 'tool',
            'content' => $name,
            'tool_name' => $name,
            'tool_args' => $outcome['args'],
            'tool_result' => ToolRegistry::forStorage($outcome['result']),
            'source' => 'client',
            'status' => $outcome['status'],
            'error_code' => $outcome['error_code'],
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        $conversation->forceFill(['last_activity_at' => now()])->save();

        return response()->json([
            'response' => $outcome['result'],
            'cart' => ['count' => $context->cart()?->totalQuantity() ?? 0],
        ]);
    }
}
