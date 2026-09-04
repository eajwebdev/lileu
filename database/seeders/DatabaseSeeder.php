<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
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
        $this->seedProducts($this->seedCategories());
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

    private function seedCategories(): array
    {
        // Edit these in Admin -> Products -> Categories; the landing page follows.
        $rows = [
            ['name' => 'Graham', 'accent' => 'caramel', 'description' => 'Graham nests and layered graham cups.'],
            ['name' => 'Cookies', 'accent' => 'chocolate', 'description' => 'Soft-baked cookies and crinkles.'],
            ['name' => 'Munchkin', 'accent' => 'blush', 'description' => 'Bite-sized glazed doughnut bites.'],
            ['name' => 'Cream Cups', 'accent' => 'blush', 'description' => 'Silky no-bake cream desserts.'],
            ['name' => 'Seasonal', 'accent' => 'cherry', 'description' => 'Limited runs and holiday flavours.'],
        ];

        $out = [];
        foreach ($rows as $i => $row) {
            $out[$row['name']] = Category::updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                $row + ['sort_order' => $i, 'is_active' => true],
            );
        }

        return $out;
    }

    private function seedProducts(array $categories): array
    {
        $rows = [
            ['Graham Nest', 'Graham', 'GN-01', 12, 9, 5.5, 'Buttery graham layers, sweet cream and a cherry crown.', true],
            ['Cloudy Classic', 'Cream Cups', 'CC-CL', 13, 10, 6, 'Airy whipped cream over a soft biscuit floor.', true],
            ['Misty Green', 'Cream Cups', 'CC-MG', 16, 12, 7.5, 'Matcha cream settled over a soft biscuit floor.', true],
            ['Cocoa Cascade', 'Cream Cups', 'CC-CO', 13, 10, 6, 'Dark cocoa pudding with a cascading ganache top.', true],
        ];

        $out = [];
        foreach ($rows as $i => [$name, $cat, $sku, $retail, $wholesale, $cost, $desc, $featured]) {
            $out[$sku] = Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$cat]->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
                    'retail_price' => $retail,
                    'reseller_price' => $wholesale,
                    'cost_price' => $cost,
                    'stock' => 100,
                    'low_stock_threshold' => 20,
                    'min_reseller_qty' => 10,
                    'is_active' => true,
                    'is_featured' => $featured,
                    'available_to_resellers' => true,
                    'sort_order' => $i,
                ],
            );
        }

        return $out;
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
