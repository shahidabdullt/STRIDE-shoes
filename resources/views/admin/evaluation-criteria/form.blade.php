@php $editing = $criterion->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit criterion' : 'New criterion'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit criterion' : 'New criterion' }}</h1>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $editing ? route('admin.evaluation-criteria.update', $criterion) : route('admin.evaluation-criteria.store') }}" class="space-y-4">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-input name="name" label="Name" :value="$criterion->name" required />
            <x-textarea name="rubric" label="Rubric" :value="$criterion->rubric" rows="4" hint="What passes, what fails, or what a 5 looks like." required />
            <x-select name="scale" label="Scale" :value="$criterion->scale" :options="['pass_fail' => 'Pass / fail', 'score_1_5' => 'Score 1 to 5']" />
            <x-select name="applies_to" label="Applies to" :value="$criterion->applies_to" :options="['all' => 'Text and voice', 'text' => 'Text only', 'voice' => 'Voice only']" />
            <x-input name="sort" label="Sort order" type="number" min="0" :value="$criterion->sort" />
            <x-checkbox name="is_active" label="Active (applied to new conversations)" :checked="$criterion->is_active" />
            <div class="flex gap-2">
                <x-button type="submit">Save</x-button>
                <x-button variant="secondary" :href="route('admin.evaluation-criteria.index')">Cancel</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
