<?php

namespace App\Http\Controllers;

use App\Models\Odc;

class OdcController extends Controller
{
    public function show(Odc $odc)
    {
        $odc->load('ports.connectedOdp', 'odps');

        return view('odc.show', compact('odc'));
    }
}
