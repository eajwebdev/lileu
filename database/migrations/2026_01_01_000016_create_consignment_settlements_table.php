<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One collection event. A consignment can have several — the owner often
     * collects the day's takings each evening and only closes the batch when
     * every unit is accounted for.
     *
     * Settlement receipts draw from the same LE- series as reseller receipts so
     * the business keeps one continuous receipt book.
     */
    public function up(): void
    {
        Schema::create('consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique(); // LE-20260902-000042
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->date('settled_on');
            $table->decimal('sold_value', 14, 2)->default(0);      // owed for this collection
            $table->decimal('amount_collected', 14, 2)->default(0); // cash actually handed over
            $table->string('method', 30)->default('cash');          // cash|gcash|qrph|bank_transfer
            $table->string('reference', 80)->nullable();
            $table->boolean('is_final')->default(false);            // closes the consignment
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('settled_on');
        });

        Schema::create('consignment_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');

            // How this collection accounts for units that went out.
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0); // good — back on the shelf
            $table->unsignedInteger('quantity_expired')->default(0);  // write-off
            $table->unsignedInteger('quantity_damaged')->default(0);  // write-off
            $table->unsignedInteger('quantity_missing')->default(0);  // unaccounted for

            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('sold_value', 14, 2)->default(0);
            $table->decimal('loss_value', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_settlement_items');
        Schema::dropIfExists('consignment_settlements');
    }
};
