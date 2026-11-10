<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention: conversations (and their messages and questions) older than CONVERSATION_RETENTION_DAYS.
Schedule::command('model:prune')->daily();

// Conversations nobody ended (closed tab, lost connection) end after CONVERSATION_IDLE_MINUTES and get analysed.
Schedule::command('assistant:close-idle')->everyFiveMinutes()->withoutOverlapping();
