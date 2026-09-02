<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'settings' => array_merge(
                Settings::brand(),
                [
                    'receipt_prefix' => Settings::get('receipt_prefix', config('lileu.receipt.prefix')),
                    'order_prefix' => Settings::get('order_prefix', config('lileu.receipt.order_prefix')),
                    'receipt_show_logo' => Settings::bool('receipt_show_logo', (bool) config('lileu.receipt.show_logo')),
                    'receipt_footer' => Settings::get('receipt_footer', config('lileu.receipt.footer')),
                    'receipt_paper' => Settings::get('receipt_paper', config('lileu.receipt.paper')),
                    'default_downpayment_percent' => Settings::int('default_downpayment_percent', (int) config('lileu.orders.downpayment_percent')),
                ],
            ),
            'paymongo' => [
                // Never echo the keys themselves back to the browser.
                'configured' => filled(config('lileu.paymongo.secret_key')),
                'webhook_url' => route('webhooks.paymongo'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'business_tagline' => ['nullable', 'string', 'max:120'],
            'business_phone' => ['nullable', 'string', 'max:60'],
            'business_email' => ['nullable', 'email', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'business_facebook' => ['nullable', 'string', 'max:255'],
            'business_website' => ['nullable', 'string', 'max:255'],
            'business_logo' => ['nullable', 'string', 'max:255'],
            'receipt_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
            'order_prefix' => ['required', 'string', 'max:6', 'regex:/^[A-Z0-9]+$/'],
            'receipt_show_logo' => ['boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'receipt_paper' => ['required', 'in:a4,thermal'],
            'default_downpayment_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        Settings::putMany($data, 'branding');

        return back()->with('success', 'Settings saved.');
    }
}
