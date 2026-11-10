<?php

return [

    /*
    | Models an AI config version may use. Anything else is rejected, so a typo in the admin form
    | cannot silently break the assistant. Add a model here once it has been verified (docs/phase-0-findings.md).
    */
    'text_models' => ['gemini-3.6-flash', 'gemini-3.8-flash'],
    'live_models' => ['gemini-3.8-live'],

    // gemini-3.6-flash is the verified default (decision of 8 Nov 2026); 3.8-flash was unavailable on a free-tier key.
    'default_text_model' => env('GEMINI_TEXT_MODEL', 'gemini-3.6-flash'),
    'default_live_model' => env('GEMINI_LIVE_MODEL', 'gemini-3.8-live'),

    /* Always-included knowledge goes into every prompt, so its total size is capped (words, published articles only). */
    'always_include_max_words' => 300,

    /*
    | Text chat turn limits (BLUEPRINT §2.4). The deadline covers the whole turn, including tool rounds.
    */
    'max_tool_rounds' => 4,
    'history_messages' => 20,
    'turn_deadline_seconds' => 20,
    'message_max_chars' => 1000,

    /* Optional gate for chat and voice. Empty means no gate. */
    'access_code' => env('ASSISTANT_ACCESS_CODE'),

    /* Global daily caps across all visitors (cost guard). */
    'daily_chat_cap' => (int) env('ASSISTANT_DAILY_CHAT_CAP', 2000),
    'daily_voice_session_cap' => (int) env('ASSISTANT_DAILY_VOICE_SESSION_CAP', 100),

    /* Card-like numbers (Luhn-checked) are always masked before storage; emails only when this is on. */
    'redact_emails' => (bool) env('ASSISTANT_REDACT_EMAILS', true),

    'retention_days' => (int) env('CONVERSATION_RETENTION_DAYS', 30),
    'idle_minutes' => (int) env('CONVERSATION_IDLE_MINUTES', 15),

    /* Voice mode chosen by default before a session starts (BLUEPRINT §2.7). */
    'voice_default_mode_mobile' => env('VOICE_DEFAULT_MODE_MOBILE', 'hold_to_talk'),
    'voice_default_mode_desktop' => env('VOICE_DEFAULT_MODE_DESKTOP', 'hands_free'),

    /* Most knowledge gaps the model may report in one conversation. */
    'max_gap_reports' => 10,

    'greeting' => 'Hi! I can help you find shoes, check sizes and stock, answer questions about shipping and returns, and add things to your cart. What are you looking for?',

];
