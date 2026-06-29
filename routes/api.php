<?php

use App\Http\Controllers\Api\MikrotikHotspotController;
use Illuminate\Support\Facades\Route;

Route::post('/api/v1/mikrotik/hotspot-login', [MikrotikHotspotController::class, 'hotspotLogin']);
