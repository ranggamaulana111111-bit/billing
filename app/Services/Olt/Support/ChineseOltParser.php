<?php

namespace App\Services\Olt\Support;

/**
 * Tolerant parser for the "Chinese mini-OLT" CLI family
 * (VSOL / BDCom, Hioso, Global / Globtel, HSGQ, OPTIWAY, Cortina, ...).
 *
 * The same underlying show commands return slightly different layouts per
 * vendor/firmware, so we parse with several known patterns instead of a
 * single strict format.
 */
class ChineseOltParser
{
    /**
     * Known status words and their normalized mapping.
     *
     * @return array<string, string>
     */
    public static function statusMap(): array
    {
        return [
            'online' => 'online',
            'on-line' => 'online',
            'up' => 'online',
            'working' => 'online',
            'active' => 'online',
            'normal' => 'online',
            'good' => 'online',
            'auth-ok' => 'online',
            'register' => 'online',
            'registered' => 'online',
            'offline' => 'offline',
            'off-line' => 'offline',
            'down' => 'offline',
            'los' => 'offline',
            'dying' => 'offline',
            'dying-gasp' => 'offline',
            'lost' => 'offline',
            'auto-find' => 'discover',
            'discover' => 'discover',
            'unregister' => 'discover',
            'unregistered' => 'discover',
            'intrusion' => 'alarm',
            'equalization' => 'alarm',
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        $raw = strtolower(trim($status));

        if ($raw === '') {
            return 'unknown';
        }

        foreach (self::statusMap() as $needle => $mapped) {
            if (str_contains($raw, $needle)) {
                return $mapped;
            }
        }

        return 'unknown';
    }

    /**
     * Parse a full ONU listing into a normalized array.
     *
     * Supported layouts:
     *   - VSOL/BDCom:  EPON0/1:1   online   78:d7:52:de:cf:7a
     *   - VSOL basic:  EPON0/1:1   HWTC      010H    78D752DECF7A
     *   - Avies:       1/1  a2:3e:...  Offline
     *   - OPTIWAY:     3/4/1 00:0a:... 54 ... UP
     *   - Cortina/Avies online-only: onu-12 e0:67:... 2.0 30 6m
     *
     * @return array<int, array{
     *     onu_id: string,
     *     slot: int,
     *     port: int,
     *     ont_id: int,
     *     sn: string|null,
     *     mac: string|null,
     *     status: string,
     *     mode: string|null,
     * }>
     */
    public static function parseOnus(string $output): array
    {
        $onus = [];
        $seen = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            // Header lines
            if (preg_match('/^(epon|gpon)\d+\/\d+\s+onu/i', $line)
                || preg_match('/^onu\s+(?:id|sn|mac)/i', $line)
                || preg_match('/^(f\/s|index)\b/i', $line)) {
                continue;
            }

            // Format 1a: BDCom "show (e|g)pon onu-information":
            //   "epon 0/1  1  78:d7:52:de:cf:7a  HWTCDE110A  Online"
            if (preg_match('/^(?:epon|gpon)\s+(\d+)\/(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/i', $line, $m)) {
                $status = self::normalizeStatus($m[6]);
                $mac = self::looksLikeMac($m[4]) ? $m[4] : null;
                $sn = $status === 'unknown' ? $m[4] : $m[5];

                self::rememberOnu($onus, $seen, (int) $m[1], (int) $m[2], (int) $m[3], $sn, $mac, $status);

                continue;
            }

            // Format 1: "EPON0/1:1  <status>  <sn-or-mac>"  (VSOL/BDCom status output)
            if (preg_match('/^(?:[A-Za-z]+)?(\d+)\/(\d+):(\d+)\s+(\S+)(?:\s+(\S+))?(?:\s+(\S+))?/', $line, $m)) {
                $ontId = (int) $m[3];
                $first = $m[4];
                $second = $m[5] ?? null;
                $third = $m[6] ?? null;

                $status = self::normalizeStatus($first);
                $sn = null;
                $mac = null;

                if ($status !== 'unknown') {
                    // "EPON0/1:1  online  78:d7:52:de:cf:7a"
                    if ($second !== null && self::looksLikeMac($second)) {
                        $mac = $second;
                    } elseif ($second !== null && self::isSerial($second)) {
                        $sn = $second;
                    }
                } else {
                    // basic-info: "EPON0/1:1  HWTC  010H  78D752DECF7A"
                    if ($third !== null && self::isSerial($third)) {
                        $sn = $third;
                    } elseif ($second !== null && self::isSerial($second)) {
                        $sn = $second;
                    }
                }

                self::rememberOnu($onus, $seen, (int) $m[1], (int) $m[2], $ontId, $sn, $mac, $status);

                continue;
            }

            // Format 2: "3/4/1 00:0a:5a:12:46:59 54 ... UP"  (OPTIWAY onu-status)
            if (preg_match('/^(\d+)\/(\d+)\/(\d+)\s+(\S+)(?:\s+\S+){3,6}\s+(UP|DOWN|LOS)\b/i', $line, $m)) {
                self::rememberOnu($onus, $seen, (int) $m[1], (int) $m[2], (int) $m[3], null, $m[4], self::normalizeStatus($m[5]));

                continue;
            }

            // Format 3: "1/1  a2:3e:...  Offline"  (Avies / bare slot/port + status)
            if (preg_match('/^(\d+)\/(\d+)\s+(\S+)\s+(Online|Offline|LOS|Down|Up|Normal|Active)/i', $line, $m)) {
                $mac = $m[3];
                $status = self::normalizeStatus($m[4]);

                self::rememberOnu($onus, $seen, (int) $m[1], (int) $m[2], 1, null, $mac, $status);

                continue;
            }

            // Format 4: "onu-12 e0:67:... 2.0 30 6m"  (Cortina/Avies online-only list)
            if (preg_match('/^onu-(\d+)\s+(\S+)\s+\S+\s+\S+\s+\S+/', $line, $m)) {
                self::rememberOnu($onus, $seen, 0, 0, (int) $m[1], null, $m[2], 'online');

                continue;
            }
        }

        return array_values($onus);
    }

