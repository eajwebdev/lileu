<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (series, period) so receipt/order counters reset per day or year
        // without ever colliding — the row is locked while a number is drawn.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('series', 40);
            $table->string('period', 20)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
            $table->unique(['series', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
