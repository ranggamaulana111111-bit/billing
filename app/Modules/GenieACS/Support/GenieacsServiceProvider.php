<?php

namespace App\Modules\GenieACS\Support;

use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Repositories\GenieACSRepository;
use App\Modules\GenieACS\Services\GenieACSClient;
use Illuminate\Support\ServiceProvider;

/**
 * Registers GenieACS module bindings in the Laravel container.
 *
 * Bindings:
 * - IGenieACSClient → GenieACSClient (singleton)
 * - GenieACSRepository (singleton, injected with IGenieACSClient)
 */
class GenieacsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IGenieACSClient::class, function () {
            return new GenieACSClient;
        });

        $this->app->singleton(GenieACSRepository::class, function ($app) {
            return new GenieACSRepository(
                $app->make(IGenieACSClient::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
