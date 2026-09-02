<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $reseller = $request->user()->reseller;

        abort_if(! $reseller, 403);

        if ($reseller->isApproved()) {
            return redirect()->route('portal.dashboard');
        }

        return Inertia::render('Portal/Status', [
            'reseller' => [
                'code' => $reseller->code,
                'name' => $reseller->name,
                'status' => $reseller->status,
                'applied_at' => $reseller->applied_at?->format('F j, Y'),
                'admin_notes' => $reseller->status === 'rejected' ? $reseller->admin_notes : null,
            ],
        ]);
    }
}
