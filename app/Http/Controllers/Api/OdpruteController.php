<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OdpPoint;
use App\Models\OdpRoute;

class OdpruteController extends Controller
{
    public function routes()
    {
        return OdpRoute::with('odc:id,name,status')->get(['id', 'odc_id', 'name', 'description', 'color', 'coordinates']);
    }

    public function points()
    {
        return OdpPoint::with('route:id,name,color')->get();
    }
}
