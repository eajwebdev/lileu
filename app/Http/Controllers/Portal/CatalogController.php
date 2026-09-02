<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        $reseller = $request->user()->reseller;

        $curated = $reseller->products()
            ->wherePivot('is_approved', true)
            ->where('products.is_active', true)
            ->get();

        $products = $curated->isNotEmpty()
            ? $curated
            : Product::active()->where('available_to_resellers', true)->get();

        return Inertia::render('Portal/Catalog', [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'description' => $p->description,
                'image_url' => $p->image_url,
                'unit_price' => $this->orders->priceFor($reseller, $p),
                'retail_price' => (float) $p->retail_price,
                'margin' => round((float) $p->retail_price - $this->orders->priceFor($reseller, $p), 2),
                'min_qty' => $p->min_reseller_qty,
                'in_stock' => $p->isAvailable(),
                'made_to_order' => ! $p->tracksStock(),
            ])->values(),
            'isCurated' => $curated->isNotEmpty(),
        ]);
    }
}
