<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('tracks_stock')->default(true)->after('stock');
        });

        Schema::table('consignment_items', function (Blueprint $table) {
            // Snapshot the inventory mode used when the goods were issued.
            $table->boolean('tracks_stock')->default(true)->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_items', function (Blueprint $table) {
            $table->dropColumn('tracks_stock');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tracks_stock');
        });
    }
};
