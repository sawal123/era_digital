<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDemoSettingMutations
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isDemo() || $request->isMethodSafe()) {
            return $next($request);
        }

        $blockedRoutes = [
            'two-factor.confirm',
            'two-factor.disable',
            'two-factor.enable',
            'two-factor.regenerate-recovery-codes',
        ];

        if ($request->routeIs(...$blockedRoutes)) {
            return back()->with('error', 'Akun demo tidak memiliki akses untuk mengubah pengaturan.');
        }

        return $next($request);
    }
}
