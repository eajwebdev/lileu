<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('incurred_on');
            $table->string('category', 60)->default('operations');
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('incurred_on');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchased_on');
            $table->string('supplier')->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('purchased_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('expenses');
    }
};
