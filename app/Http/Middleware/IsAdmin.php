<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! in_array($request->user()->role, ['admin', 'superadmin'])) {
    abort(403, 'Akses hanya untuk admin.');
}
        return $next($request);
    }
}
