<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            // Not every seller applies through the public form. A student taking
            // a tray to school is the same kind of party record, just added by
            // the admin and paid on consignment instead of up front.
            $table->string('engagement', 20)->default('reseller')->after('status');
            $table->index('engagement');
        });

        // Consignment sellers often have no email address at all.
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropIndex(['engagement']);
            $table->dropColumn('engagement');
        });
    }
};
