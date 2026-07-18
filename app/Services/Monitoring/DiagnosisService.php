<?php

namespace App\Services\Monitoring;

class DiagnosisService
{
    public function diagnose(array $data): array
    {
        $diagnoses = [];

        $status = strtolower($data['status'] ?? 'unknown');
        $rx = $data['rx_power'] ?? null;
        $tx = $data['tx_power'] ?? null;
        $temperature = $data['temperature'] ?? null;
        $voltage = $data['voltage'] ?? null;
        $biasCurrent = $data['bias_current'] ?? null;
        $losDetected = $data['los_detected'] ?? false;
        $dyingGasp = $data['dying_gasp_detected'] ?? false;
        $authFailed = $data['auth_failed'] ?? false;

        if ($rx !== null && $rx > -22 && $status === 'offline') {
            $diagnoses[] = [
                'title' => 'ONU Offline dengan RX Normal',
                'severity' => 'warning',
                'priority' => 'Medium',
                'causes' => [
                    'Adaptor listrik ONU mati',
                    'Router pelanggan mati',
                    'ONU hang/frozen',
                    'Kabel Ethernet longgar',
                ],
                'recommendations' => [
                    'Periksa adaptor listrik ONU',
                    'Restart router pelanggan',
                    'Power cycle ONU (cabut pasang)',
                    'Periksa kabel Ethernet LAN',
                ],
                'confidence' => 85,
            ];
        }

        if ($rx !== null && $rx < -28) {
            $diagnoses[] = [
                'title' => 'RX Power Sangat Lemah',
                'severity' => 'critical',
                'priority' => 'High',
                'causes' => [
                    'Fiber optik putus/cabang',
                    'Patchcord lepas/kendor',
                    'Konektor fiber kotor/rusak',
                    'Splitter bermasalah',
                    'Terlalu banyak splice point',
                ],
                'recommendations' => [
                    'Periksa jalur fiber dari ODP ke ONU',
                    'Bersihkan konektor fiber',
                    'Ganti patchcord jika rusak',
                    'Periksa splicing point',
                    'Gunakan OTDR untuk localisasi break',
                ],
                'confidence' => 90,
            ];
        }

        if ($rx !== null && $rx >= -28 && $rx < -25) {
            $diagnoses[] = [
                'title' => 'RX Power Lemah (Warning)',
                'severity' => 'warning',
                'priority' => 'Medium',
                'causes' => [
                    'Konektor fiber kotor',
                    'Patchcord bengkok berlebihan',
                    'Splice loss tinggi',
                    'Splitter marginal',
                ],
                'recommendations' => [
                    'Bersihkan konektor fiber',
                    'Periksa routing patchcord',
                    'Cek splice point dengan OTDR',
                    'Monitor secara berkala',
                ],
                'confidence' => 75,
            ];
        }

        if ($temperature !== null && $temperature > 70) {
            $diagnoses[] = [
                'title' => 'ONU Terlalu Panas',
                'severity' => 'critical',
                'priority' => 'High',
                'causes' => [
                    'Ventilasi/OHU buruk pada ruang ONU',
                    'Adaptor power bermasalah',
                    'ONU bekerja overload',
                    'Suhu lingkungan terlalu tinggi',
                ],
                'recommendations' => [
                    'Pastikan ventilasi ONU cukup',
                    'Ganti adaptor power',
                    'Pindahkan ONU ke lokasi lebih sejuk',
                    'Periksa apakah ONU terkena sinar matahari langsung',
                ],
                'confidence' => 80,
            ];
        }

        if ($biasCurrent !== null && $biasCurrent > 80) {
            $diagnoses[] = [
                'title' => 'Bias Current Tinggi — Laser Melemah',
                'severity' => 'warning',
                'priority' => 'Medium',
                'causes' => [
                    'Laser transmitter mulai degradasi',
                    'Usia ONU sudah lama',
                    'Suhu operasional tidak ideal',
                ],
                'recommendations' => [
                    'Monitor bias current secara berkala',
                    'Siapkan ONU pengganti',
                    'Periksa suhu operasional ONU',
                ],
                'confidence' => 70,
            ];
        }

        if ($voltage !== null && $voltage < 3.0) {
            $diagnoses[] = [
                'title' => 'Voltage Rendah — Power Supply Tidak Stabil',
                'severity' => 'warning',
                'priority' => 'Medium',
                'causes' => [
                    'Adaptor power melemah',
                    'Kabel power rusak',
                    'Listrik tidak stabil di lokasi pelanggan',
                    'Power supply tidak sesuai spesifikasi',
                ],
                'recommendations' => [
                    'Ganti adaptor power ONU',
                    'Periksa kabel power',
                    'Gunakan stabilizer/UPS',
                    'Pastikan spesifikasi power sesuai',
                ],
                'confidence' => 85,
            ];
        }

        if ($losDetected || $status === 'los') {
            $diagnoses[] = [
                'title' => 'Loss of Signal (LOS)',
                'severity' => 'critical',
                'priority' => 'Critical',
                'causes' => [
                    'Fiber putus total',
                    'Konektor fiber terlepas',
                    'ODP/closure bermasalah',
                    'Splitter rusak',
                    'Fiber tertekuk/terjepit',
                ],
                'recommendations' => [
                    'Periksa jalur fiber secara visual',
                    'Gunakan OTDR untuk localisasi break point',
                    'Periksa ODP dan closure',
                    'Lakukan splicing ulang jika fiber putus',
                    'Pastikan semua konektor terpasang rapat',
                ],
                'confidence' => 95,
            ];
        }

        if ($authFailed || $status === 'auth-failed') {
            $diagnoses[] = [
                'title' => 'Authentication Failed',
                'severity' => 'critical',
                'priority' => 'High',
                'causes' => [
                    'ONU belum didaftarkan/diregistrasi di OLT',
                    'Serial Number ONU tidak sesuai konfigurasi',
                    'Service Profile salah',
                    'Line Profile tidak cocok',
                ],
                'recommendations' => [
                    'Periksa registrasi ONU di OLT',
                    'Verifikasi Serial Number',
                    'Cek konfigurasi Service Profile',
                    'Re-register ONU jika diperlukan',
                ],
                'confidence' => 95,
            ];
        }

        if ($dyingGasp || $status === 'dying-gasp') {
            $diagnoses[] = [
                'title' => 'Dying Gasp — ONU Kehabisan Daya',
                'severity' => 'critical',
                'priority' => 'Critical',
                'causes' => [
                    'Listrik padam di lokasi pelanggan',
                    'Adaptor power tercabut',
                    'Power supply rusak',
                    'MCB tripped',
                ],
                'recommendations' => [
                    'Konfirmasi status listrik di lokasi pelanggan',
                    'Hubungi pelanggan',
                    'Periksa adaptor dan kabel power',
                    'Jika listrik padam, tunggu atau gunakan UPS',
                ],
                'confidence' => 95,
            ];
        }

        if ($status === 'offline' && ($rx === null || $rx === 0.0) && ! $losDetected && ! $dyingGasp && ! $authFailed) {
            $diagnoses[] = [
                'title' => 'ONU Offline — Penyebab Tidak Diketahui',
                'severity' => 'warning',
                'priority' => 'Medium',
                'causes' => [
                    'ONU perlu di-reboot',
                    'Konfigurasi berubah',
                    'Intermittent connection',
                ],
                'recommendations' => [
                    'Reboot ONU dari dashboard',
                    'Periksa log OLT',
                    'Monitor selama 15-30 menit',
                    'Jika persisten, turun ke lokasi',
                ],
                'confidence' => 50,
            ];
        }

        usort($diagnoses, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $diagnoses;
    }
}
