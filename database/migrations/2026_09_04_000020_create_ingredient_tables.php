<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The reusable side of buying: an ingredient is entered once and then
        // picked from the list on every later purchase, carrying its last paid
        // price forward as the default.
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('unit', 20)->default('pc');
            $table->string('category', 60)->default('ingredient');
            $table->string('supplier')->nullable();
            $table->decimal('last_price', 12, 2)->default(0);
            $table->date('last_purchased_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('category');
        });

        // Line items turn a purchase into "what we actually bought", so the
        // ledger total stays the sum of real ingredients rather than a typed figure.
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('unit', 20)->default('pc');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        // A price the buyer corrects before saving is a real signal about cost
        // drift, so every change is kept instead of overwriting silently.
        Schema::create('ingredient_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('old_price', 12, 2)->default(0);
            $table->decimal('new_price', 12, 2)->default(0);
            $table->date('changed_on');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ingredient_id', 'changed_on']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('notes', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::dropIfExists('ingredient_price_changes');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('ingredients');
    }
};
