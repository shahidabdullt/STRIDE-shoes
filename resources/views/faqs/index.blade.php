<x-layouts.app title="Help">
    <h1 class="text-2xl font-bold tracking-tight">Help</h1>

    @foreach ($faqsByTopic as $topic => $faqs)
        <section class="mt-6" aria-labelledby="topic-{{ \Illuminate\Support\Str::slug($topic) }}">
            <h2 id="topic-{{ \Illuminate\Support\Str::slug($topic) }}" class="text-lg font-semibold">{{ \Illuminate\Support\Str::headline($topic) }}</h2>
            <div class="mt-2 divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
                @foreach ($faqs as $faq)
                    <details class="group p-4">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-medium">
                            {{ $faq->question }}
                            <span aria-hidden="true" class="text-stone-500 group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-2 text-stone-700">{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($articles->isNotEmpty())
        <section class="mt-10" aria-labelledby="guides-heading">
            <h2 id="guides-heading" class="text-lg font-semibold">Guides</h2>
            <div class="mt-2 space-y-3">
                @foreach ($articles as $article)
                    <x-card>
                        <h3 class="font-semibold">{{ $article->title }}</h3>
                        <p class="mt-1 text-stone-700">{{ $article->body }}</p>
                    </x-card>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
