<?php

namespace App\Modules\GenieACS\Services;

use App\Models\MikrotikRouter;
use App\Models\Onu;
use App\Models\OnuMonitoringHistory;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Repositories\GenieACSRepository;
use App\Services\Mikrotik\RouterCommandService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates GenieACS device data with local network context (ONU/OLT
 * optical values, monitoring temperature, MikroTik PPPoE sessions) and
 * computes the NOC overview statistics.
 */
class AcsCatalogService
{
    /** Device dianggap online bila last inform dalam 10 menit terakhir. */
    public const ONLINE_WINDOW = 600;

    /** CWMP subtree yang diambil untuk overview & tabel device. */
    public const PROJECTION = [
        '_id',
        '_deviceId',
        '_lastInform',
        '_lastBoot',
        '_tags',
        'InternetGatewayDevice.DeviceInfo',
        'InternetGatewayDevice.WANDevice',
        'InternetGatewayDevice.LANDevice',
        'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
    ];

    private const DAY = 86400;

    private const BRAND_MAP = [
        'huawei' => 'Huawei',
        'technicolor' => 'Huawei',
        'fiberhome' => 'FiberHome',
        'fibrehome' => 'FiberHome',
        'totolink' => 'TOTOLINK',
        'zte' => 'ZTE',
        'mikrotik' => 'MikroTik',
        'routerboard' => 'MikroTik',
    ];

    private const COLORS = [
        'on' => '#22c55e',
        'off' => '#94a3b8',
        'off_24h' => '#f59e0b',
        'off_3d' => '#fb923c',
        'off_7d' => '#ef4444',
        'off_30d' => '#7f1d1d',
        'GPON' => '#2563eb',
        'EPON' => '#8b5cf6',
        'Ethernet/Converter' => '#059669',
        'Bagus' => '#22c55e',
        'Sedang' => '#f59e0b',
        'Kritis' => '#dc2626',
        'Huawei' => '#c2410c',
        'FiberHome' => '#0ea5e9',
        'TOTOLINK' => '#f97316',
        'ZTE' => '#6366f1',
        'MikroTik' => '#14b8a6',
        'Other' => '#64748b',
        'Hari Ini' => '#22c55e',
        '< 7 hari' => '#f59e0b',
        '< 30 hari' => '#2563eb',
        '>= 30 hari' => '#ef4444',
        'Adem' => '#22c55e',
        'Anget' => '#f59e0b',
        'Puanass' => '#f97316',
        'Awas' => '#dc2626',
    ];

    public function __construct(
        private readonly IGenieACSClient $client,
        private readonly GenieACSRepository $repo,
    ) {}

    // ── Fetch ──────────────────────────────────────────────

    /**
     * Fetch devices from GenieACS with the targeted projection.
     *
     * @param  array<string, string>  $filters
     */
    public function fetchDevices(array $filters = [], int $limit = 0, int $skip = 0): array
    {
        return $this->repo->projectedDevices($filters, self::PROJECTION, $limit, $skip);
    }

    /**
     * @param  array<string, string>  $filters
     */
    public function countDevices(array $filters = []): int
    {
        return $this->repo->countDevices($filters);
    }

    /**
     * Build per-request enrichment context (ONU maps, latest temperature, PPPoE index).
     *
     * @return array{
     *     onu_by_id: array<string, Onu>,
     *     onu_by_serial: array<string, Onu>,
     *     temps: array<int, object>,
     *     pppoe: array<string, string|null>,
     * }
     */
    public function context(): array
    {
        $onus = Onu::fromOlt()
            ->with(['oltPort.olt', 'customer'])
            ->get();

        $onuById = [];
        $onuBySerial = [];

        foreach ($onus as $onu) {
            if (filled($onu->acs_device_id)) {
                $onuById[$this->normalizeKey($onu->acs_device_id)] = $onu;
            }
            if (filled($onu->serial_number)) {
                $onuBySerial[$this->normalizeKey($onu->serial_number)] = $onu;
            }
        }

        return [
            'onu_by_id' => $onuById,
            'onu_by_serial' => $onuBySerial,
            'temps' => $this->latestTemps($onus->pluck('id')),
            'pppoe' => $this->pppoeIndex(),
        ];
    }

