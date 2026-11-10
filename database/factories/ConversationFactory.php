<?php

namespace Database\Factories;

use App\Models\AiConfigVersion;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'channel' => 'text',
            'device_class' => 'desktop',
            'visitor_id' => (string) Str::uuid(),
            'ai_config_version_id' => AiConfigVersion::factory(),
            'status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ];
    }

    public function voice(): static
    {
        return $this->state(['channel' => 'voice', 'voice_mode' => 'hands_free']);
    }
}
