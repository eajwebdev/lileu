<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $term = $request->string('q')->toString();

        $products = Product::query()
            ->active()
            ->with(['category:id,name,slug,accent', 'variants'])
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $slug),
            ))
            // A shopper searching "cloudy" is naming a flavour, not a product,
            // so the flavours are searched alongside the names above them.
            ->when($term, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$t}%")
                ->orWhereHas('variants', fn ($v) => $v->where('name', 'like', "%{$t}%"))))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Site/Catalog', [
            // The menu lists what can be bought, so a product sold by flavour
            // contributes a card per flavour rather than one card hiding them.
            'items' => $this->matching(Catalog::sellables($products), $term),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'accent']),
            'filters' => $request->only(['category', 'q']),
        ]);
    }

    /**
     * Narrow the flavours to the search itself.
     *
     * The query above keeps a whole product when any of its flavours matches,
     * which is right for "graham" and wrong for "cloudy" — that should leave
     * one card on the screen, not three. The label carries both names.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function matching(array $items, string $term): array
    {
        if ($term === '') {
            return $items;
        }

        return collect($items)
            ->filter(fn (array $item) => str_contains(mb_strtolower($item['label']), mb_strtolower($term)))
            ->values()
            ->all();
    }

    public function show(Product $product): Response
    {
        abort_unless($product->is_active, 404);

        $product->load(['category:id,name,slug,accent', 'variants']);

        return Inertia::render('Site/Product', [
            'product' => Catalog::grouped([$product])[0] + [
                'min_reseller_qty' => $product->min_reseller_qty,
            ],
            'related' => Product::active()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->with('variants')
                ->limit(4)
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'image_url' => $p->image_url,
                    'retail_price' => $p->lowestRetailPrice(),
                ]),
        ]);
    }
}
