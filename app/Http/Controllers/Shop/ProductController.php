<?php

namespace App\Http\Controllers\Shop;

use App\Actions\Catalog\SearchProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ProductIndexRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, SearchProducts $search): View
    {
        $filters = $request->validated();

        return view('products.index', [
            'products' => $search($filters)->paginate(12)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::orderBy('sort')->get(),
            'sizes' => range(6, 13, 0.5),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load(['category', 'variants', 'images']);

        return view('products.show', [
            'product' => $product,
            'related' => Product::active()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->orderByDesc('is_featured')->orderBy('id')
                ->limit(4)->get(),
        ]);
    }
}
