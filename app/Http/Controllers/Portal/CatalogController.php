<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderService;
use App\Support\Catalog;
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
            ->with(['category:id,name,slug,accent', 'variants'])
            ->get();

        $products = $curated->isNotEmpty()
            ? $curated
            : Product::active()
                ->where('available_to_resellers', true)
                ->with(['category:id,name,slug,accent', 'variants'])
                ->get();

        return Inertia::render('Portal/Catalog', [
            // A price list is read flavour by flavour, since that is what the
            // seller actually buys and marks up.
            'products' => collect(Catalog::sellables($products))
                ->map(function (array $row) use ($reseller) {
                    $unit = $this->orders->priceFor(
                        $reseller,
                        $row['variant_id']
                            ? ProductVariant::find($row['variant_id'])
                            : Product::find($row['product_id']),
                    );

                    $row['unit_price'] = $unit;
                    $row['margin'] = round($row['retail_price'] - $unit, 2);
                    $row['min_qty'] = $row['min_reseller_qty'];
                    $row['in_stock'] = $row['is_available'] && (! $row['tracks_stock'] || $row['stock'] > 0);
                    $row['manually_unavailable'] = ! $row['is_available'];
                    $row['made_to_order'] = ! $row['tracks_stock'];

                    return $row;
                })
                ->values(),
            'isCurated' => $curated->isNotEmpty(),
        ]);
    }
}
