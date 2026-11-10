<?php

namespace App\Assistant;

use App\Models\AiConfigVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Cost guards (BLUEPRINT §5.1): the admin kill switches on the active AI config version, and global
 * daily caps on chat messages and voice sessions counted in the cache.
 */
class AssistantAvailability
{
    public function chatEnabled(): bool
    {
        return (bool) AiConfigVersion::active()?->chat_enabled;
    }

    public function voiceEnabled(): bool
    {
        return (bool) AiConfigVersion::active()?->voice_enabled;
    }

    public function chatCapReached(): bool
    {
        return $this->count('chat-messages') >= config('assistant.daily_chat_cap');
    }

    public function voiceCapReached(): bool
    {
        return $this->count('voice-sessions') >= config('assistant.daily_voice_session_cap');
    }

    public function countChatMessage(): void
    {
        $this->increment('chat-messages');
    }

    public function countVoiceSession(): void
    {
        $this->increment('voice-sessions');
    }

    private function count(string $name): int
    {
        return (int) Cache::get($this->key($name), 0);
    }

    private function increment(string $name): void
    {
        Cache::add($this->key($name), 0, now()->endOfDay());
        Cache::increment($this->key($name));
    }

    private function key(string $name): string
    {
        return "assistant:{$name}:".now()->toDateString();
    }
}
