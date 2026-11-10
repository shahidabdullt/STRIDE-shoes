@php $editing = $faq->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit FAQ' : 'New FAQ'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit FAQ' : 'New FAQ' }}</h1>

    @if (! empty($gap))
        <p class="mb-4 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            From a knowledge gap: a customer asked this and the assistant could not answer. Write the answer below;
            saving marks the gap as resolved.
        </p>
    @endif

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            @if (! empty($gap)) <input type="hidden" name="from_gap" value="{{ $gap->id }}"> @endif
            <x-input name="question" label="Question" :value="$faq->question" required />
            <x-textarea name="answer" label="Answer" :value="$faq->answer" rows="6" required />
            <x-input name="topic" label="Topic (for example shipping, returns, sizing)" :value="$faq->topic" required />
            <x-input name="sort" label="Sort order" type="number" min="0" :value="$faq->sort" />
            <x-checkbox name="is_published" label="Published (visible on the Help page and to the assistant)" :checked="$faq->is_published" />
            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('admin.faqs.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
