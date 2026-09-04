<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog;
use App\Models\Reseller;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->active()
            ->with(['category:id,name,slug,accent', 'variants'])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return Inertia::render('Site/Landing', [
            'featured' => $products->map(fn (Product $p) => $this->card($p)),
            // Whatever the owner sets up in Admin → Products → Categories shows
            // up here, with a live count and a taste of what is inside.
            'categories' => Category::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->with(['products' => fn ($q) => $q->where('is_active', true)->with('variants')
                    ->orderByDesc('is_featured')
                    ->limit(3)])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'description' => $c->description,
                    'accent' => $c->accent,
                    'products_count' => $c->products_count,
                    'from_price' => (float) ($c->products->min(fn (Product $p) => $p->lowestRetailPrice()) ?? 0),
                    'preview' => $c->products->map(fn (Product $p) => [
                        'name' => $p->name,
                        'image_url' => $p->image_url,
                    ])->values(),
                ]),
            'stats' => [
                'products' => Product::active()->count(),
                'resellers' => Reseller::approved()->count(),
                'since' => Settings::get('business_tagline', config('lileu.business.tagline')),
            ],
        ]);
    }

    /** A product card, priced from its cheapest flavour when it has any. */
    private function card(Product $product): array
    {
        return Catalog::grouped([$product])[0];
    }
}