    /**
     * Enrich raw GenieACS device rows with ONU/OLT context + readability helpers.
     *
     * @param  array<int, array<string, mixed>>  $devices
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function enrichMany(array $devices, array $context): array
    {
        $rows = [];

        foreach ($devices as $device) {
            $row = $this->enrich($device, $context);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $dev
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    public function enrich(array $dev, array $context): ?array
    {
        $deviceId = (string) ($dev['_id'] ?? 'unknown');
        $serial = $this->resolveSerial($dev, $deviceId);

        $manufacturer = $this->deviceIdMeta($dev, '_Manufacturer')
            ?? $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.Manufacturer');
        $productClass = $this->deviceIdMeta($dev, '_ProductClass')
            ?? $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.ProductClass');

        $onu = $this->matchOnu($deviceId, $serial, $context);

        $lastInform = $this->toCarbon($dev['_lastInform'] ?? null);
        $online = $lastInform !== null
            && (time() - $lastInform->getTimestamp()) < self::ONLINE_WINDOW;

        $ssid = $this->acsValue($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID')
            ?: ($onu->wifi_ssid ?? null);
        $wifiEnabled = $this->acsValue($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable');

        $wan = $this->wanAnalysis($dev);
        $wanIp = $wan['wan_ip'] ?: $this->tr069UrlIp($dev);
        $mac = $this->normalizeMac($wan['mac'])
            ?: $this->normalizeMac($this->acsValue($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.MACAddress'))
            ?: $this->normalizeMac($this->firstMacInTree($dev['InternetGatewayDevice']['LANDevice'] ?? null))
            ?: $this->normalizeMac($onu->mac_address ?? null);

        $uptime = (int) ($this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.Uptime')
            ?? $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.UpTime')
            ?? $onu->uptime
            ?? 0);

        return [
            'device_id' => $deviceId,
            'serial' => $serial,
            'manufacturer' => $manufacturer ?: ($onu->vendor ?? null),
            'product_class' => $productClass ?: ($onu->model ?? null),
            'model' => $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.ModelName') ?: ($onu->model ?? null),
            'ssid' => $ssid,
            'wifi_enabled' => $wifiEnabled,
            'wifi_clients' => $this->countChildren($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice'),
            'mac' => $mac,
            'wan_ip' => $wanIp,
            'pppoe_username' => filled($wan['username']) ? $wan['username'] : null,
            'pppoe_ip' => $wan['pppoe_ip'] ?: $this->pppoeIp($onu, $context),
            'rx_power' => $onu?->rx_power,
            'temperature' => $this->temperatureFromAcs($dev)
                ?? (isset($onu) ? ($context['temps'][$onu->id]->temperature ?? null) : null),
            'uptime' => $uptime,
            'uptime_human' => $uptime > 0 ? $this->humanUptime($uptime) : null,
            'last_inform' => $lastInform,
            'online' => $online,
            'status_bucket' => $this->statusBucket($lastInform),
            'reg_time' => $onu?->created_at ?? $this->registeredOn($dev['_tags'] ?? null),
            'access_type' => $this->accessType($onu),
            'pon' => $this->ponLabel($onu),
            'onu_id' => $onu?->onu_id,
            'customer' => $onu?->customer,
            'onu' => $onu,
        ];
    }

    // ── Parameter yang Bisa Dikonfigurasi (WAN/LAN/WLAN) ───

    /**
     * Daftar leaf writable di bawah WANDevice & LANDevice (termasuk
     * WLANConfiguration). Dipakai untuk membangun form konfigurasi dan
     * memvalidasi path saat kirim setParameterValues.
     *
     * @param  array<string, mixed>  $device
     * @return array<string, string> full CWMP path => xsd type
     */
    public function writableLeaves(array $device): array
    {
        $map = [];
        $ig = $device['InternetGatewayDevice'] ?? $device;
        $this->collectWritable($ig['WANDevice'] ?? null, 'InternetGatewayDevice.WANDevice', $map);
        $this->collectWritable($ig['LANDevice'] ?? null, 'InternetGatewayDevice.LANDevice', $map);
        $this->collectWritable($ig['ManagementServer'] ?? null, 'InternetGatewayDevice.ManagementServer', $map);

        return $map;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function collectWritable(mixed $node, string $path, array &$map): void
    {
        if (! is_array($node)) {
            return;
        }

        $isLeaf = ($node['_object'] ?? true) === false;

        if ($isLeaf) {
            if (($node['_writable'] ?? false) === true) {
                $type = (string) ($node['_type'] ?? 'xsd:string');

                $map[$path] = $type;
            }

            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                continue;
            }

            if (is_array($value)) {
                $this->collectWritable($value, $path.'.'.$key, $map);
            }
        }
    }

