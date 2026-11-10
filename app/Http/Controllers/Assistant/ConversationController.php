<?php

namespace App\Http\Controllers\Assistant;

use App\Assistant\AssistantAvailability;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\Assistant\StartConversationRequest;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function store(StartConversationRequest $request, AssistantAvailability $availability): JsonResponse
    {
        $config = AiConfigVersion::active();

        if ($config === null || ! $config->chat_enabled || $availability->chatCapReached()) {
            return $this->unavailable();
        }

        $conversation = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'channel' => 'text',
            'device_class' => $request->validated('device_class'),
            'visitor_id' => EnsureVisitorId::idFor($request),
            'ai_config_version_id' => $config->id,
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json(['uuid' => $conversation->uuid, 'greeting' => config('assistant.greeting')], 201);
    }

    /** Used to restore the chat after a page reload. Tool messages are internal and never sent. */
    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json([
            'uuid' => $conversation->uuid,
            'status' => $conversation->status,
            'greeting' => config('assistant.greeting'),
            'messages' => $conversation->messages()->whereIn('role', ['user', 'assistant'])->get()
                ->map(fn (Message $m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    public function end(Conversation $conversation): Response
    {
        // Ending also queues the post-conversation analysis.
        $conversation->end('user_ended');

        return response()->noContent();
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'assistant_unavailable',
            'message' => 'The assistant is not available right now. You can still browse and check out as usual.',
        ], 503);
    }
}
