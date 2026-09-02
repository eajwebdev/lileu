<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerIsApproved
{
    /**
     * Pending / rejected applicants keep their login but are parked on a status
     * page rather than being dropped into an empty ordering portal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = $request->user()?->reseller;

        abort_if(! $reseller, 403, 'No reseller profile is linked to this account.');

        if ($reseller->status !== Reseller::STATUS_APPROVED && ! $request->routeIs('portal.status')) {
            return redirect()->route('portal.status');
        }

        return $next($request);
    }
}
