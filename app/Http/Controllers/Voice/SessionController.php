<?php

namespace App\Http\Controllers\Voice;

use App\Assistant\AssistantAvailability;
use App\Assistant\LiveSessionConfig;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\Voice\EndVoiceSessionRequest;
use App\Http\Requests\Voice\StartVoiceSessionRequest;
use App\Models\AiConfigVersion;
use App\Models\Conversation;
use App\Services\Gemini\GeminiException;
use App\Services\Gemini\GeminiLiveTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Voice session lifecycle (BLUEPRINT §7.3). The browser talks to Gemini Live directly with a single-use,
 * locked, short-lived token minted here; the API key never leaves the server.
 */
class SessionController extends Controller
{
    public function __construct(
        private LiveSessionConfig $live,
        private GeminiLiveTokenService $tokens,
    ) {}

    public function store(StartVoiceSessionRequest $request, AssistantAvailability $availability): JsonResponse
    {
        $config = AiConfigVersion::active();

        if ($config === null || ! $config->voice_enabled || $availability->voiceCapReached()) {
            return $this->unavailable();
        }

        $mode = $request->validated('mode');
        $conversation = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'channel' => 'voice',
            'voice_mode' => $mode,
            'device_class' => $request->validated('device_class'),
            'visitor_id' => EnsureVisitorId::idFor($request),
            'ai_config_version_id' => $config->id,
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $minted = $this->mint($conversation);

        if ($minted === null) {
            return $this->unavailable();
        }

        $availability->countVoiceSession();

        return response()->json([
            'conversation_uuid' => $conversation->uuid,
            'token' => $minted['token'],
            'ws_url' => config('services.gemini.live_ws_url'),
            'model' => $this->live->modelName($config),
            'mode' => $mode,
            'expires_at' => $minted['expires_at']->toIso8601String(),
            'max_seconds' => (int) $config->voice_max_seconds,
        ], 201);
    }

    /** A fresh token for a reconnect, only when the original cannot be reused (resumption does not use it up). */
    public function token(Conversation $conversation): JsonResponse
    {
        if (! $conversation->isActive() || $conversation->voiceTimeIsUp()) {
            return response()->json(['error' => 'session_ended'], 409);
        }

        $minted = $this->mint($conversation, failOnError: false);

        if ($minted === null) {
            return $this->unavailable();
        }

        return response()->json([
            'token' => $minted['token'],
            'expires_at' => $minted['expires_at']->toIso8601String(),
        ]);
    }

    public function end(EndVoiceSessionRequest $request, Conversation $conversation): Response
    {
        $max = (int) $conversation->aiConfigVersion->voice_max_seconds;
        $elapsed = (int) $conversation->started_at->diffInSeconds(now());
        $usage = $request->validated('usage') ?? [];

        // Client-reported numbers are clamped to what is physically possible.
        $conversation->end($request->validated('reason'), [
            'duration_seconds' => min((int) $request->validated('duration_seconds', $elapsed), $elapsed + 5, $max + 60),
            'input_tokens' => $conversation->input_tokens + (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => $conversation->output_tokens + (int) ($usage['output_tokens'] ?? 0),
        ]);

        return response()->noContent();
    }

    /** @return array{token: string, expires_at: CarbonImmutable}|null */
    private function mint(Conversation $conversation, bool $failOnError = true): ?array
    {
        $config = $conversation->aiConfigVersion;
        $setup = $this->live->setup($config, $conversation->voice_mode);

        try {
            return $this->tokens->mint($setup, $this->live->fieldMask($setup), (int) $config->voice_max_seconds);
        } catch (GeminiException $e) {
            Log::warning('Gemini Live token failed', ['kind' => $e->kind, 'status' => $e->status, 'message' => mb_substr($e->getMessage(), 0, 300), 'conversation' => $conversation->id]);

            if ($failOnError) {
                $conversation->forceFill(['status' => 'failed', 'ended_reason' => 'error', 'ended_at' => now()])->save();
            }

            return null;
        }
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'assistant_unavailable',
            'message' => 'Voice is not available right now. You can still use the text chat or browse as usual.',
        ], 503);
    }
}
