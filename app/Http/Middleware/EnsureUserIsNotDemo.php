<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotDemo
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDemo()) {
            return back()->with('error', 'Akun demo tidak memiliki akses untuk mengubah pengaturan.');
        }

        return $next($request);
    }
}
