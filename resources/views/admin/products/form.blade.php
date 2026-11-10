@php
    use App\Support\Money;

    $editing = $product->exists;
    $attrs = $product->attributes ?? [];

    $rows = old('variants', $product->variants->map(fn ($v) => [
        'id' => $v->id, 'size' => Money::size($v->size), 'sku' => $v->sku, 'stock' => $v->stock,
    ])->all());
    // A few empty rows are always offered for adding new sizes; blank rows are ignored on save.
    $rows = array_values($rows);
    $blank = $editing ? 3 : 8;
    for ($i = 0; $i < $blank; $i++) {
        $rows[] = ['id' => '', 'size' => '', 'sku' => '', 'stock' => ''];
    }

    $cell = 'block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 text-base focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700';
@endphp
<x-layouts.app :title="$editing ? 'Edit product' : 'New product'">
    <x-admin.nav />
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $editing ? 'Edit '.$product->name : 'New product' }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="grid max-w-4xl gap-4">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card class="space-y-4">
            <h2 class="text-lg font-semibold">Details</h2>
            <x-input name="name" label="Name" :value="$product->name" required />
            <x-input name="slug" label="URL slug (leave blank to generate from name and colour)" :value="$product->slug" />
            <x-input name="short_description" label="Short description" :value="$product->short_description" required />
            <x-textarea name="description" label="Description" :value="$product->description" rows="5" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="category_id" label="Category" :options="$categories" :value="$product->category_id" />
                <x-input name="price" label="Price ({{ config('store.currency') }})" type="number" step="0.01" min="1" :value="$product->price_cents ? number_format($product->price_cents / 100, 2, '.', '') : ''" required />
                <x-select name="audience" label="For" :options="['men' => 'Men', 'women' => 'Women', 'unisex' => 'Unisex']" :value="$product->audience" />
                <x-select name="activity" label="Activity" :options="['running' => 'Running', 'trail' => 'Trail', 'walking' => 'Walking', 'casual' => 'Casual', 'training' => 'Training']" :value="$product->activity" />
                <x-input name="color" label="Colour" :value="$product->color" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-checkbox name="is_active" label="Active (visible in the shop)" :checked="$product->is_active" />
                <x-checkbox name="is_featured" label="Featured on the home page" :checked="$product->is_featured" />
            </div>
        </x-card>

        <x-card class="space-y-4">
            <h2 class="text-lg font-semibold">Specifications</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="material" label="Material" :value="$attrs['material'] ?? ''" />
                <x-input name="width" label="Width (for example standard, wide)" :value="$attrs['width'] ?? ''" />
                <x-input name="weight_g" label="Weight (g)" type="number" min="0" :value="$attrs['weight_g'] ?? ''" />
                <x-input name="drop_mm" label="Heel drop (mm)" type="number" min="0" :value="$attrs['drop_mm'] ?? ''" />
            </div>
            <x-checkbox name="waterproof" label="Waterproof" :checked="$attrs['waterproof'] ?? false" />
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Sizes and stock</h2>
            <p class="mt-1 text-sm text-stone-700">Sizes go in half steps. Sizes can't be removed once added (carts and orders refer to them): set the stock to 0 to stop selling one. Fill an empty row to add a size.</p>
            @error('variants')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror

            <div class="mt-3 space-y-3">
                @foreach ($rows as $i => $row)
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                        <div>
                            <label for="variants-{{ $i }}-size" class="mb-1 block text-xs font-medium text-stone-700">Size (US)</label>
                            <input id="variants-{{ $i }}-size" name="variants[{{ $i }}][size]" type="number" step="0.5" min="3" max="15" value="{{ $row['size'] ?? '' }}" class="{{ $cell }}">
                            @error("variants.$i.size")<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="variants-{{ $i }}-sku" class="mb-1 block text-xs font-medium text-stone-700">SKU</label>
                            <input id="variants-{{ $i }}-sku" name="variants[{{ $i }}][sku]" type="text" maxlength="50" value="{{ $row['sku'] ?? '' }}" class="{{ $cell }}">
                            @error("variants.$i.sku")<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="variants-{{ $i }}-stock" class="mb-1 block text-xs font-medium text-stone-700">Stock</label>
                            <input id="variants-{{ $i }}-stock" name="variants[{{ $i }}][stock]" type="number" min="0" value="{{ $row['stock'] ?? '' }}" class="{{ $cell }}">
                            @error("variants.$i.stock")<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            @error("variants.$i.id")<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button type="submit">Save product</x-button>
            <x-button variant="secondary" :href="route('admin.products.index')">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
