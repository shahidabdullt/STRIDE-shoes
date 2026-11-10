<x-layouts.app :title="'Conversation '.$conversation->id">
    <x-admin.nav />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold tracking-tight">Conversation #{{ $conversation->id }}</h1>
        <x-admin.outcome-badge :outcome="$conversation->outcome" />
        @if ($conversation->failure_kind)
            <x-badge variant="warning">{{ str_replace('_', ' ', $conversation->failure_kind) }}</x-badge>
        @endif
        @if ($conversation->outcomeOverrider)
            <span class="text-sm text-stone-600">Overridden by {{ $conversation->outcomeOverrider->name }}</span>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card>
                <h2 class="text-lg font-semibold">Transcript</h2>
                @if ($conversation->channel === 'voice')
                    <p class="mt-1 text-xs text-stone-600">Voice transcripts are reported by the customer's browser and may contain speech-recognition errors.</p>
                @endif
                <ol class="mt-3 space-y-3">
                    @forelse ($conversation->messages as $message)
                        <li @class([
                            'rounded-lg px-3 py-2 text-sm',
                            'bg-emerald-50' => $message->role === 'user',
                            'bg-stone-100' => $message->role === 'assistant',
                            'border border-dashed border-stone-300 bg-white' => $message->role === 'tool',
                        ])>
                            <p class="mb-1 flex flex-wrap items-center gap-2 text-xs text-stone-600">
                                <span class="font-semibold uppercase">{{ $message->role === 'user' ? 'Customer' : ($message->role === 'tool' ? 'Tool' : 'Assistant') }}</span>
                                <span>{{ $message->created_at?->format('H:i:s') }}</span>
                                @if ($message->source === 'client') <x-badge>client-reported</x-badge> @endif
                                @if ($message->status === 'error') <x-badge variant="danger">error{{ $message->error_code ? ': '.$message->error_code : '' }}</x-badge> @endif
                                @if ($message->latency_ms) <span>{{ number_format($message->latency_ms) }} ms</span> @endif
                                @if ($message->input_tokens || $message->output_tokens) <span>{{ $message->input_tokens }} in / {{ $message->output_tokens }} out tokens</span> @endif
                            </p>
                            @if ($message->role === 'tool')
                                <details>
                                    <summary class="cursor-pointer font-mono text-xs break-all">{{ $message->tool_name }}({{ json_encode($message->tool_args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }})</summary>
                                    <pre class="mt-2 max-h-64 overflow-auto rounded bg-stone-50 p-2 text-xs whitespace-pre-wrap break-words">{{ json_encode($message->tool_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <p class="whitespace-pre-wrap break-words">{{ $message->content }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-stone-600">No messages.</li>
                    @endforelse
                </ol>
            </x-card>
        </div>

        <div class="min-w-0 space-y-4">
            <x-card>
                <h2 class="text-lg font-semibold">Details</h2>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    <dt class="text-stone-600">Channel</dt><dd>{{ $conversation->channel }}{{ $conversation->voice_mode ? ' ('.str_replace('_', '-', $conversation->voice_mode).')' : '' }}</dd>
                    <dt class="text-stone-600">Device</dt><dd>{{ $conversation->device_class }}</dd>
                    <dt class="text-stone-600">Status</dt><dd>{{ $conversation->status }}{{ $conversation->ended_reason ? ' ('.str_replace('_', ' ', $conversation->ended_reason).')' : '' }}</dd>
                    <dt class="text-stone-600">Started</dt><dd>{{ $conversation->started_at->format('j M Y, H:i') }}</dd>
                    @if ($conversation->duration_seconds !== null)
                        <dt class="text-stone-600">Duration</dt><dd>{{ gmdate('i:s', $conversation->duration_seconds) }}</dd>
                    @endif
                    <dt class="text-stone-600">Tokens</dt><dd>{{ number_format($conversation->input_tokens) }} in / {{ number_format($conversation->output_tokens) }} out</dd>
                    <dt class="text-stone-600">AI config</dt><dd>v{{ $conversation->aiConfigVersion->version }}</dd>
                    <dt class="text-stone-600">Analysis</dt><dd>{{ $conversation->analysis_status }}</dd>
                </dl>
                @if ($conversation->analysis_error)
                    <p class="mt-2 text-xs text-red-800">{{ $conversation->analysis_error }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @unless ($conversation->isActive())
                        <form method="POST" action="{{ route('admin.conversations.analyze', $conversation) }}">
                            @csrf
                            <x-button variant="secondary" type="submit">Re-run analysis</x-button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ route('admin.conversations.destroy', $conversation) }}" data-confirm="Delete this conversation and everything recorded about it?">
                        @csrf
                        @method('DELETE')
                        <x-button variant="secondary" type="submit">Delete</x-button>
                    </form>
                </div>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold">Override outcome</h2>
                <form method="POST" action="{{ route('admin.conversations.outcome', $conversation) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('PATCH')
                    <x-select name="outcome" label="Outcome" :value="$conversation->outcome_overridden_by ? $conversation->outcome : 'automatic'" :options="['automatic' => 'Automatic (from analysis)', 'success' => 'Success', 'failed' => 'Failed']" />
                    <x-button type="submit">Save outcome</x-button>
                </form>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold">Questions</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($conversation->questions as $question)
                        <li>
                            <p>{{ $question->question }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-stone-600">
                                <x-badge :variant="match ($question->outcome) { 'answered' => 'success', 'not_answered' => 'danger', default => 'neutral' }">{{ str_replace('_', ' ', $question->outcome) }}</x-badge>
                                <span>{{ $question->topic }}</span>
                                <span>source: {{ $question->source }}</span>
                                <span>{{ $question->detected_by === 'model_report' ? 'reported live by the assistant' : 'classifier' }}</span>
                                @if ($question->resolved_at) <x-badge variant="success">resolved</x-badge> @endif
                            </p>
                            <p class="mt-1 text-xs text-stone-600">{{ $question->reason }}</p>
                        </li>
                    @empty
                        <li class="text-stone-600">{{ $conversation->analysis_status === 'done' ? 'No customer questions.' : 'Not analysed yet.' }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold">Data points</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @forelse ($conversation->dataPoints as $point)
                        <div>
                            <dt class="text-stone-600">{{ $point->definition->label }}</dt>
                            <dd class="font-semibold">{{ $point->displayValue() }}</dd>
                            @if ($point->evidence) <dd class="text-xs text-stone-600">“{{ $point->evidence }}”</dd> @endif
                        </div>
                    @empty
                        <p class="text-stone-600">None extracted.</p>
                    @endforelse
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold">Evaluations</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($conversation->evaluations as $evaluation)
                        <li>
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $evaluation->criterion->name }}</span>
                                @if ($evaluation->passed !== null)
                                    <x-badge :variant="$evaluation->passed ? 'success' : 'danger'">{{ $evaluation->passed ? 'pass' : 'fail' }}</x-badge>
                                @else
                                    <x-badge>{{ $evaluation->score }}/5</x-badge>
                                @endif
                            </p>
                            <p class="text-xs text-stone-600">{{ $evaluation->rationale }}</p>
                        </li>
                    @empty
                        <li class="text-stone-600">None.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
