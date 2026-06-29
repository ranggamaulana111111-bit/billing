<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use Illuminate\Http\Request;

class OdpruteController extends Controller
{
    public function routes(Request $request)
    {
        $query = OdpRoute::with('odc:id,name,status')->select(['id', 'odc_id', 'name', 'description', 'color', 'coordinates']);

        if ($request->filled(['north', 'south', 'east', 'west'])) {
            $north = (float) $request->north;
            $south = (float) $request->south;
            $east = (float) $request->east;
            $west = (float) $request->west;
        }

        return $query->get();
    }

    public function points(Request $request)
    {
        $query = OdpPoint::with('route:id,name,color');

        if ($request->filled(['north', 'south', 'east', 'west'])) {
            $north = (float) $request->north;
            $south = (float) $request->south;
            $east = (float) $request->east;
            $west = (float) $request->west;

            $query->whereBetween('latitude', [$south, $north])
                ->whereBetween('longitude', [$west, $east]);
        }

        return $query->get();
    }
}
