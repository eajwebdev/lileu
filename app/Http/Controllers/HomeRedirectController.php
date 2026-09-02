<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class HomeRedirectController extends Controller
{
    /** One /dashboard entry point; each role lands where it belongs. */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() => redirect()->route('admin.dashboard'),
            $user->isCashier() => redirect()->route('pos.index'),
            $user->reseller !== null => redirect()->route('portal.dashboard'),
            default => redirect()->route('portal.status'),
        };
    }
}
