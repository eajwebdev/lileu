<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Product;
use App\Models\Reseller;
use App\Models\ResellerOrder;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ResellerController extends Controller
{
    public function index(Request $request): Response
    {
        $resellers = Reseller::query()
            ->withCount('orders')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('business_name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")))
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'suspended', 'rejected')")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reseller $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'business_name' => $r->business_name,
                'email' => $r->email,
                'phone' => $r->phone,
                'city' => $r->city,
                'address' => $r->address,
                'status' => $r->status,
                'engagement' => $r->engagement,
                'orders_count' => $r->orders_count,
                'lifetime_value' => (float) $r->lifetime_value,
                'discount_percent' => $r->discount_percent,
                'downpayment_percent' => $r->downpayment_percent,
                'admin_notes' => $r->admin_notes,
                'applied_on' => $r->applied_at?->format('M j, Y'),
            ]);

        return Inertia::render('Admin/Resellers/Index', [
            'resellers' => $resellers,
            'filters' => $request->only(['status', 'q']),
            'counts' => [
                'pending' => Reseller::where('status', Reseller::STATUS_PENDING)->count(),
                'approved' => Reseller::where('status', Reseller::STATUS_APPROVED)->count(),
                'suspended' => Reseller::where('status', Reseller::STATUS_SUSPENDED)->count(),
            ],
        ]);
    }

    /**
     * Adds a seller by hand. Consignment sellers (students, market vendors)
     * usually have no email and need no portal login, so the account is only
     * created when a password is supplied.
     */
    public function store(Request $request, NumberGenerator $numbers): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'engagement' => ['required', 'in:reseller,consignment,both'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:50'],
            'downpayment_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'create_login' => ['boolean'],
            'password' => ['nullable', 'required_if:create_login,true', 'confirmed', Password::defaults()],
        ]);

        $reseller = DB::transaction(function () use ($data, $numbers, $request) {
            $userId = null;

            if (! empty($data['create_login']) && ! empty($data['email'])) {
                $userId = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => User::ROLE_RESELLER,
                    'email_verified_at' => now(),
                ])->id;
            }

            return Reseller::create([
                'user_id' => $userId,
                'code' => $numbers->resellerCode(),
                'name' => $data['name'],
                'business_name' => $data['business_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'engagement' => $data['engagement'],
                // Added by us, so already approved: there is nothing to review.
                'status' => Reseller::STATUS_APPROVED,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'downpayment_percent' => $data['downpayment_percent'] ?? config('lileu.orders.downpayment_percent'),
                'admin_notes' => $data['admin_notes'] ?? null,
                'applied_at' => now(),
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('admin.resellers.show', $reseller->id)
            ->with('success', $reseller->name.' added as a seller.');
    }

    public function show(Reseller $reseller): Response
    {
        $reseller->load(['orders' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('Admin/Resellers/Show', [
            'reseller' => [
                'id' => $reseller->id,
                'code' => $reseller->code,
                'name' => $reseller->name,
                'business_name' => $reseller->business_name,
                'email' => $reseller->email,
                'phone' => $reseller->phone,
                'city' => $reseller->city,
                'address' => $reseller->address,
                'facebook' => $reseller->facebook,
                'why_reseller' => $reseller->why_reseller,
                'status' => $reseller->status,
                'engagement' => $reseller->engagement,
                'discount_percent' => $reseller->discount_percent,
                'downpayment_percent' => $reseller->downpayment_percent,
                'admin_notes' => $reseller->admin_notes,
                'lifetime_value' => (float) $reseller->lifetime_value,
                'outstanding' => $reseller->outstandingBalance(),
                'consignment_due' => $reseller->consignmentDue(),
                'applied_on' => $reseller->applied_at?->format('F j, Y'),
                'approved_on' => $reseller->approved_at?->format('F j, Y'),
            ],
            'orders' => $reseller->orders->map(fn (ResellerOrder $o) => [
                'number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total' => (float) $o->total,
                'balance' => (float) $o->balance,
                'placed_on' => $o->created_at->format('M j, Y'),
            ]),
            'consignments' => $reseller->consignments()->latest('issued_on')->limit(8)->get()
                ->map(fn ($c) => [
                    'number' => $c->consignment_number,
                    'status' => $c->status,
                    'issued_on' => $c->issued_on->format('M j, Y'),
                    'quantity_issued' => $c->quantity_issued,
                    'outstanding' => $c->outstandingQuantity(),
                    'amount_due' => (float) $c->amount_due,
                ]),
            'catalog' => Product::active()
                ->with('variants')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    // Approval is per product, so a flavoured one quotes its
                    // cheapest flavour rather than its own empty price.
                    'reseller_price' => $p->lowestResellerPrice(),
                    'retail_price' => $p->lowestRetailPrice(),
                    'has_variants' => $p->hasVariants(),
                ]),
            'assigned' => $reseller->products()->get()->map(fn (Product $p) => [
                'product_id' => $p->id,
                'custom_price' => $p->pivot->custom_price !== null ? (float) $p->pivot->custom_price : null,
                'is_approved' => (bool) $p->pivot->is_approved,
            ]),
        ]);
    }

    public function update(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'engagement' => ['required', 'in:reseller,consignment,both'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'downpayment_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reseller->update($data);

        return back()->with('success', 'Reseller updated.');
    }

    public function updateStatus(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,suspended'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $reseller->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? $reseller->admin_notes,
            'approved_at' => $data['status'] === Reseller::STATUS_APPROVED ? now() : $reseller->approved_at,
            'approved_by' => $data['status'] === Reseller::STATUS_APPROVED ? $request->user()->id : $reseller->approved_by,
        ]);

        if (! empty($data['message'])) {
            Message::create([
                'reseller_id' => $reseller->id,
                'user_id' => $request->user()->id,
                'author_role' => 'admin',
                'body' => $data['message'],
            ]);
        }

        return back()->with('success', "Reseller marked as {$data['status']}.");
    }

    /** Curates the catalog a reseller may order from, with optional pricing. */
    public function syncProducts(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate([
            'products' => ['present', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.custom_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.is_approved' => ['boolean'],
        ]);

        $sync = collect($data['products'])->mapWithKeys(fn ($row) => [
            (int) $row['product_id'] => [
                // An absent or blank custom price means "use the standard rate".
                'custom_price' => isset($row['custom_price']) && $row['custom_price'] !== ''
                    ? (float) $row['custom_price']
                    : null,
                'is_approved' => (bool) ($row['is_approved'] ?? true),
            ],
        ])->all();

        $reseller->products()->sync($sync);

        return back()->with('success', 'Approved products updated.');
    }
}
