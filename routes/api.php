<?php

use App\Http\Controllers\Api\MikrotikHotspotController;
use App\Http\Controllers\Api\PortController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/mikrotik/hotspot-login', [MikrotikHotspotController::class, 'hotspotLogin']);

Route::get('/v1/odp/{odp}/ports', [PortController::class, 'odpPorts']);
Route::get('/v1/odc/{odc}/ports', [PortController::class, 'odcPorts']);
