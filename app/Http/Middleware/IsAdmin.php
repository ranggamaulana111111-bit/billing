<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->role !== 'admin') {
            abort(403, 'Akses hanya untuk admin.');
        }

        return $next($request);
    }
}
