<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * What the shop starts with: its branding, the menu, and the people who run
 * it. Selling, buying and spending are all recorded in the app itself, so
 * nothing here invents history.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedCategories();
        $this->call(ProductSeeder::class);
        $this->seedStaff();
    }

    private function seedSettings(): void
    {
        Settings::putMany([
            'business_name' => "Lil'Eu Sweets",
            'business_tagline' => 'Est. 2026',
            'business_phone' => '0917 000 0000',
            'business_email' => 'hello@lileu.test',
            'business_address' => 'Dahile, Mabinay, Negros Oriental',
            'business_facebook' => 'facebook.com/lileucafe',
            'business_website' => 'lileu.test',
            'receipt_prefix' => 'LE',
            'order_prefix' => 'RE',
            'receipt_show_logo' => true,
            'receipt_footer' => "Thank you for growing with Lil'Eu. 💗",
            'receipt_paper' => 'a4',
            'default_downpayment_percent' => 50,
        ], 'branding');
    }

    private function seedCategories(): void
    {
        // Edit these in Admin -> Products -> Categories; the landing page follows.
        $rows = [
            ['name' => 'Graham', 'accent' => 'caramel', 'description' => 'Graham nests and layered graham cups.'],
            ['name' => 'Cookies', 'accent' => 'chocolate', 'description' => 'Soft-baked cookies and crinkles.'],
            ['name' => 'Munchkin', 'accent' => 'blush', 'description' => 'Bite-sized glazed doughnut bites.'],
            ['name' => 'Cream Cups', 'accent' => 'blush', 'description' => 'Silky no-bake cream desserts.'],
            ['name' => 'Seasonal', 'accent' => 'cherry', 'description' => 'Limited runs and holiday flavours.'],
        ];

        foreach ($rows as $i => $row) {
            Category::updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                $row + ['sort_order' => $i, 'is_active' => true],
            );
        }
    }

    private function seedStaff(): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@lileu.test'],
            [
                'name' => "Lil'Eu Owner",
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'phone' => '0917 000 0001',
                'email_verified_at' => now(),
            ],
        );

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@lileu.test'],
            [
                'name' => 'Front Counter',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CASHIER,
                'phone' => '0917 000 0002',
                'email_verified_at' => now(),
            ],
        );

        return [$admin, $cashier];
    }
}
