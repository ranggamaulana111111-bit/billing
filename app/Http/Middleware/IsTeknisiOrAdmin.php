<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsTeknisiOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! in_array($request->user()->role, ['admin', 'teknisi'])) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
