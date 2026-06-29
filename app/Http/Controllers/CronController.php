<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function run(Request $request)
    {
        $token = $request->query('token');

        if (! $token || $token !== config('services.cron_token')) {
            abort(401, 'Unauthorized');
        }

        Artisan::call('schedule:run');

        return response('OK', 200);
    }
}
