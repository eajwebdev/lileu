<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A flavour of a product. Variants are optional: a product with none
        // keeps its own price and stock and sells exactly as before, while a
        // product with variants carries neither — each flavour holds its own.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku', 60)->unique();
            $table->string('description', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('retail_price', 12, 2)->default(0);
            $table->decimal('reseller_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('tracks_stock')->default(true);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'slug']);
        });

        // Every line that can be sold records which flavour left the shelf.
        // Nullable throughout: lines for products without variants have none,
        // and the name snapshot keeps history readable if a variant is removed.
        foreach ([
            'reseller_order_items',
            'pos_sale_items',
            'consignment_items',
            'consignment_settlement_items',
        ] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('product_variant_id')->nullable()->after('product_id')
                    ->constrained('product_variants')->nullOnDelete();
                $t->string('variant_name')->nullable()->after('product_name');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'reseller_order_items',
            'pos_sale_items',
            'consignment_items',
            'consignment_settlement_items',
        ] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('product_variant_id');
                $t->dropColumn('variant_name');
            });
        }

        Schema::dropIfExists('product_variants');
    }
};
