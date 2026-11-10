@if (session('status'))
    <div role="status" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        {{ session('error') }}
    </div>
@endif
