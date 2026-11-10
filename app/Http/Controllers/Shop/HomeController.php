<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'featured' => Product::active()->where('is_featured', true)->with('category')->orderBy('id')->limit(8)->get(),
            'categories' => Category::orderBy('sort')->get(),
        ]);
    }
}
