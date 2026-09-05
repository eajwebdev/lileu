<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Reseller;
use App\Support\Catalog;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
    {
        // One pass over the catalog the admin actually maintains. Everything on
        // the page — the shelves, the cards, the counts — is derived from it, so
        // a flavour added in Admin → Products shows up here without a second
        // list to keep in sync.
        $products = Catalog::activeProducts();

        $sellable = $products->filter(fn (Product $p) => $this->flavourCount($p) > 0);

        $featured = $sellable
            ->sortByDesc('is_featured')
            ->take(8)
            ->values();

        return Inertia::render('Site/Landing', [
            'featured' => Catalog::grouped($featured),
            // Whatever the owner sets up in Admin → Products → Categories shows
            // up here, with a live count and a taste of what is inside.
            'categories' => $this->shelves($sellable),
            'stats' => [
                'products' => $sellable->count(),
                // A product sold by flavour is several things on the counter, so
                // the headline number counts flavours, not product rows.
                'flavours' => $sellable->sum(fn (Product $p) => $this->flavourCount($p)),
                'resellers' => Reseller::approved()->count(),
                'since' => Settings::get('business_tagline', config('lileu.business.tagline')),
            ],
        ]);
    }

    /**
     * The shelves, priced and counted from every product on them — not just the
     * few whose photos make the preview.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function shelves(Collection $products): array
    {
        $byCategory = $products->groupBy('category_id');

        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $c) use ($byCategory) {
                $shelf = $byCategory->get($c->id, collect());

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'description' => $c->description,
                    'accent' => $c->accent,
                    'products_count' => $shelf->count(),
                    'flavours_count' => $shelf->sum(fn (Product $p) => $this->flavourCount($p)),
                    'from_price' => (float) ($shelf->min(fn (Product $p) => $p->lowestRetailPrice()) ?? 0),
                    'preview' => $shelf
                        ->sortByDesc('is_featured')
                        ->take(3)
                        ->map(fn (Product $p) => [
                            'name' => $p->name,
                            'image_url' => $this->face($p),
                        ])
                        ->values(),
                ];
            })
            ->all();
    }

    /**
     * The picture that best stands for a product: its own, or failing that the
     * first flavour that brought one of its own.
     */
    private function face(Product $product): ?string
    {
        if ($product->image_url) {
            return $product->image_url;
        }

        return $product->sellableVariants()
            ->first(fn (ProductVariant $v) => filled($v->image_path))?->image_url;
    }

    /** How many things this product actually puts on the counter. */
    private function flavourCount(Product $product): int
    {
        return $product->hasVariants()
            ? $product->sellableVariants()->count()
            : 1;
    }
}
