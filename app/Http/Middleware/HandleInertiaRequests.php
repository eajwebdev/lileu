<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $reseller = $user?->isReseller() ? $user->reseller : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ] : null,
                'reseller' => $reseller ? [
                    'id' => $reseller->id,
                    'code' => $reseller->code,
                    'status' => $reseller->status,
                    'business_name' => $reseller->business_name,
                    'discount_percent' => $reseller->discount_percent,
                ] : null,
            ],
            // Every surface — public, portal, admin, POS, receipt — reads its
            // brand strings from the same editable settings record.
            'brand' => fn () => Settings::brand(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
