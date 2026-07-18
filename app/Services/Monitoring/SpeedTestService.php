<?php

namespace App\Services\Monitoring;

use App\Models\Olt;
use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Support\Facades\Log;

class SpeedTestService
{
    public function __construct(
        private readonly HealthScoreService $healthService = new HealthScoreService,
    ) {}

    public function estimateThroughput(Olt $olt, ?string $onuId = null): array
    {
        try {
            $connector = $this->getConnector($olt);

            if ($connector === null) {
                return $this->getUnsupportedResult('Tidak dapat terhubung ke OLT');
            }

            $olt->fresh();

            return $this->getUnsupportedResult(
                'Speed test langsung dari OLT tidak tersedia untuk '.($olt->brand ?? 'unknown').
                '. Hasil adalah estimasi berdasarkan kondisi optical power dan status link.'
            );
        } catch (\Throwable $e) {
            Log::error("SpeedTestService estimateThroughput failed: {$e->getMessage()}");

            return $this->getUnsupportedResult('Gagal mengambil data throughput: '.$e->getMessage());
        }
    }

    public function getEstimateFromOptical(array $onuData): array
    {
        $rx = $onuData['rx_power'] ?? null;
        $status = strtolower($onuData['status'] ?? 'unknown');

        if ($status !== 'online' || $rx === null) {
            return [
                'supported' => true,
                'type' => 'estimate',
                'message' => 'Estimasi berdasarkan optical power dan status link',
                'download_mbps' => 0,
                'upload_mbps' => 0,
                'latency_ms' => null,
                'packet_loss' => null,
                'jitter' => null,
                'bandwidth_utilization' => 0,
                'estimated' => true,
            ];
        }

        $qualityScore = max(0, min(100, ($rx + 30) * 5));

        $maxDown = 100;
        $maxUp = 50;
        $estDown = round($maxDown * ($qualityScore / 100), 1);
        $estUp = round($maxUp * ($qualityScore / 100), 1);

        return [
            'supported' => true,
            'type' => 'estimate',
            'message' => 'Estimasi berdasarkan kualitas sinyal RX: '.number_format($rx, 1).' dBm',
            'download_mbps' => $estDown,
            'upload_mbps' => $estUp,
            'latency_ms' => round(2 + (28 - max($rx, -28)) * 0.5, 1),
            'packet_loss' => $rx < -28 ? round(abs($rx + 28) * 2, 1) : 0,
            'jitter' => round(max(0, ($rx + 25) * -0.3), 1),
            'bandwidth_utilization' => round($qualityScore, 1),
            'estimated' => true,
        ];
    }

    private function getConnector(Olt $olt): ?OltConnector
    {
        try {
            return OltConnectorFactory::make($olt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getUnsupportedResult(string $message): array
    {
        return [
            'supported' => false,
            'type' => 'unsupported',
            'message' => $message,
            'download_mbps' => null,
            'upload_mbps' => null,
            'latency_ms' => null,
            'packet_loss' => null,
            'jitter' => null,
            'bandwidth_utilization' => null,
            'estimated' => true,
        ];
    }
}
