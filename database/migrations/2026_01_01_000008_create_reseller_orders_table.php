<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique(); // RE-2026-000012
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // pending|confirmed|preparing|ready|completed|cancelled
            $table->string('payment_status', 30)->default('unpaid'); // unpaid|partially_paid|downpayment_paid|fully_paid|refunded|void
            $table->string('fulfillment_type', 20)->default('pickup'); // pickup|delivery
            $table->text('delivery_address')->nullable();
            $table->date('date_needed')->nullable();
            $table->time('time_needed')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedTinyInteger('downpayment_percent')->default(50);
            $table->decimal('downpayment_required', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_orders');
    }
};
