<?php

namespace App\Http\Controllers\Voice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Voice\TranscriptRequest;
use App\Models\Conversation;
use App\Support\Redactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Stores Live transcripts reported by the browser, redacted and marked source=client. They are
 * informational only and never fed back into a prompt.
 */
class TranscriptController extends Controller
{
    /** The browser flushes its last batch while ending, so a just-ended session still accepts transcripts briefly. */
    private const LATE_SECONDS = 120;

    public function store(TranscriptRequest $request, Conversation $conversation): Response|JsonResponse
    {
        $lateAllowed = $conversation->ended_at?->copy()->addSeconds(self::LATE_SECONDS)->isFuture() ?? false;

        if (! $conversation->isActive() && ! $lateAllowed) {
            return response()->json(['error' => 'session_ended'], 409);
        }

        $segments = collect($request->validated('segments'))
            ->map(fn (array $s) => ['role' => $s['role'], 'text' => trim($s['text']), 'at' => $s['at'] ?? null])
            ->filter(fn (array $s) => $s['text'] !== '');

        foreach ($segments as $segment) {
            $message = $conversation->messages()->make([
                'role' => $segment['role'],
                'content' => Redactor::redact($segment['text']),
                'source' => 'client',
            ]);
            // Segments arrive in batches after the tool calls they led to, so they keep the time the speech
            // started (client-reported, clamped to the session) and the transcript reads in order.
            $message->created_at = $this->spokenAt($conversation, $segment['at']);
            $message->save();
        }

        $conversation->forceFill([
            'message_count' => $conversation->message_count + $segments->count(),
            'last_activity_at' => now(),
        ])->save();

        return response()->noContent();
    }

    private function spokenAt(Conversation $conversation, ?string $at): Carbon
    {
        $now = now();

        if ($at === null) {
            return $now;
        }

        $time = Carbon::parse($at)->setTimezone($now->getTimezone());

        return $time->between($conversation->started_at, $now) ? $time : $now;
    }
}
