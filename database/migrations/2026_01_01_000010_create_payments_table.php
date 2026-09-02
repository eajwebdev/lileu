<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A payment IS the receipt. One order can carry several (downpayment,
        // balance), each with its own LE- receipt number.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique(); // LE-20260902-001301
            $table->foreignId('reseller_order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('downpayment'); // downpayment|balance|full|adjustment
            $table->string('method', 30)->default('qrph'); // qrph|cash|bank_transfer|gcash|manual
            $table->string('status', 20)->default('pending'); // pending|paid|failed|cancelled|refunded|void
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('reference', 80)->nullable();
            $table->string('paymongo_source_id', 80)->nullable();
            $table->string('paymongo_payment_id', 80)->nullable();
            $table->text('qr_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
