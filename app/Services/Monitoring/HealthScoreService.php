<?php

namespace App\Services\Monitoring;

class HealthScoreService
{
    public function calculate(array $data): array
    {
        $score = 100;
        $factors = [];

        $rx = $data['rx_power'] ?? null;
        $tx = $data['tx_power'] ?? null;
        $status = strtolower($data['status'] ?? 'unknown');
        $temperature = $data['temperature'] ?? null;
        $voltage = $data['voltage'] ?? null;
        $biasCurrent = $data['bias_current'] ?? null;
        $losDetected = $data['los_detected'] ?? false;
        $dyingGasp = $data['dying_gasp_detected'] ?? false;
        $authFailed = $data['auth_failed'] ?? false;
        $rogueDetected = $data['rogue_detected'] ?? false;

        if ($status === 'offline') {
            $score -= 40;
            $factors[] = ['factor' => 'ONU Offline', 'impact' => -40, 'severity' => 'critical'];
        }

        if ($status === 'los') {
            $score -= 50;
            $factors[] = ['factor' => 'Loss of Signal', 'impact' => -50, 'severity' => 'critical'];
        }

        if ($status === 'dying-gasp') {
            $score -= 45;
            $factors[] = ['factor' => 'Dying Gasp Detected', 'impact' => -45, 'severity' => 'critical'];
        }

        if ($authFailed) {
            $score -= 35;
            $factors[] = ['factor' => 'Authentication Failed', 'impact' => -35, 'severity' => 'critical'];
        }

        if ($rogueDetected) {
            $score -= 30;
            $factors[] = ['factor' => 'Rogue ONU Detected', 'impact' => -30, 'severity' => 'critical'];
        }

        if ($rx !== null) {
            if ($rx < -28) {
                $score -= 30;
                $factors[] = ['factor' => 'RX Power Critical ('.number_format($rx, 1).' dBm)', 'impact' => -30, 'severity' => 'critical'];
            } elseif ($rx < -25) {
                $score -= 15;
                $factors[] = ['factor' => 'RX Power Warning ('.number_format($rx, 1).' dBm)', 'impact' => -15, 'severity' => 'warning'];
            } elseif ($rx < -22) {
                $score -= 5;
                $factors[] = ['factor' => 'RX Power Fair ('.number_format($rx, 1).' dBm)', 'impact' => -5, 'severity' => 'good'];
            } else {
                $factors[] = ['factor' => 'RX Power Excellent ('.number_format($rx, 1).' dBm)', 'impact' => 0, 'severity' => 'excellent'];
            }
        }

        if ($tx !== null) {
            if ($tx < -1 || $tx > 7) {
                $score -= 10;
                $factors[] = ['factor' => 'TX Power Abnormal ('.number_format($tx, 1).' dBm)', 'impact' => -10, 'severity' => 'warning'];
            }
        }

        if ($temperature !== null) {
            if ($temperature > 70) {
                $score -= 20;
                $factors[] = ['factor' => 'Temperature Critical ('.number_format($temperature, 1).'°C)', 'impact' => -20, 'severity' => 'critical'];
            } elseif ($temperature > 55) {
                $score -= 10;
                $factors[] = ['factor' => 'Temperature High ('.number_format($temperature, 1).'°C)', 'impact' => -10, 'severity' => 'warning'];
            }
        }

        if ($voltage !== null && $voltage < 3.0) {
            $score -= 15;
            $factors[] = ['factor' => 'Voltage Low ('.number_format($voltage, 2).' V)', 'impact' => -15, 'severity' => 'warning'];
        }

        if ($biasCurrent !== null && $biasCurrent > 80) {
            $score -= 10;
            $factors[] = ['factor' => 'Bias Current High ('.number_format($biasCurrent, 1).' mA)', 'impact' => -10, 'severity' => 'warning'];
        }

        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Warning',
            default => 'Critical',
        };

        $color = match ($grade) {
            'Excellent' => 'success',
            'Good' => 'info',
            'Warning' => 'warning',
            default => 'danger',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'color' => $color,
            'factors' => $factors,
        ];
    }

    public function getStatusBadge(string $status): array
    {
        return match (strtolower($status)) {
            'online' => ['label' => 'Online', 'color' => 'success'],
            'offline' => ['label' => 'Offline', 'color' => 'danger'],
            'los' => ['label' => 'LOS', 'color' => 'warning'],
            'dying-gasp' => ['label' => 'Dying Gasp', 'color' => 'warning'],
            'auth-failed' => ['label' => 'Auth Failed', 'color' => 'danger'],
            'registered' => ['label' => 'Registered', 'color' => 'success'],
            'rogue' => ['label' => 'Rogue ONU', 'color' => 'danger'],
            'disabled' => ['label' => 'Disabled', 'color' => 'secondary'],
            'provisioning' => ['label' => 'Provisioning', 'color' => 'primary'],
            default => ['label' => ucfirst($status), 'color' => 'secondary'],
        };
    }
}
