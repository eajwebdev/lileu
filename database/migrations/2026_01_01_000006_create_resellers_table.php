<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('email');
            $table->string('phone', 40);
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('facebook')->nullable();
            $table->text('why_reseller')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|suspended
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedTinyInteger('downpayment_percent')->default(50);
            $table->decimal('lifetime_value', 14, 2)->default(0);
            $table->text('admin_notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resellers');
    }
};
