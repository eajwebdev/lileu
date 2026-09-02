<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ResellerApplicationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Site/BecomeReseller', [
            'downpaymentPercent' => config('lileu.orders.downpayment_percent'),
        ]);
    }

    public function store(Request $request, NumberGenerator $numbers): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'why_reseller' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($data, $numbers) {
            // The account exists from day one so the applicant can log in and
            // watch their status; the portal itself stays locked until approval.
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_RESELLER,
            ]);

            Reseller::create([
                'user_id' => $user->id,
                'code' => $numbers->resellerCode(),
                'name' => $data['name'],
                'business_name' => $data['business_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'facebook' => $data['facebook'] ?? null,
                'why_reseller' => $data['why_reseller'] ?? null,
                'status' => Reseller::STATUS_PENDING,
                'downpayment_percent' => config('lileu.orders.downpayment_percent'),
                'applied_at' => now(),
            ]);
        });

        return redirect()->route('reseller.apply.received');
    }

    public function received(): Response
    {
        return Inertia::render('Site/ApplicationReceived');
    }
}
