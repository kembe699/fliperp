<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the whole /platform-admin/* route tree. Deliberately a dedicated
 * middleware rather than widening CompanyScope or reusing the pre-existing
 * company_id===null "platform super_admin" convention (see RoleService::
 * isPlatformSuperAdmin()) — platform staff here keep a real home company (the
 * platform company) and are normally tenant-scoped everywhere else; this flag
 * only ever grants anything on routes explicitly wrapped with this middleware.
 * Per-endpoint ability (view vs. create vs. suspend a client, etc.) is still
 * enforced by the normal `permission:platform-x.y` middleware alongside this
 * one — this only gates entry to the panel at all.
 */
class EnsurePlatformStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_platform_staff) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'This action is unauthorized.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
