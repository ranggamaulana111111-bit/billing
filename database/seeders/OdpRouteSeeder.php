<?php

namespace Database\Seeders;

use App\Models\Odc;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use Illuminate\Database\Seeder;

class OdpRouteSeeder extends Seeder
{
    public function run(): void
    {
        $bendunganOdc = Odc::updateOrCreate(['name' => 'ODC Bendungan'], [
            'address' => 'Bendungan, Banjarsari, Lebak',
            'latitude' => null,
            'longitude' => null,
            'status' => 'active',
            'capacity' => 48,
            'notes' => 'ODC area Bendungan Banjarsari Lebak',
        ]);

        $route1 = OdpRoute::updateOrCreate(['name' => 'ODP Jalur Kumpay Timur'], [
            'odc_id' => $bendunganOdc->id,
            'description' => 'Jalur fiber optik RT 01 - RT 03 Kumpay Timur',
            'color' => '#e74c3c',
            'coordinates' => [
                [-6.4745, 106.0140],
                [-6.4752, 106.0148],
                [-6.4760, 106.0155],
                [-6.4768, 106.0162],
                [-6.4775, 106.0168],
            ],
        ]);

        OdpPoint::updateOrCreate(['name' => 'ODP-01 Kumpay Timur'], [
            'odp_route_id' => $route1->id,
            'address' => 'RT 01, Kumpay, Banjarsari',
            'latitude' => -6.4752,
            'longitude' => 106.0148,
            'port_capacity' => 16,
            'port_used' => 12,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-02 Kumpay Tengah'], [
            'odp_route_id' => $route1->id,
            'address' => 'RT 02, Kumpay, Banjarsari',
            'latitude' => -6.4765,
            'longitude' => 106.0160,
            'port_capacity' => 8,
            'port_used' => 5,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-03 Kumpay Selatan'], [
            'odp_route_id' => $route1->id,
            'address' => 'RT 03, Kumpay, Banjarsari',
            'latitude' => -6.4775,
            'longitude' => 106.0170,
            'port_capacity' => 16,
            'port_used' => 14,
        ]);

        $route2 = OdpRoute::updateOrCreate(['name' => 'ODP Jalur Kumpay Barat'], [
            'odc_id' => $bendunganOdc->id,
            'description' => 'Jalur fiber optik dari barat ke timur Kumpay',
            'color' => '#2ecc71',
            'coordinates' => [
                [-6.4740, 106.0130],
                [-6.4748, 106.0125],
                [-6.4755, 106.0120],
                [-6.4763, 106.0115],
                [-6.4770, 106.0110],
            ],
        ]);

        OdpPoint::updateOrCreate(['name' => 'ODP-04 Kumpay Barat'], [
            'odp_route_id' => $route2->id,
            'address' => 'RT 04, Kumpay, Banjarsari',
            'latitude' => -6.4748,
            'longitude' => 106.0125,
            'port_capacity' => 8,
            'port_used' => 3,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-05 Kumpay Utara'], [
            'odp_route_id' => $route2->id,
            'address' => 'RT 05, Kumpay, Banjarsari',
            'latitude' => -6.4763,
            'longitude' => 106.0115,
            'port_capacity' => 16,
            'port_used' => 8,
        ]);

        $route3 = OdpRoute::updateOrCreate(['name' => 'ODP Jalur Kumpay Raya'], [
            'odc_id' => $bendunganOdc->id,
            'description' => 'Jalur fiber optik sepanjang Jalan Raya Kumpay',
            'color' => '#3498db',
            'coordinates' => [
                [-6.4750, 106.0150],
                [-6.4758, 106.0145],
                [-6.4765, 106.0140],
                [-6.4772, 106.0135],
                [-6.4780, 106.0130],
            ],
        ]);

        OdpPoint::updateOrCreate(['name' => 'ODP-06 Kumpay Raya'], [
            'odp_route_id' => $route3->id,
            'address' => 'Jl. Raya Kumpay, Banjarsari',
            'latitude' => -6.4770,
            'longitude' => 106.0135,
            'port_capacity' => 16,
            'port_used' => 10,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-07 Kumpay Pasar'], [
            'odp_route_id' => $route3->id,
            'address' => 'Pasar Kumpay, Banjarsari',
            'latitude' => -6.4750,
            'longitude' => 106.0158,
            'port_capacity' => 8,
            'port_used' => 7,
        ]);

        $route4 = OdpRoute::updateOrCreate(['name' => 'ODP Jalur Kumpay Selatan'], [
            'odc_id' => $bendunganOdc->id,
            'description' => 'Jalur fiber optik selatan desa Kumpay',
            'color' => '#f39c12',
            'coordinates' => [
                [-6.4735, 106.0145],
                [-6.4742, 106.0152],
                [-6.4750, 106.0158],
                [-6.4758, 106.0165],
                [-6.4765, 106.0170],
            ],
        ]);

        OdpPoint::updateOrCreate(['name' => 'ODP-08 Kumpay Sekolah'], [
            'odp_route_id' => $route4->id,
            'address' => 'SDN Kumpay, Banjarsari',
            'latitude' => -6.4760,
            'longitude' => 106.0165,
            'port_capacity' => 16,
            'port_used' => 6,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-09 Kumpay Masjid'], [
            'odp_route_id' => $route4->id,
            'address' => 'Masjid Al-Fatah, Kumpay',
            'latitude' => -6.4758,
            'longitude' => 106.0125,
            'port_capacity' => 8,
            'port_used' => 4,
        ]);

        $route5 = OdpRoute::updateOrCreate(['name' => 'ODP Jalur Kumpay Lapangan'], [
            'odc_id' => $bendunganOdc->id,
            'description' => 'Jalur fiber optik sekitar lapangan Kumpay',
            'color' => '#9b59b6',
            'coordinates' => [
                [-6.4775, 106.0145],
                [-6.4770, 106.0138],
                [-6.4765, 106.0132],
                [-6.4758, 106.0125],
                [-6.4750, 106.0120],
            ],
        ]);

        OdpPoint::updateOrCreate(['name' => 'ODP-10 Kumpay Lapangan'], [
            'odp_route_id' => $route5->id,
            'address' => 'Lapangan Kumpay, Banjarsari',
            'latitude' => -6.4778,
            'longitude' => 106.0145,
            'port_capacity' => 8,
            'port_used' => 8,
        ]);
        OdpPoint::updateOrCreate(['name' => 'ODP-11 Kumpay Posyandu'], [
            'odp_route_id' => $route5->id,
            'address' => 'Posyandu Kumpay, Banjarsari',
            'latitude' => -6.4742,
            'longitude' => 106.0152,
            'port_capacity' => 16,
            'port_used' => 9,
        ]);

        Odc::whereIn('name', ['ODC Kumpay Utama', 'ODC Kumpay Barat'])->delete();
    }
}
