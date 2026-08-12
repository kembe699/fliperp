<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated tenant request (not just at login) so a company
 * suspended mid-session stops working on its NEXT request, not just future
 * logins — an already-issued Sanctum token is otherwise valid until it expires
 * or is revoked, regardless of what happens to the company afterward.
 *
 * Deliberately not applied to /platform-admin/* (see routes/api.php) — Nile
 * Hive staff need to keep managing a client while that client's own company is
 * suspended, and the platform company itself is always active anyway.
 *
 * A user with no company at all (company_id null — see the pre-existing
 * super_admin@demo.test seed) is intentionally let through unchecked: there's
 * no company to be suspended/pending.
 */
class EnsureCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // A fresh lookup by id, not the lazy $user->company relation — Eloquent
        // caches a loaded relation on the model instance for as long as that
        // instance lives, and the whole point of this check is to see a status
        // change that happened after the token/user was resolved.
        $companyId = $request->user()?->company_id;
        $company = $companyId ? Company::find($companyId) : null;

        if ($company && $company->status !== 'active') {
            $reason = $company->status === 'suspended' ? 'company_suspended' : 'company_pending';

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $company->status === 'suspended'
                    ? 'Your account has been suspended. Please contact support.'
                    : 'Your account is not yet active. Please contact support.',
                'errors' => null,
                'reason' => $reason,
            ], 403);
        }

        return $next($request);
    }
}
