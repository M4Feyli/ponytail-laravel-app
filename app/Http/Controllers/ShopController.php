<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class ShopController extends Controller
{
    public function landing(): View
    {
        $categories = Category::withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        $productCount = Product::where('is_active', true)->count();

        return view('shop.landing', [
            'categories' => $categories,
            'productCount' => $productCount,
        ]);
    }

    public function category(Category $category): View
    {
        $products = $category->products()
            ->where('is_active', true)
            ->with('variants')
            ->latest()
            ->get();

        // Small, category-scoped JSON for the client-side quick-view modal
        // and sort control -- never the whole catalog, just what's on
        // screen (matches the old design's product-card + modal pattern,
        // just fed from the database instead of a hardcoded JS array).
        $productsJson = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'cat' => $category->name,
            'condition' => $p->condition,
            'price' => $p->isOnSale() ? $p->sale_price : $p->price,
            'old' => $p->isOnSale() ? $p->price : null,
            'img' => $p->imageUrl(),
            'desc' => $p->description,
            'specs' => $p->specs ?? [],
            'isNew' => $p->is_new,
            'sizes' => $p->variants->map(fn ($v) => [
                'size' => $v->size,
                'stock' => $v->stock,
            ])->values(),
        ])->values();

        return view('shop.category', [
            'category' => $category,
            'products' => $products,
            'productsJson' => $productsJson,
        ]);
    }

    public function cart(): View
    {
        return view('shop.cart');
    }
}
