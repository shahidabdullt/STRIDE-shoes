<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:close-idle')]
#[Description('End conversations idle for CONVERSATION_IDLE_MINUTES and queue their analysis')]
class CloseIdleConversations extends Command
{
    public function handle(): int
    {
        $closed = 0;

        Conversation::where('status', 'active')
            ->where('last_activity_at', '<', now()->subMinutes((int) config('assistant.idle_minutes')))
            ->each(function (Conversation $conversation) use (&$closed) {
                $conversation->end('idle');
                $closed++;
            });

        $this->info("Closed {$closed} idle conversation(s).");

        return self::SUCCESS;
    }
}
