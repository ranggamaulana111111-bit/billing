<?php

namespace App\Http\Controllers;

use App\Models\Odp;

class OdpController extends Controller
{
    public function show(Odp $odp)
    {
        $odp->load('ports.customer', 'odc');

        return view('odp.show', compact('odp'));
    }
}
