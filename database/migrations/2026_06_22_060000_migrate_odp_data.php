<?php

use App\Models\Customer;
use App\Models\Odp;
use App\Models\OdpPoint;
use App\Models\OdpPort;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('tenants')->value('id');

        OdpPoint::with('route.odc')->chunk(50, function ($points) use ($tenantId) {
            foreach ($points as $old) {
                $odcId = $old->route?->odc?->id;

                $odp = Odp::create([
                    'tenant_id' => $tenantId,
                    'odc_id' => $odcId,
                    'nama_odp' => $old->name,
                    'koordinat' => $old->latitude && $old->longitude
                        ? $old->latitude.','.$old->longitude
                        : null,
                    'kapasitas_port' => $old->port_capacity ?: 8,
                    'kabel_tube_color' => 'Biru',
                    'kabel_core_number' => 1,
                    'kondisi_jalur' => 'UP',
                ]);

                for ($i = 1; $i <= $odp->kapasitas_port; $i++) {
                    OdpPort::create([
                        'odp_id' => $odp->id,
                        'port_number' => $i,
                        'status' => 'available',
                    ]);
                }

                Customer::where('odp_point_id', $old->id)
                    ->whereNotNull('odp_point_id')
                    ->each(function ($customer) use ($odp) {
                        $firstAvailable = $odp->ports()->where('status', 'available')->first();
                        if ($firstAvailable) {
                            $firstAvailable->update(['status' => 'used']);
                            $customer->updateQuietly([
                                'odp_id' => $odp->id,
                                'odp_port_id' => $firstAvailable->id,
                            ]);
                        }
                    });
            }
        });
    }

    public function down(): void
    {
        Customer::query()->update(['odp_id' => null, 'odp_port_id' => null]);
        Odp::query()->delete();
    }
};
