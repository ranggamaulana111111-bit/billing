<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsNoc
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! in_array($request->user()->role, ['admin', 'superadmin', 'noc'])) {
            abort(403, 'Akses hanya untuk NOC.');
        }

        return $next($request);
    }
}