    // ── Ringkasan Detail Device ─────────────────────────────

    /**
     * Ringkas informasi perangkat untuk halaman detail (ping, kartu info,
     * tab WAN/LAN/WLAN/USER/TR069).
     *
     * @param  array<string, mixed>  $dev
     * @return array{
     *     device_id: string,
     *     serial: string|null,
     *     oui: string|null,
     *     product_class: string|null,
     *     manufacturer: string|null,
     *     model: string|null,
     *     hardware: string|null,
     *     software: string|null,
     *     uptime: int,
     *     uptime_human: string|null,
     *     last_inform: Carbon|null,
     *     online: bool,
     *     ssid: string|null,
     *     wan_ip: string|null,
     *     pppoe_ip: string|null,
     *     pppoe_username: string|null,
     *     mac: string|null,
     *     connection_request_url: string|null,
     * }
     */
    public function summarize(array $dev): array
    {
        $deviceId = (string) ($dev['_id'] ?? 'unknown');
        $did = $dev['_deviceId'] ?? [];
        $didVal = fn (string $key): mixed => is_array($did) ? ($did[$key] ?? null) : null;

        $infoVal = fn (string $key): mixed => $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.'.$key);
        $wan = $this->wanAnalysis($dev);

        $lastInform = $this->toCarbon($dev['_lastInform'] ?? null);
        $online = $lastInform !== null
            && (time() - $lastInform->getTimestamp()) < self::ONLINE_WINDOW;

        $uptime = (int) ($this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.Uptime')
            ?? $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.UpTime')
            ?? 0);

        $oui = $didVal('_OUI');

        if (! filled($oui)) {
            $parts = explode('-', $deviceId);
            $oui = $parts[0] ?? null;
        }

        $mac = $this->normalizeMac($wan['mac'])
            ?: $this->normalizeMac($this->acsValue($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.MACAddress'))
            ?: $this->normalizeMac($this->firstMacInTree($dev['InternetGatewayDevice']['LANDevice'] ?? null));

        return [
            'device_id' => $deviceId,
            'serial' => $this->resolveSerial($dev, $deviceId),
            'oui' => is_scalar($oui) ? (string) $oui : null,
            'product_class' => is_scalar($didVal('_ProductClass')) ? (string) $didVal('_ProductClass') : null,
            'manufacturer' => is_scalar($didVal('_Manufacturer')) ? (string) $didVal('_Manufacturer') : null,
            'model' => is_scalar($infoVal('ModelName')) ? (string) $infoVal('ModelName') : null,
            'hardware' => is_scalar($infoVal('HardwareVersion')) ? (string) $infoVal('HardwareVersion') : null,
            'software' => is_scalar($infoVal('SoftwareVersion')) ? (string) $infoVal('SoftwareVersion') : null,
            'uptime' => $uptime,
            'uptime_human' => $uptime > 0 ? $this->humanUptime($uptime) : null,
            'last_inform' => $lastInform,
            'online' => $online,
            'ssid' => $this->acsValue($dev, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'),
            'wan_ip' => $wan['wan_ip'] ?: $this->tr069UrlIp($dev),
            'pppoe_ip' => $wan['pppoe_ip'],
            'pppoe_username' => filled($wan['username']) ? $wan['username'] : null,
            'mac' => $mac,
            'connection_request_url' => $this->acsValue($dev, 'InternetGatewayDevice.ManagementServer.ConnectionRequestURL'),
        ];
    }

    // ── WAN / MAC / Temperature dari CWMP ────────────────

    /**
     * Rangkum koneksi WAN dari subtree CWMP: pisahkan IP PPPoE, IP TR069,
     * MAC & username. Rotation koneksi (WANIPConnection/WANPPPConnection)
     * di-scan semua instance agar tahan jenis ONT/router yang beda-beda.
     *
     * @param  array<string, mixed>  $dev
     * @return array{pppoe_ip: string|null, wan_ip: string|null, mac: string|null, username: string|null}
     */
    private function wanAnalysis(array $dev): array
    {
        $connections = [];
        $wan = $dev['InternetGatewayDevice']['WANDevice'] ?? null;

        if (is_array($wan)) {
            foreach ($wan as $wanKey => $wanDevice) {
                if (str_starts_with((string) $wanKey, '_')) {
                    continue;
                }

                $connDevices = is_array($wanDevice) ? ($wanDevice['WANConnectionDevice'] ?? null) : null;

                if (! is_array($connDevices)) {
                    continue;
                }

                foreach ($connDevices as $connKey => $connDevice) {
                    if (str_starts_with((string) $connKey, '_')) {
                        continue;
                    }

                    foreach (['WANIPConnection', 'WANPPPConnection'] as $type) {
                        $conns = is_array($connDevice) ? ($connDevice[$type] ?? null) : null;

                        if (! is_array($conns)) {
                            continue;
                        }

                        foreach ($conns as $conn) {
                            if (is_array($conn)) {
                                $connections[] = ['type' => $type, 'node' => $conn];
                            }
                        }
                    }
                }
            }
        }

        $ipOf = function (array $node): ?string {
            $ip = $this->nodeValue($node, 'ExternalIPAddress');

            if ($this->validIp((string) $ip)) {
                return (string) $ip;
            }

            $ip = $this->nodeValue($node, 'IPAddress');

            return $this->validIp((string) $ip) ? (string) $ip : null;
        };

        $pppoeIp = null;
        $wanIp = null;
        $mac = null;
        $username = null;

        foreach ($connections as $conn) {
            $node = $conn['node'];
            $connType = mb_strtolower((string) $this->nodeValue($node, 'ConnectionType'));
            $service = mb_strtolower((string) $this->nodeValue($node, 'X_CMCC_ServiceList'));
            $name = mb_strtolower((string) $this->nodeValue($node, 'Name'));
            $ip = $ipOf($node);

            $mac ??= $this->nodeValue($node, 'MACAddress');
            $username ??= $this->nodeValue($node, 'Username');

            if ($ip === null) {
                continue;
            }

            if ($conn['type'] === 'WANPPPConnection' || str_contains($connType, 'ppp')) {
                $pppoeIp ??= $ip;

                continue;
            }

            if ($wanIp === null && (str_contains($service, 'tr069') || str_contains($name, 'tr069'))) {
                $wanIp = $ip;
            }
        }

        if ($pppoeIp === null) {
            foreach ($connections as $conn) {
                $node = $conn['node'];
                $service = mb_strtolower((string) $this->nodeValue($node, 'X_CMCC_ServiceList'));
                $name = mb_strtolower((string) $this->nodeValue($node, 'Name'));
                $ip = $ipOf($node);

                if ($ip !== null && (str_contains($service, 'internet') || str_contains($name, 'internet'))) {
                    $pppoeIp = $ip;
                    break;
                }
            }
        }

        $fallbackWan = null;

        foreach ($connections as $conn) {
            $ip = $ipOf($conn['node']);

            if ($ip === null) {
                continue;
            }

            $fallbackWan ??= $ip;

            if ($wanIp === null && $ip !== $pppoeIp) {
                $wanIp = $ip;
            }
        }

        $wanIp ??= $fallbackWan;

        return [
            'pppoe_ip' => $pppoeIp,
            'wan_ip' => $wanIp,
            'mac' => is_string($mac) ? $mac : null,
            'username' => is_string($username) ? $username : null,
        ];
    }

    /**
     * IP host dari ManagementServer.ConnectionRequestURL (fallback IP TR069).
     *
     * @param  array<string, mixed>  $dev
     */
    private function tr069UrlIp(array $dev): ?string
    {
        $url = $this->acsValue($dev, 'InternetGatewayDevice.ManagementServer.ConnectionRequestURL');

        if (! is_string($url)) {
            return null;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        return $this->validIp($host) ? $host : null;
    }

    private function normalizeMac(mixed $mac): ?string
    {
        if (! is_string($mac) || trim($mac) === '') {
            return null;
        }

        $mac = strtoupper(trim($mac));

        if (str_contains($mac, '-')) {
            $mac = str_replace('-', ':', $mac);
        }

        return $mac;
    }

    /**
     * MAC perangkat dari subtree LAN (LANHostConfigManagement/WLANConfig/Interface),
     * kecuali subtree Hosts yang berisi MAC klien WiFi/LAN.
     *
     * @param  array<string, mixed>|null  $node
     */
    private function firstMacInTree(?array $node): ?string
    {
        if (! is_array($node)) {
            return null;
        }

        foreach ($node as $key => $value) {
            if (str_starts_with((string) $key, '_') || $key === 'Hosts') {
                continue;
            }

            if ($key === 'MACAddress') {
                $raw = is_array($value) ? ($value['_value'] ?? null) : $value;

                if (is_string($raw) && preg_match('/^([0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/i', trim($raw))) {
                    return (string) $raw;
                }
            }

            if (is_array($value)) {
                $found = $this->firstMacInTree($value);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Suhu optik dari CWMP (mis. TransceiverTemperature vendor, skala x100),
     * fallback ke riwayat monitoring ONU.
     *
     * @param  array<string, mixed>  $dev
     */
    private function temperatureFromAcs(array $dev): ?float
    {
        $candidates = [];
        // VirtualParameters.gettemp adalah hasil kalkulasi provision yang paling akurat (sudah dalam °C)
        $this->collectTemperature($dev['VirtualParameters'] ?? null, $candidates, 'VirtualParameters');
        $this->collectTemperature($dev['InternetGatewayDevice']['DeviceInfo'] ?? null, $candidates, 'InternetGatewayDevice.DeviceInfo');
        $this->collectTemperature($dev['InternetGatewayDevice']['WANDevice'] ?? null, $candidates, 'InternetGatewayDevice.WANDevice');
        $this->collectTemperature($dev['InternetGatewayDevice']['X_ALU_OntOpticalParam'] ?? null, $candidates, 'InternetGatewayDevice.X_ALU_OntOpticalParam');

        foreach ($candidates as $value) {
            if ($value >= 0 && $value <= 200) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<float>  $out
     */
    private function collectTemperature(mixed $node, array &$out, string $path = ''): void
    {
        if (! is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $curPath = $path !== '' ? $path.'.'.$key : (string) $key;

            if (str_contains(mb_strtolower((string) $key), 'temp')) {
                $raw = is_array($value) ? ($value['_value'] ?? null) : $value;

                if (is_numeric($raw)) {
                    $n = (float) $raw;

                    if (abs($n) >= 1000) {
                        // ZTE/FiberHome X_CT-COM_GponInterfaceConfig melaporkan suhu skala 1/256 °C
                        // (mis. 8640 => 33.75°C), sementara vendor lain skala 1/100 (3200 => 32°C)
                        if (str_contains(mb_strtolower($curPath), 'x_ct-com')) {
                            $n /= 256;
                        } else {
                            $n /= 100;
                        }
                    }

                    $out[] = round($n, 1);
                }
            }

            if (is_array($value)) {
                $this->collectTemperature($value, $out, $curPath);
            }
        }
    }

    private function nodeValue(mixed $node, string $key): mixed
    {
        if (! is_array($node)) {
            return null;
        }

        $value = $node[$key] ?? null;

        return is_array($value) ? ($value['_value'] ?? null) : $value;
    }

    private function validIp(string $value): bool
    {
        return $value !== '' && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    // ── Overview ───────────────────────────────────────────

    /**
     * Aggregate enriched rows into the overview stat panels.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{title: string, total: int, items: array<int, array{label: string, value: int, pct: float, color: string}>}>
     */
    public function overviewStats(array $rows): array
    {
        $status = [];
        $brands = [];
        $access = [];
        $rx = [];
        $temp = [];
        $reg = [];

        foreach ($rows as $row) {
            $bucket = $row['status_bucket'] ?? 'off_30d';
            $status[$bucket] = ($status[$bucket] ?? 0) + 1;

            $brand = $this->brandKey($row['manufacturer'] ?? null);
            $brands[$brand] = ($brands[$brand] ?? 0) + 1;

            $accessType = $row['access_type'] ?? 'Ethernet/Converter';
            $access[$accessType] = ($access[$accessType] ?? 0) + 1;

            if (($row['rx_power'] ?? null) !== null && (float) $row['rx_power'] != 0.0) {
                $key = $this->rxKey((float) $row['rx_power']);
                $rx[$key] = ($rx[$key] ?? 0) + 1;
            }

            if (($row['temperature'] ?? null) !== null) {
                $key = $this->tempKey((float) $row['temperature']);
                $temp[$key] = ($temp[$key] ?? 0) + 1;
            }

            if (($row['reg_time'] ?? null) instanceof Carbon) {
                $key = $this->regKey($row['reg_time']);
                $reg[$key] = ($reg[$key] ?? 0) + 1;
            }
        }

        $total = count($rows);

        return [
            'status' => $this->panel('Status', $total, [
                $this->statItem('on', $status['on'] ?? 0, $total, self::COLORS['on']),
                $this->statItem('off', $status['off'] ?? 0, $total, self::COLORS['off']),
                $this->statItem('off > 24h', $status['off_24h'] ?? 0, $total, self::COLORS['off_24h']),
                $this->statItem('off > 3d', $status['off_3d'] ?? 0, $total, self::COLORS['off_3d']),
                $this->statItem('off > 7d', $status['off_7d'] ?? 0, $total, self::COLORS['off_7d']),
                $this->statItem('off > 30d', $status['off_30d'] ?? 0, $total, self::COLORS['off_30d']),
            ]),
            'access' => $this->panel('Access Type', array_sum($access), [
                $this->statItem('GPON', $access['GPON'] ?? 0, $total, self::COLORS['GPON']),
                $this->statItem('EPON', $access['EPON'] ?? 0, $total, self::COLORS['EPON']),
                $this->statItem('Ethernet/Converter', $access['Ethernet/Converter'] ?? 0, $total, self::COLORS['Ethernet/Converter']),
            ]),
            'rx' => $this->panel('Optical RX', array_sum($rx), [
                $this->statItem('Bagus', $rx['Bagus'] ?? 0, array_sum($rx), self::COLORS['Bagus']),
                $this->statItem('Sedang', $rx['Sedang'] ?? 0, array_sum($rx), self::COLORS['Sedang']),
                $this->statItem('Kritis', $rx['Kritis'] ?? 0, array_sum($rx), self::COLORS['Kritis']),
            ]),
            'brands' => $this->panel('Merk Perangkat', $total, [
                $this->statItem('Huawei', $brands['Huawei'] ?? 0, $total, self::COLORS['Huawei']),
                $this->statItem('FiberHome', $brands['FiberHome'] ?? 0, $total, self::COLORS['FiberHome']),
                $this->statItem('TOTOLINK', $brands['TOTOLINK'] ?? 0, $total, self::COLORS['TOTOLINK']),
                $this->statItem('ZTE', $brands['ZTE'] ?? 0, $total, self::COLORS['ZTE']),
                $this->statItem('MikroTik', $brands['MikroTik'] ?? 0, $total, self::COLORS['MikroTik']),
                $this->statItem('Other', $brands['Other'] ?? 0, $total, self::COLORS['Other']),
            ]),
            'register' => $this->panel('Devices Register', $total, [
                $this->statItem('Register Hari Ini', $reg['Hari Ini'] ?? 0, $total, self::COLORS['Hari Ini']),
                $this->statItem('Register < 7 hari', $reg['< 7 hari'] ?? 0, $total, self::COLORS['< 7 hari']),
                $this->statItem('Register < 30 hari', $reg['< 30 hari'] ?? 0, $total, self::COLORS['< 30 hari']),
                $this->statItem('Register >= 30 hari', $reg['>= 30 hari'] ?? 0, $total, self::COLORS['>= 30 hari']),
            ]),
            'temp' => $this->panel('Optical Temperatur', array_sum($temp), [
                $this->statItem('Adem < 45°C', $temp['Adem'] ?? 0, array_sum($temp), self::COLORS['Adem']),
                $this->statItem('Anget > 45°C', $temp['Anget'] ?? 0, array_sum($temp), self::COLORS['Anget']),
                $this->statItem('Puanass > 65°C', $temp['Puanass'] ?? 0, array_sum($temp), self::COLORS['Puanass']),
                $this->statItem('Awas!!! > 70°C', $temp['Awas'] ?? 0, array_sum($temp), self::COLORS['Awas']),
            ]),
        ];
    }

    /**
     * Empty overview used when GenieACS is unreachable so the page still renders.
     *
     * @return array<string, array{title: string, total: int, items: array<int, array{label: string, value: int, pct: float, color: string}>}>
     */
    public function emptyOverview(): array
    {
        $rows = [];

        return $this->overviewStats($rows);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $dev
     */
    private function resolveSerial(array $dev, string $deviceId): ?string
    {
        $meta = $dev['_deviceId'] ?? null;
        $serial = is_array($meta)
            ? ($meta['_SerialNumber'] ?? $meta['SerialNumber'] ?? null)
            : null;

        if (filled($serial)) {
            return (string) $serial;
        }

        $serial = $this->acsValue($dev, 'InternetGatewayDevice.DeviceInfo.SerialNumber');

        if (filled($serial)) {
            return (string) $serial;
        }

        $parts = explode('-', $deviceId);
        $suffix = end($parts);

        return filled($suffix) ? (string) $suffix : null;
    }

    /**
     * @param  array<string, mixed>  $dev
     */
    private function deviceIdMeta(array $dev, string $key): mixed
    {
        $meta = $dev['_deviceId'] ?? null;

        return is_array($meta) ? ($meta[$key] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchOnu(string $deviceId, ?string $serial, array $context): ?Onu
    {
        $byId = $context['onu_by_id'] ?? [];
        $bySerial = $context['onu_by_serial'] ?? [];

        if (isset($byId[$this->normalizeKey($deviceId)])) {
            return $byId[$this->normalizeKey($deviceId)];
        }

        if ($serial !== null && isset($bySerial[$this->normalizeKey($serial)])) {
            return $bySerial[$this->normalizeKey($serial)];
        }

        $parts = explode('-', $deviceId);
        $suffix = mb_strtolower(trim((string) end($parts)));

        if ($suffix !== '' && isset($bySerial[$suffix])) {
            return $bySerial[$suffix];
        }

        return null;
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @param  array<int, mixed>|Collection  $onuIds
     * @return array<int, OnuMonitoringHistory>
     */
    private function latestTemps($onuIds): array
    {
        $ids = $onuIds instanceof Collection ? $onuIds : collect($onuIds);

        if ($ids->isEmpty()) {
            return [];
        }

        return OnuMonitoringHistory::query()
            ->select('onu_monitoring_history.*')
            ->joinSub(
                OnuMonitoringHistory::query()
                    ->selectRaw('MAX(id) as id')
                    ->whereIn('onu_id', $ids)
                    ->groupBy('onu_id'),
                'latest',
                'onu_monitoring_history.id',
                '=',
                'latest.id'
            )
            ->get()
            ->keyBy('onu_id')
            ->all();
    }

    /**
     * Map aktif username PPPoE → IP address (cache 3 detik).
     *
     * @return array<string, string|null>
     */
    private function pppoeIndex(): array
    {
        return Cache::remember('genieacs_pppoe_idx', 3, function () {
            $idx = [];

            foreach (MikrotikRouter::where('is_active', true)->orderBy('id')->get() as $router) {
                try {
                    $result = (new RouterCommandService($router))->getPppActive();

                    if (! $result->isSuccess() || ! is_array($result->getData())) {
                        continue;
                    }

                    foreach ($result->getData() as $session) {
                        $name = trim((string) ($session['name'] ?? ''));

                        if ($name === '') {
                            continue;
                        }

                        $idx[mb_strtolower(explode('@', $name)[0])] = $session['address'] ?? null;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $idx;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function pppoeIp(?Onu $onu, array $context): ?string
    {
        $username = $onu?->customer?->pppoe_username;

        if (filled($username)) {
            $key = mb_strtolower(trim(explode('@', $username)[0]));

            if (isset($context['pppoe'][$key])) {
                return $context['pppoe'][$key];
            }
        }

        return $onu?->ip_address;
    }

    private function statusBucket(?Carbon $lastInform): string
    {
        if ($lastInform === null) {
            return 'off_30d';
        }

        $diff = time() - $lastInform->getTimestamp();

        return match (true) {
            $diff < self::ONLINE_WINDOW => 'on',
            $diff < self::DAY => 'off',
            $diff < self::DAY * 3 => 'off_24h',
            $diff < self::DAY * 7 => 'off_3d',
            $diff < self::DAY * 30 => 'off_7d',
            default => 'off_30d',
        };
    }

    private function brandKey(?string $manufacturer): string
    {
        $value = mb_strtolower((string) $manufacturer);

        foreach (self::BRAND_MAP as $needle => $brand) {
            if (str_contains($value, $needle)) {
                return $brand;
            }
        }

        return 'Other';
    }

    private function rxKey(float $rx): string
    {
        if ($rx < -28) {
            return 'Kritis';
        }

        if ($rx < -25) {
            return 'Sedang';
        }

        return 'Bagus';
    }

    private function tempKey(float $temperature): string
    {
        if ($temperature > 70) {
            return 'Awas';
        }

        if ($temperature > 65) {
            return 'Puanass';
        }

        if ($temperature > 45) {
            return 'Anget';
        }

        return 'Adem';
    }

    private function regKey(Carbon $regTime): string
    {
        $diff = time() - $regTime->getTimestamp();

        if ($regTime->isToday()) {
            return 'Hari Ini';
        }

        if ($diff < self::DAY * 7) {
            return '< 7 hari';
        }

        if ($diff < self::DAY * 30) {
            return '< 30 hari';
        }

        return '>= 30 hari';
    }

    private function accessType(?Onu $onu): string
    {
        $portType = mb_strtolower((string) ($onu?->oltPort?->port_type ?? ''));

        if (in_array($portType, ['gpon', 'xgspon'], true)) {
            return 'GPON';
        }

        if ($portType === 'epon') {
            return 'EPON';
        }

        return 'Ethernet/Converter';
    }

    private function ponLabel(?Onu $onu): ?string
    {
        if ($onu === null) {
            return null;
        }

        $port = $onu->oltPort;
        $olt = $port?->olt;

        if ($olt && $port && filled($port->port_name)) {
            return $olt->name.'/'.$port->port_name;
        }

        return filled($port?->port_name) ? $port->port_name : $onu->onu_id;
    }

    /**
     * @param  array<string, mixed>  $dev
     */
    private function registeredOn(mixed $tags): ?Carbon
    {
        if (! is_array($tags)) {
            return null;
        }

        foreach ($tags as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $haystack = mb_strtolower((string) $key.' '.(string) $value);

            if (! str_contains($haystack, 'registered') && ! str_contains($haystack, 'regtime')) {
                continue;
            }

            $parsed = $this->toCarbon($value);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dev
     */
    private function countChildren(array $dev, string $path): int
    {
        $node = $dev;

        foreach (preg_split('/\./', $path) as $key) {
            if (! is_array($node) || ! isset($node[$key])) {
                return 0;
            }

            $node = $node[$key];
        }

        if (! is_array($node)) {
            return 0;
        }

        $count = 0;

        foreach ($node as $key => $_) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Unwrap nested GenieACS value (leaf bisa berwujud {_value: ...}).
     *
     * @param  array<string, mixed>  $dev
     */
    private function acsValue(array $dev, string $path): mixed
    {
        $node = $dev;

        foreach (preg_split('/\./', $path) as $key) {
            if (! is_array($node) || ! isset($node[$key])) {
                return null;
            }

            $node = $node[$key];
        }

        return is_array($node) ? ($node['_value'] ?? null) : $node;
    }

    /**
     * @param  array<int, array{label: string, value: int, pct: float, color: string}>  $items
     * @return array{title: string, total: int, items: array<int, array{label: string, value: int, pct: float, color: string}>}
     */
    private function panel(string $title, int $total, array $items): array
    {
        return [
            'title' => $title,
            'total' => $total,
            'items' => $items,
        ];
    }

    /**
     * @return array{label: string, value: int, pct: float, color: string}
     */
    private function statItem(string $label, int $value, int $total, string $color): array
    {
        $pct = $total > 0 ? round($value / $total * 100, 2) : 0.0;

        return [
            'label' => $label,
            'value' => $value,
            'pct' => $pct,
            'color' => $color,
        ];
    }

    private function humanUptime(int $seconds): string
    {
        $days = intdiv($seconds, self::DAY);
        $hours = intdiv($seconds % self::DAY, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days.'d '.$hours.'h';
        }

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
