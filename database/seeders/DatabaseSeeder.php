<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Message;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Reseller;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\OrderService;
use App\Services\ConsignmentService;
use App\Services\PaymentService;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories);
        [$admin, $cashier] = $this->seedStaff();
        $resellers = $this->seedResellers($products);
        $this->seedOrders($resellers, $products, $admin);
        $this->seedConsignments($products, $admin);
        $this->seedLedger($admin);
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
            ['Graham Nest Classic', 'Graham', 'GN-CL', 12, 9, 5.5, 'Buttery graham layers, sweet cream and a cherry crown.', true],
            ['Graham Nest Mango', 'Graham', 'GN-MG', 14, 10.5, 6.5, 'Ripe mango folded through our signature graham base.', true],
            ['Graham Nest Ube', 'Graham', 'GN-UB', 14, 10.5, 6.5, 'House-cooked ube halaya over toasted graham crumble.', false],
            ['Chocolate Chip Cookies', 'Cookies', 'CK-CC', 15, 11, 6.5, 'Soft-baked and loaded with dark chocolate chunks.', true],
            ['Chocolate Crinkles', 'Cookies', 'CK-CR', 12, 9, 5, 'Fudgy cocoa cookies rolled in powdered sugar.', false],
            ['Munchkin Glazed', 'Munchkin', 'MK-GL', 10, 7.5, 4.5, 'Bite-sized doughnuts under a thin vanilla glaze.', true],
            ['Munchkin Choco', 'Munchkin', 'MK-CH', 11, 8, 5, 'Doughnut bites dipped in dark chocolate.', false],
            ['Cloudy Classic', 'Cream Cups', 'CC-CL', 13, 10, 6, 'Airy whipped cream over a soft biscuit floor.', true],
            ['Cloudy Strawberry', 'Cream Cups', 'CC-ST', 15, 11, 7, 'Strawberry compote swirled into cloud cream.', false],
            ['Cocoa Cascade', 'Cream Cups', 'CO-CS', 13, 10, 6, 'Dark cocoa pudding with a cascading ganache top.', true],
            ['Biscoff Dream', 'Seasonal', 'SE-BF', 18, 13.5, 8.5, 'Speculoos crumb, caramel cream and a cookie shard.', true],
            ['Mango Float Cup', 'Seasonal', 'SE-MF', 16, 12, 7.5, 'Layered mango float in a grab-and-go cup.', false],
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
                    'stock' => 200 - ($i * 12),
                    'low_stock_threshold' => 40,
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

    private function seedResellers(array $products): array
    {
        $numbers = app(NumberGenerator::class);

        $rows = [
            ['Juan Dela Cruz', 'Sweet Corner PH', 'juan@lileu.test', '0917 111 2222', 'Bais City', Reseller::STATUS_APPROVED, 0],
            ['Maria Santos', 'Maria Pastry Hub', 'maria@lileu.test', '0917 333 4444', 'Dumaguete City', Reseller::STATUS_APPROVED, 5],
            ['Ana Reyes', null, 'ana@lileu.test', '0917 555 6666', 'Manjuyod', Reseller::STATUS_PENDING, 0],
        ];

        $out = [];
        foreach ($rows as [$name, $business, $email, $phone, $city, $status, $discount]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_RESELLER,
                    'phone' => $phone,
                    'email_verified_at' => now(),
                ],
            );

            $reseller = Reseller::updateOrCreate(
                ['email' => $email],
                [
                    'user_id' => $user->id,
                    'code' => $numbers->resellerCode(),
                    'name' => $name,
                    'business_name' => $business,
                    'phone' => $phone,
                    'city' => $city,
                    'address' => "{$city}, Negros Oriental",
                    'why_reseller' => 'I already sell desserts online and want a reliable supplier.',
                    'status' => $status,
                    'discount_percent' => $discount,
                    'downpayment_percent' => 50,
                    'applied_at' => now()->subDays(20),
                    'approved_at' => $status === Reseller::STATUS_APPROVED ? now()->subDays(18) : null,
                ],
            );

            if ($status === Reseller::STATUS_APPROVED) {
                $reseller->products()->syncWithoutDetaching(
                    collect($products)->mapWithKeys(fn (Product $p) => [
                        $p->id => ['is_approved' => true, 'custom_price' => null],
                    ])->all(),
                );
            }

            $out[$email] = $reseller;
        }

        return $out;
    }

    private function seedOrders(array $resellers, array $products, User $admin): void
    {
        $orderService = app(OrderService::class);
        $paymentService = app(PaymentService::class);

        $juan = $resellers['juan@lileu.test'];
        $maria = $resellers['maria@lileu.test'];

        // Order 1 — the master-prompt example: 50% downpayment settled, balance open.
        $order = $orderService->placeResellerOrder($juan, [
            ['product_id' => $products['GN-CL']->id, 'quantity' => 50],
            ['product_id' => $products['CC-CL']->id, 'quantity' => 20],
            ['product_id' => $products['CO-CS']->id, 'quantity' => 20],
        ], [
            'fulfillment_type' => 'pickup',
            'date_needed' => now()->addDays(3)->toDateString(),
            'time_needed' => '10:00',
            'notes' => 'Please pack per flavour in separate trays.',
        ]);

        $paymentService->markPaid(
            $paymentService->openPayment($order),
            ['reference' => 'QRPH-DEMO-001'],
        );

        Message::create([
            'reseller_id' => $juan->id,
            'reseller_order_id' => $order->id,
            'user_id' => $juan->user_id,
            'author_role' => 'reseller',
            'body' => 'Hi! Downpayment sent. Can I pick this up at 10 AM sharp?',
        ]);

        Message::create([
            'reseller_id' => $juan->id,
            'reseller_order_id' => $order->id,
            'user_id' => $admin->id,
            'author_role' => 'admin',
            'body' => 'Received, thank you! 10 AM works perfectly. See you then. 💗',
        ]);

        // Order 2 — fully settled in two payments, so the summary sheet has data.
        $paid = $orderService->placeResellerOrder($maria, [
            ['product_id' => $products['SE-BF']->id, 'quantity' => 30],
            ['product_id' => $products['GN-MG']->id, 'quantity' => 30],
        ], [
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'Purok 3, Barangay Poblacion, Bais City',
            'delivery_fee' => 150,
            'date_needed' => now()->addDays(5)->toDateString(),
            'time_needed' => '14:30',
        ]);

        $paymentService->markPaid($paymentService->openPayment($paid), ['reference' => 'QRPH-DEMO-002']);
        $paymentService->markPaid($paymentService->openPayment($paid->refresh()), ['reference' => 'QRPH-DEMO-003']);
        $paid->refresh()->update([
            'status' => \App\Models\ResellerOrder::STATUS_READY,
        ]);

        // Order 3 — brand new, nothing paid yet.
        $orderService->placeResellerOrder($maria, [
            ['product_id' => $products['CC-ST']->id, 'quantity' => 24],
        ], [
            'fulfillment_type' => 'pickup',
            'date_needed' => now()->addDays(7)->toDateString(),
        ]);
    }

    /**
     * Consignment sellers are added by hand, not through the public form, and
     * usually have no email or login at all.
     */
    private function seedConsignments(array $products, User $admin): void
    {
        $numbers = app(NumberGenerator::class);
        $service = app(ConsignmentService::class);

        $rows = [
            ['Kyle Amores', 'Mabinay National High School', '0917 777 8888', 'Mabinay'],
            ['Aling Nena', 'Public Market Stall 12', '0917 999 0000', 'Mabinay'],
        ];

        $sellers = [];
        foreach ($rows as [$name, $business, $phone, $city]) {
            $sellers[] = Reseller::updateOrCreate(
                ['name' => $name],
                [
                    'code' => $numbers->resellerCode(),
                    'business_name' => $business,
                    'email' => null,
                    'phone' => $phone,
                    'city' => $city,
                    'address' => $city.', Negros Oriental',
                    'status' => Reseller::STATUS_APPROVED,
                    'engagement' => Reseller::ENGAGEMENT_CONSIGNMENT,
                    'downpayment_percent' => 0,
                    'applied_at' => now()->subDays(10),
                    'approved_at' => now()->subDays(10),
                    'approved_by' => $admin->id,
                ],
            );
        }

        // Batch 1 — yesterday's tray, already collected: most sold, a few came
        // back good, one melted in the heat.
        $batch = $service->issue($sellers[0], [
            ['product_id' => $products['GN-CL']->id, 'quantity' => 30],
            ['product_id' => $products['CC-CL']->id, 'quantity' => 20],
        ], [
            'issued_on' => now()->subDay()->toDateString(),
            'due_on' => now()->toDateString(),
            'notes' => 'Selling at the school canteen until 4 PM.',
        ], $admin);

        $items = $batch->items()->get()->keyBy('sku');

        $service->settle($batch, [
            ['consignment_item_id' => $items['GN-CL']->id, 'sold' => 26, 'returned' => 3, 'damaged' => 1],
            ['consignment_item_id' => $items['CC-CL']->id, 'sold' => 15, 'returned' => 4, 'expired' => 1],
        ], [
            'settled_on' => now()->toDateString(),
            'method' => 'cash',
            'is_final' => true,
            'notes' => 'One cup melted on the way home.',
        ], $admin);

        // Batch 2 — went out this morning, nothing collected yet.
        $service->issue($sellers[1], [
            ['product_id' => $products['CO-CS']->id, 'quantity' => 24],
            ['product_id' => $products['SE-BF']->id, 'quantity' => 12],
        ], [
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDay()->toDateString(),
            'notes' => 'Market day — collect tomorrow morning.',
        ], $admin);
    }

    private function seedLedger(User $admin): void
    {
        foreach ([
            ['operations', 'Store rent share', 4500],
            ['utilities', 'Electricity and water', 2380],
            ['packaging', 'Cups, lids and stickers', 1750],
        ] as [$category, $description, $amount]) {
            Expense::create([
                'incurred_on' => now()->subDays(rand(1, 20)),
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'recorded_by' => $admin->id,
            ]);
        }

        foreach ([
            ['Negros Dairy Supply', 'Cream and condensed milk restock', 6200],
            ['Graham House', 'Graham crackers 20kg', 3400],
        ] as [$supplier, $description, $amount]) {
            Purchase::create([
                'purchased_on' => now()->subDays(rand(1, 20)),
                'supplier' => $supplier,
                'reference' => 'PO-'.strtoupper(Str::random(6)),
                'description' => $description,
                'amount' => $amount,
                'recorded_by' => $admin->id,
            ]);
        }
    }
}
