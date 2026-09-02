<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin curates which products each reseller may order, optionally at a
        // negotiated price that overrides the product's standard reseller price.
        Schema::create('product_reseller', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->decimal('custom_price', 12, 2)->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'reseller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reseller');
    }
};
