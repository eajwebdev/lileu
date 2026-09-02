<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Keep existing installations aligned with the configured business address. */
    public function up(): void
    {
        $query = DB::table('settings')->where('key', 'business_address');

        if ($query->exists()) {
            $query->update([
                'value' => 'Dahile, Mabinay, Negros Oriental',
                'group' => 'branding',
                'type' => 'string',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => 'business_address',
                'value' => 'Dahile, Mabinay, Negros Oriental',
                'group' => 'branding',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Settings::flush();
    }

    /**
     * This data migration is intentionally irreversible: rolling back code
     * must not silently restore an address that may no longer be correct.
     */
    public function down(): void
    {
        // No-op.
    }
};
