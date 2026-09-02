<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consignment: stock is handed to a seller without payment up front. It
     * leaves our shelf but is still ours until it sells. Later — same day or
     * the next — we collect the cash for what sold and take back what did not,
     * sorting it into good stock, expired, damaged or missing.
     */
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_number', 40)->unique(); // CN-2026-000001
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('open'); // open|settled|cancelled
            $table->date('issued_on');
            $table->date('due_on')->nullable();            // when we expect to collect
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            // Running tallies, derived from settlement lines.
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->unsignedInteger('quantity_expired')->default(0);
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->unsignedInteger('quantity_missing')->default(0);

            $table->decimal('issued_value', 14, 2)->default(0);   // qty out × unit price
            $table->decimal('sold_value', 14, 2)->default(0);     // what the seller owes us
            $table->decimal('loss_value', 14, 2)->default(0);     // expired + damaged + missing, at cost
            $table->decimal('amount_collected', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);     // sold_value − collected

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'issued_on']);
        });

        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name'); // snapshot, so history never drifts
            $table->string('sku', 60)->nullable();

            $table->decimal('unit_price', 12, 2)->default(0);   // what the seller remits per unit
            $table->decimal('retail_price', 12, 2)->default(0); // suggested selling price
            $table->decimal('cost_price', 12, 2)->default(0);   // for valuing losses

            $table->unsignedInteger('quantity_issued')->default(0);
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_returned')->default(0);
            $table->unsignedInteger('quantity_expired')->default(0);
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->unsignedInteger('quantity_missing')->default(0);

            $table->decimal('sold_value', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
    }
};
