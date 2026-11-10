<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.products.index', [
            'search' => $search,
            'products' => Product::query()
                ->with('category')->withSum('variants as stock_total', 'stock')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('category_id')->orderBy('name')
                ->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true, 'is_featured' => false, 'attributes' => []]),
            'categories' => Category::orderBy('sort')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($request->productData());
            $this->syncVariants($product, $request->variantRows());

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('variants'),
            'categories' => Category::orderBy('sort')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->update($request->productData());
            $this->syncVariants($product, $request->variantRows());
        });

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $variantIds = $product->variants()->pluck('id');

        // Order history keeps its snapshot, but a product that has been sold is deactivated rather than deleted.
        if (OrderItem::whereIn('product_variant_id', $variantIds)->exists()) {
            return back()->with('error', 'This product has been ordered, so it cannot be deleted. Untick "Active" to hide it instead.');
        }

        DB::transaction(function () use ($product, $variantIds): void {
            CartItem::whereIn('product_variant_id', $variantIds)->delete();
            $product->delete();
        });

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    /**
     * Updates existing sizes and adds new ones. Sizes are never deleted here because carts and orders point at them;
     * to retire a size set its stock to 0.
     *
     * @param  array<int, array{id: int|null, size: float, sku: string, stock: int}>  $rows
     */
    private function syncVariants(Product $product, array $rows): void
    {
        foreach ($rows as $row) {
            $values = ['size' => $row['size'], 'sku' => $row['sku'], 'stock' => $row['stock']];

            if ($row['id'] !== null) {
                $product->variants()->whereKey($row['id'])->firstOrFail()->update($values);
            } else {
                $product->variants()->create($values);
            }
        }
    }
}
