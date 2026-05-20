<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Central\Tenant;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByHandle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $handle = $request->header('X-Tenant-Handle');

        if (is_array($handle)) {
            $handle = end($handle);
        } elseif (is_string($handle) && str_contains($handle, ',')) {
            $handle = trim(explode(',', $handle)[0]);
        }

        if (!$handle) {
            return response()->json([
                'message' => 'Missing X-Tenant-Handle header.'
            ], 400);
        }

        $tenant = Tenant::where('handle', $handle)->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.'
            ], 404);
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