    /**
     * Parse a "PON totals" table used by BDCom-style OLTs:
     *
     *   PON        Total ONUs    Online ONUs
     *   EPON0/1    43            29
     *   EPON0/2    12            8
     *
     * @return array<int, array{slot: int, port: int, total: int, online: int}>
     */
    public static function parsePonTotals(string $output): array
    {
        $totals = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            if (preg_match('/^(?:EPON|GPON)?(\d+)\/(\d+)\s+(\d+)\s+(\d+)/i', $line, $m)) {
                $totals[] = [
                    'slot' => (int) $m[1],
                    'port' => (int) $m[2],
                    'total' => (int) $m[3],
                    'online' => (int) $m[4],
                ];
            }
        }

        return $totals;
    }

    private static function rememberOnu(array &$onus, array &$seen, int $slot, int $port, int $ontId, ?string $sn, ?string $mac, string $status): void
    {
        $key = "{$slot}/{$port}/{$ontId}";

        if (isset($seen[$key])) {
            $idx = $seen[$key];

            // Prefer richer info: fill missing SN, prefer real status over unknown
            if ($sn !== null && $onus[$idx]['sn'] === null) {
                $onus[$idx]['sn'] = $sn;
            }
            if ($mac !== null && $onus[$idx]['mac'] === null) {
                $onus[$idx]['mac'] = $mac;
            }
            if ($status !== 'unknown' && $onus[$idx]['status'] === 'unknown') {
                $onus[$idx]['status'] = $status;
            }

            return;
        }

        $seen[$key] = count($onus);
        $onus[] = [
            'onu_id' => $key,
            'slot' => $slot,
            'port' => $port,
            'ont_id' => $ontId,
            'sn' => $sn,
            'mac' => $mac,
            'status' => $status,
            'mode' => null,
        ];
    }

    private static function isSerial(string $value): bool
    {
        return preg_match('/^[A-Z0-9]{8,16}$/i', $value) === 1 && ! self::looksLikeMac($value);
    }

    private static function looksLikeMac(string $value): bool
    {
        return preg_match('/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/', $value) === 1;
    }
}
