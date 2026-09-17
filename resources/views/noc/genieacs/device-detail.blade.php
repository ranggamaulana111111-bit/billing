@extends('layouts.app')

@section('title', 'Device Detail — GenieACS NOC')

@section('navbar_title')
<div class="d-flex align-items-center gap-2" style="min-width:0;">
    <i class="fa-solid fa-hard-drive" style="color:var(--primary);"></i>
    <span class="fw-bold" style="font-size:0.95rem; white-space:nowrap;">Device Detail</span>
    <code style="font-size:0.78rem; background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px; color:rgba(255,255,255,0.85); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $deviceId }}</code>
</div>
@endsection

@push('styles')
<style>
    .nav-tabs-acs { border-bottom: 1px solid var(--border-subtle); }
    .nav-tabs-acs .nav-link {
        color: var(--text-secondary); font-size: 0.8rem; font-weight: 600;
        border: 0; border-bottom: 2px solid transparent; padding: 0.6rem 1.1rem;
    }
    .nav-tabs-acs .nav-link:hover { color: var(--primary); border-color: transparent; }
    .nav-tabs-acs .nav-link.active {
        color: var(--primary); background: transparent; border-bottom-color: var(--primary);
    }
    .tab-pane-acs { padding-top: 1rem; }
    .param-table { font-size: 0.76rem; }
    .param-table th {
        font-weight: 600; color: var(--text-secondary); width: 45%;
        white-space: normal; word-break: break-all;
    }
    .param-table td {
        font-family: var(--font-mono); white-space: normal; word-break: break-all;
    }
    .param-scroll { max-height: 480px; overflow: auto; }
    .clients-scroll { max-height: 480px; overflow-x: hidden; overflow-y: auto; }
    .status-dot { display: inline-block; width: 0.6rem; height: 0.6rem; border-radius: 50%; }
    .acs-tag { background: rgba(var(--primary-rgb), 0.08); color: var(--primary); font-size: 0.64rem; font-weight: 500; border: 1px solid rgba(var(--primary-rgb), 0.2); }
    .acs-config-form details > summary { cursor: pointer; list-style-position: inside; }
    .acs-config-form details[open] > summary { color: var(--primary); }
    .detail-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
.acs-info-cards .detail-label { text-transform: none; letter-spacing: 0; font-weight: 600; }
    .acs-info-cards .card,
    .acs-info-cards .card-header,
    .acs-info-cards .card::before { border-radius: 12px 12px 0 0; }
    .acs-info-cards .card { border-radius: 12px; }
    #content { padding: 12px 16px 24px; max-width: none; }
    .top-navbar { padding: 6px 16px; justify-content: space-between !important; gap: 16px !important; }
    .top-navbar-title { display:flex; align-items:center; gap:10px; min-width:0; flex:1; color: rgba(255,255,255,0.92); font-weight:600; }
    .top-navbar-title code { font-size:0.78rem; color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px; }
    .top-navbar-avatar { width: 26px; height: 26px; font-size: 11px; border-radius: 7px; }
    .top-navbar-user { gap: 7px; }
    .top-navbar-name { font-size: 11.5px; }
    .top-navbar-role { font-size: 9px; }
    .top-navbar-logout { padding: 4px 8px; font-size: 12px; border-radius: 7px; }
    .page-header { margin-bottom: 10px; }
    .page-header h2 { font-size: 1.05rem; }
    .section-subtitle { font-size: 0.75rem; }
    .acd-action-btn { padding: 5px 14px; font-size: 0.75rem; border-radius: 8px; font-weight: 600; }
    .acs-wan-table { font-size: 0.76rem; }
    .acs-wan-table thead th { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.03em; color: var(--text-muted); border-bottom: 1px solid var(--border-subtle); padding: 8px 14px; white-space: nowrap; }
    .acs-wan-table tbody td { padding: 8px 14px; vertical-align: middle; border-bottom: 1px solid var(--border-subtle); }
    .acs-wan-table code { font-size: 0.7rem; }

    /* â”€â”€â”€ WAN MODERN: scoped khusus section WAN â”€â”€â”€ */
    .wan-modern .detail-label, #cardWanForm .detail-label {
        font-size: 0.7rem; letter-spacing: 0.02em; font-weight: 600;
    }
    .wan-modern .form-control-sm, #cardWanForm .form-control-sm,
    .wan-modern .form-select-sm, #cardWanForm .form-select-sm {
        border-radius: 8px; border-color: var(--border-subtle); background-color: #fff;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .wan-modern .form-control-sm:focus, #cardWanForm .form-control-sm:focus,
    .wan-modern .form-select-sm:focus, #cardWanForm .form-select-sm:focus {
        border-color: var(--primary); background-color: #fff; box-shadow: 0 0 0 0.14rem rgba(var(--primary-rgb), 0.12);
    }
    .wan-modern .form-control-sm:hover:not(:focus), #cardWanForm .form-control-sm:hover:not(:focus),
    .wan-modern .form-select-sm:hover:not(:focus), #cardWanForm .form-select-sm:hover:not(:focus) { border-color: #b8c4d3; }
    .wan-modern .form-select-sm { cursor: pointer; }
    .wan-btn-grad {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        border: 0; box-shadow: 0 4px 12px -3px rgba(var(--primary-rgb), 0.45); color: #fff;
    }
    .wan-btn-grad:hover { filter: brightness(1.05); box-shadow: 0 6px 16px -4px rgba(var(--primary-rgb), 0.55); color: #fff; }
    .wan-chip-vlan { background: rgba(var(--primary-rgb), 0.1); color: var(--primary); border-radius: 6px; padding: 2px 7px; font-size: 0.7rem; font-weight: 600; }
    .wan-chip-svc { background: var(--surface-2); color: var(--text-muted); border-radius: 6px; padding: 2px 7px; font-size: 0.68rem; }
    .acs-wan-table thead th { font-size: 0.6rem; letter-spacing: 0.05em; }
    .acs-wan-table tbody tr { transition: background-color .12s ease; }
    .acs-wan-table tbody tr:hover { background-color: rgba(var(--primary-rgb), 0.04); }
    .wan-modern-header-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: linear-gradient(135deg, rgba(var(--primary-rgb),0.12), rgba(var(--primary-rgb),0.05));
        border: 1px solid rgba(var(--primary-rgb),0.18); color: var(--primary);
        padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600;
    }
    .acs-wan-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.66rem; font-weight: 600; }
    .wan-switch { width: 2.5em !important; height: 1.2em !important; margin-top: 0.15em !important; border-radius: 2em !important; }
    .wan-switch-box { position: relative; display: inline-block; vertical-align: middle; line-height: 0; }
    .wan-switch-box .wan-switch { margin: 0 !important; background-color: #adb5bd !important; border-color: #8a939c !important; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 9'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e") !important; border-radius: 2em !important; background-position: left center !important; background-repeat: no-repeat !important; background-size: contain !important; transition: background-position .15s ease-in-out, background-color .15s ease-in-out, border-color .15s ease-in-out !important; }
    .wan-switch-box .wan-switch:checked { background-color: var(--primary) !important; border-color: var(--primary) !important; background-position: right center !important; }
    .wan-pos { position: absolute; top: 0; bottom: 0; width: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.52rem; font-weight: 700; line-height: 1; pointer-events: none; user-select: none; }
    .wan-on { left: 0; color: #fff; visibility: hidden; padding-left: 0.28em; }
    .wan-off { right: 0; color: #fff; padding-right: 0.28em; }
    .wan-switch-box .wan-switch:checked ~ .wan-on { visibility: visible; }
    .wan-switch-box .wan-switch:checked ~ .wan-off { visibility: hidden; }
    .acs-config-form .form-check-input, .acs-config-form .form-check-label { cursor: pointer; }
    .wan-switch, .wan-switch ~ label { cursor: pointer; }
    .card::before, .card:hover::before { display: none !important; }
    #btnKembali, #btnUpgrade, #btnRefreshAll { background:#fff !important; }
    #btnKembali:hover, #btnUpgrade:hover { background:#6c757d !important; color:#fff !important; border-color:#6c757d !important; }
    #btnRefreshAll:hover { background:var(--primary) !important; color:#fff !important; border-color:var(--primary) !important; }
    @media (max-width: 575.98px) {
        .page-header .page-actions { flex-wrap: nowrap !important; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; gap: 6px !important; }
        .page-header .page-actions::-webkit-scrollbar { height: 4px; }
        .page-header .page-actions::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 2px; }
        .page-header .page-actions .btn { flex: 0 0 auto; white-space: nowrap; padding: 4px 10px !important; font-size: 0.7rem !important; }
        .top-navbar-title code { font-size: 0.65rem !important; max-width: 140px; overflow: hidden; text-overflow: ellipsis; }
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex flex-wrap justify-content-start align-items-center">
    <div class="page-actions mt-2 mt-md-0 d-flex flex-wrap gap-2">
        <a href="{{ route('noc.genieacs.devices') }}" id="btnKembali" class="btn btn-outline-secondary acd-action-btn">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali
        </a>
        <button type="button" class="btn btn-primary acd-action-btn" id="btnSummon" title="Summon (Connection Request)">
            <i class="fa-solid fa-bolt me-1"></i>Summon
        </button>
        <button type="button" class="btn btn-outline-primary acd-action-btn" id="btnRefreshAll" title="Refresh Semua">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh Semua
        </button>
        <button type="button" class="btn btn-danger acd-action-btn" id="btnReboot" title="Reboot ONT">
            <i class="fa-solid fa-power-off me-1"></i>Reboot ONT
        </button>
        <button type="button" class="btn btn-warning acd-action-btn" id="btnFactoryReset" title="Factory Reset">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>Factory Reset
        </button>
        <button type="button" class="btn btn-outline-secondary acd-action-btn" id="btnUpgrade" title="Upgrade Firmware">
            <i class="fa-solid fa-download me-1"></i>Upgrade Firmware
        </button>
        <button type="button" class="btn btn-danger acd-action-btn" id="btnDelete" title="Hapus">
            <i class="fa-solid fa-trash-can me-1"></i>Hapus
        </button>
    </div>
</div>

@if($error ?? null)
<div class="alert alert-danger d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-circle-xmark me-2 fa-lg"></i>
    <div>{{ $error }}</div>
</div>
@elseif(!$device)
<div class="alert alert-warning d-flex align-items-center mb-4 py-3" style="font-size:0.85rem;">
    <i class="fa-solid fa-triangle-exclamation me-2 fa-lg"></i>
    <div>Device tidak ditemukan atau GenieACS tidak terjangkau.</div>
</div>
@else
@php
    $s = $summary ?? [];

    $flat = function ($node, $path = '') use (&$flat) {
        $rows = [];
        if (! is_array($node)) {
            $rows[] = [$path, $node];
            return $rows;
        }
        if (array_key_exists('_value', $node)) {
            $rows[] = [$path, $node['_value']];
            return $rows;
        }
        foreach ($node as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                continue;
            }
            $p = $path === '' ? (string) $k : $path.'.'.$k;
            if (is_array($v)) {
                $rows = array_merge($rows, $flat($v, $p));
            } else {
                $rows[] = [$p, $v];
            }
        }
        return $rows;
    };

    $renderParams = function ($rows) {
        if (count($rows) === 0) {
            return '<div class="text-muted text-center py-4" style="font-size:0.85rem;">Tidak ada data CWMP.</div>';
        }
        $html = '<table class="table table-sm table-striped mb-0 param-table"><tbody>';
        foreach ($rows as [$p, $v]) {
            $display = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            if ($display === '') {
                $display = '&laquo;empty&raquo;';
            }
            $html .= '<tr><th scope="row">'.e($p).'</th><td>'.e($display).'</td></tr>';
        }
        return $html.'</tbody></table>';
    };

    $hostRows = $flat($device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'] ?? null, 'Hosts.Host');
    $tr069Rows = $flat($device['InternetGatewayDevice']['ManagementServer'] ?? null, 'ManagementServer');

    // â”€â”€ Form konfigurasi WAN/LAN/WLAN (hanya leaf writable) â”€â”€
    $writable = $writable ?? [];

    $pathValue = function ($path) use ($device) {
        $node = $device;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($node) || ! array_key_exists($seg, $node)) {
                return '';
            }
            $node = $node[$seg];
        }
        return is_array($node) ? ($node['_value'] ?? '') : (string) $node;
    };

    // â”€â”€ Form konfigurasi ACS (TR-069) â”€â”€
    $msRoot = 'InternetGatewayDevice.ManagementServer';
    $msEnablePath = $msRoot.'.EnableCWMP';
    if ($pathValue($msEnablePath) === '') {
        $msEnablePath = $msRoot.'.PeriodicInformEnable';
    }
    $tr069Fields = [
        'Enable ACS Management' => [$msEnablePath, 'xsd:boolean'],
        'Informing Interval' => [$msRoot.'.PeriodicInformInterval', 'xsd:unsignedInt'],
        'Informing Time' => [$msRoot.'.PeriodicInformTime', 'xsd:string'],
        'ACS URL' => [$msRoot.'.URL', 'xsd:string'],
        'ACS User Name' => [$msRoot.'.Username', 'xsd:string'],
        'ACS Password' => [$msRoot.'.Password', 'xsd:string'],
        'Connection Request User Name' => [$msRoot.'.ConnectionRequestUsername', 'xsd:string'],
        'Connection Request Password' => [$msRoot.'.ConnectionRequestPassword', 'xsd:string'],
    ];

    $wanWritable = [];
    $lanWritable = [];
    $wlanWritable = [];
    foreach ($writable as $p => $t) {
        if (str_starts_with($p, 'InternetGatewayDevice.WANDevice.')) {
            $wanWritable[$p] = $t;
        } elseif (str_starts_with($p, 'InternetGatewayDevice.LANDevice.')) {
            if (str_contains($p, '.WLANConfiguration.')) {
                $wlanWritable[$p] = $t;
            } else {
                $lanWritable[$p] = $t;
            }
        }
    }

    $groupByBase = function (array $params, string $pattern) {
        $groups = [];
        foreach ($params as $p => $t) {
            preg_match($pattern, $p, $m);
            $base = $m[1] ?? 'InternetGatewayDevice';
            $groups[$base][$p] = $t;
        }
        return $groups;
    };

    $shortBase = function (string $base): string {
        $segs = explode('.', $base);
        return implode('.', array_slice($segs, -2));
    };

    $humanize = fn (string $path): string => ucfirst(mb_strtolower(str_replace(['_', '-'], ' ', substr($path, strrpos($path, '.') + 1))));

    $wanGroups = $groupByBase(
        $wanWritable,
        '#^(InternetGatewayDevice\.WANDevice\.\d+(?:\.WANConnectionDevice\.\d+\.(?:WANIPConnection|WANPPPConnection)\.\d+|\.WANCommonInterfaceConfig|\.WANConnectionDevice\.\d+))#'
    );
    $wlanGroups = $groupByBase($wlanWritable, '#^(InternetGatewayDevice\.LANDevice\.\d+\.WLANConfiguration\.\d+)#');

    $coreWan = ['vlan', 'servicelist', 'laninterface', 'ipmode', 'enable', 'address', 'username', 'password',
        'connectiontype', 'mtu', 'shapingrate', 'dnsserver', 'defaultgateway', 'subnetmask', 'natenabled', 'name'];
    $coreLan = ['dhcp', 'ipaddress', 'subnetmask', 'dnsserver', 'gateway', 'leasetime', 'hostname', 'devicename',
        'domainname', 'dns'];
    $coreWlan = ['ssid', 'passphrase', 'wpaencryption', 'authentication', 'encryption', 'enable', 'channel',
        'bandwidth', 'apmodule', 'beacon', 'maxbitrate', 'key'];

    // â”€â”€ Box WLAN terdeteksi (konfigurasi langsung dari aplikasi) â”€â”€
    $wlanBoxes = [];
    $lanRoot = $device['InternetGatewayDevice']['LANDevice'] ?? null;
    if (is_array($lanRoot)) {
        foreach ($lanRoot as $lanIdx => $lanNode) {
            if (! is_array($lanNode) || str_starts_with((string) $lanIdx, '_')) {
                continue;
            }
            $wlanCfg = $lanNode['WLANConfiguration'] ?? null;
            if (! is_array($wlanCfg)) {
                continue;
            }
            foreach ($wlanCfg as $wlanIdx => $cfgNode) {
                if (! is_array($cfgNode) || str_starts_with((string) $wlanIdx, '_')) {
                    continue;
                }
                $base = 'InternetGatewayDevice.LANDevice.'.$lanIdx.'.WLANConfiguration.'.$wlanIdx;
                $enableRaw = strtolower(trim((string) $pathValue($base.'.Enable')));
                $active = in_array($enableRaw, ['1', 'true', 'on', 'yes'], true);
                $std = (string) $pathValue($base.'.Standard');
                $keyPath = null;
                foreach (['KeyPassphrase', 'PreSharedKey', 'WPAKeyPassphrase', 'X_CT-COM_KeyPassphrase'] as $k) {
                    if (isset($writable[$base.'.'.$k])) {
                        $keyPath = $base.'.'.$k;
                        break;
                    }
                }
                $wlanBoxes[] = [
                    'instance' => (int) $wlanIdx,
                    'base' => $base,
                    'ssid' => (string) $pathValue($base.'.SSID'),
                    'pass' => $keyPath !== null ? (string) $pathValue($keyPath) : '',
                    'active' => $active,
                    'mode' => $std !== '' ? $std : 'b.g.n',
                    'enablePath' => isset($writable[$base.'.Enable']) ? $base.'.Enable' : null,
                    'ssidPath' => isset($writable[$base.'.SSID']) ? $base.'.SSID' : null,
                    'keyPath' => $keyPath,
                ];
            }
        }
    }
    $wlanDetected = count($wlanBoxes);
    $wlanActive = count(array_filter($wlanBoxes, fn ($b) => $b['active']));

    // â”€â”€ Client terhubung: WiFi (AssociatedDevice) & LAN (Hosts.Host) â”€â”€
    $findLeaf = function (array $node, string $needle) {
        foreach ($node as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                continue;
            }
            if (is_array($v)) {
                $val = (string) ($v['_value'] ?? '');
                if ($val !== '' && str_contains((string) $k, $needle)) {
                    return $val;
                }
            } elseif (str_contains((string) $k, $needle)) {
                return (string) $v;
            }
        }

        return '';
    };

    // Pemetaan MAC -> HostName dari Hosts.Host (untuk mengisi nama client WiFi,
    // karena AssociatedDevice umumnya tidak menyediakan nama perangkat)
    $macHosts = [];
    if (is_array($lanRoot)) {
        foreach ($lanRoot as $lanIdx => $lanNode) {
            if (! is_array($lanNode) || str_starts_with((string) $lanIdx, '_')) {
                continue;
            }
            $base = 'InternetGatewayDevice.LANDevice.'.$lanIdx;
            $hosts = $lanNode['Hosts']['Host'] ?? null;
            if (! is_array($hosts)) {
                continue;
            }
            foreach ($hosts as $hIdx => $hNode) {
                if (! is_array($hNode) || str_starts_with((string) $hIdx, '_')) {
                    continue;
                }
                $hh = $base.'.Hosts.Host.'.$hIdx;
                $m = (string) $pathValue($hh.'.MACAddress');
                $n = (string) $pathValue($hh.'.HostName');
                if ($m !== '') {
                    $macHosts[strtoupper($m)] = $n;
                }
            }
        }
    }

    $wifiClients = [];
    if (is_array($lanRoot)) {
        foreach ($lanRoot as $lanIdx => $lanNode) {
            if (! is_array($lanNode) || str_starts_with((string) $lanIdx, '_')) {
                continue;
            }
            $base = 'InternetGatewayDevice.LANDevice.'.$lanIdx;
            $wlanCfg = $lanNode['WLANConfiguration'] ?? null;
            if (! is_array($wlanCfg)) {
                continue;
            }
            foreach ($wlanCfg as $wlanIdx => $cfgNode) {
                if (! is_array($cfgNode) || str_starts_with((string) $wlanIdx, '_')) {
                    continue;
                }
                $wh = $base.'.WLANConfiguration.'.$wlanIdx;
                $ssid = (string) $pathValue($wh.'.SSID');
                $assoc = $cfgNode['AssociatedDevice'] ?? null;
                if (! is_array($assoc)) {
                    continue;
                }
                foreach ($assoc as $aIdx => $aNode) {
                    if (! is_array($aNode) || str_starts_with((string) $aIdx, '_')) {
                        continue;
                    }
                    $hh = $wh.'.AssociatedDevice.'.$aIdx;
                    $mac = (string) $pathValue($hh.'.AssociatedDeviceMACAddress');
                    if ($mac === '') {
                        $mac = (string) $pathValue($hh.'.MACAddress');
                    }
                    if ($mac === '') {
                        continue;
                    }
                    $dh = $aNode; // node assoc untuk scan vendor leaf
                    $name = (string) $pathValue($hh.'.HostName');
                    if ($name === '') {
                        $name = (string) $findLeaf($dh, 'HostName');
                    }
                    if ($name === '') {
                        $name = $macHosts[strtoupper($mac)] ?? '';
                    }
                    if ($name === '') {
                        $name = (string) $findLeaf($dh, 'descriptions');
                    }
                    $wifiClients[] = [
                        'name' => $name,
                        'mac' => $mac,
                        'ip' => (string) $pathValue($hh.'.IPAddress') ?: (string) $pathValue($hh.'.AssociatedDeviceIPAddress'),
                        'ssid' => $ssid,
                    ];
                }
            }
        }
    }

    // â”€â”€ LAN: host aktif yang TIDAK sudah terhitung sebagai client WiFi â”€â”€
    $lanClients = [];
    if (is_array($lanRoot)) {
        $wifiMacs = [];
        foreach ($wifiClients as $wc) {
            $wifiMacs[strtoupper($wc['mac'])] = true;
        }
        foreach ($lanRoot as $lanIdx => $lanNode) {
            if (! is_array($lanNode) || str_starts_with((string) $lanIdx, '_')) {
                continue;
            }
            $hosts = $lanNode['Hosts']['Host'] ?? null;
            if (! is_array($hosts)) {
                continue;
            }
            $base = 'InternetGatewayDevice.LANDevice.'.$lanIdx;
            foreach ($hosts as $hIdx => $hNode) {
                if (! is_array($hNode) || str_starts_with((string) $hIdx, '_')) {
                    continue;
                }
                $hh = $base.'.Hosts.Host.'.$hIdx;
                $mac = (string) $pathValue($hh.'.MACAddress');
                if ($mac === '') {
                    continue;
                }
                $active = in_array(strtolower((string) $pathValue($hh.'.Active')), ['1', 'true', 'on', 'yes'], true);
                if (! $active || isset($wifiMacs[strtoupper($mac)])) {
                    continue;
                }
                $name = (string) $pathValue($hh.'.HostName');
                $source = (string) $pathValue($hh.'.AddressSource');
                $hostType = (string) $findLeaf($hNode, 'DeviceType') ?: (string) $findLeaf($hNode, 'HostType');
                $isModem = $source === 'Static' || $hostType === '0' || preg_match('/router|modem|gateway|ont|gpon|wifi/i', $name) === 1;
                $lanClients[] = [
                    'name' => $name,
                    'mac' => $mac,
                    'ip' => (string) $pathValue($hh.'.IPAddress'),
                    'source' => $source,
                    'active' => true,
                    'modem' => $isModem,
                ];
            }
        }
    }

    $isBool = fn ($t) => mb_strpos(mb_strtolower((string) $t), 'bool') !== false;
    $isInt = fn ($t) => mb_strpos(mb_strtolower((string) $t), 'int') !== false;
    $isSecret = fn ($p) => mb_stripos($p, 'password') !== false || mb_stripos($p, 'keypassphrase') !== false;

    $renderConfigInput = function ($path, $type, $label) use ($pathValue, $isBool, $isInt, $isSecret) {
        $v = $pathValue($path);
        $secret = $isSecret($path);

        if ($isBool($type)) {
            $lower = mb_strtolower(trim((string) $pathValue($path)));
            $boolVal = in_array($lower, ['1', 'true', 'on', 'yes'], true) ? 'true'
                : (in_array($lower, ['0', 'false', 'off', 'no'], true) ? 'false' : '');
            $html = '<select class="form-select form-select-sm" data-path="'.e($path).'" data-orig="'.e($boolVal).'" aria-label="'.e($label).'">';
            if ($boolVal === '') {
                $html .= '<option value="">Belum diisi</option>';
            }
            $html .= '<option value="true"'.($boolVal === 'true' ? ' selected' : '').'>Enabled</option>';
            $html .= '<option value="false"'.($boolVal === 'false' ? ' selected' : '').'>Disabled</option>';
            return $html.'</select>';
        }

        if ($isInt($type)) {
            $val = $v === '' ? '' : e($v);
            $extra = ' value="'.$val.'"';
            $typeAttr = 'type="number" step="1"';
        } else {
            $val = e($v);
            $extra = ' value="'.$val.'"';
            $typeAttr = $secret ? 'type="password" autocomplete="new-password"' : 'type="text"';
        }

        $inputs = '<input '.$typeAttr.' class="form-control form-control-sm'.($secret ? ' acs-pw-input' : '').'" data-path="'.e($path).'" data-orig="'.e($v).'"'.$extra.' placeholder="'.($val === '' ? '&laquo;kosong&raquo;' : '').'" aria-label="'.e($label).'" style="font-family:var(--font-mono);font-size:0.75rem;">';
        if ($secret) {
            $inputs .= '<button type="button" class="btn btn-outline-secondary acs-pw-eye" tabindex="-1" title="Tampilkan / Sembunyikan password"><i class="fa-regular fa-eye"></i></button>';
            return '<div class="input-group input-group-sm acs-pw-group">'.$inputs.'</div>';
        }
        return $inputs;
    };

    $splitCore = function (array $params, array $coreKws) {
        $core = [];
        $extra = [];
        foreach ($params as $p => $t) {
            $leaf = mb_strtolower(substr($p, strrpos($p, '.') + 1));
            $hit = false;
            foreach ($coreKws as $kw) {
                if (str_contains($leaf, $kw)) {
                    $hit = true;
                    break;
                }
            }
            $hit ? ($core[$p] = $t) : ($extra[$p] = $t);
        }
        return [$core, $extra];
    };

    $configForm = function (array $params, string $formId, array $coreKws) use ($renderConfigInput, $humanize, $splitCore) {
        if (count($params) === 0) {
            return '<div class="text-muted text-center py-4" style="font-size:0.85rem;">Tidak ada parameter yang dapat dikonfigurasi.</div>';
        }

        [$core, $extra] = $splitCore($params, $coreKws);

        $renderRows = function (array $params) use ($renderConfigInput, $humanize) {
            $html = '';
            foreach ($params as $p => $t) {
                $label = $humanize($p);
                $html .= '<tr>';
                $html .= '<th scope="row"><span class="d-block">'.e($label).'</span><span class="d-block" style="font-weight:400;font-size:0.62rem;color:var(--text-muted);">'.e($p).'</span></th>';
                $html .= '<td style="width:50%;">'.$renderConfigInput($p, $t, $label).'</td>';
                $html .= '</tr>';
            }
            return $html;
        };

        $html = '<form id="'.e($formId).'" class="acs-config-form" autocomplete="off">';
        $html .= '<div class="table-responsive param-scroll"><table class="table table-sm table-striped mb-0 param-table"><tbody>';
        $html .= $renderRows($core);
        $html .= '</tbody></table></div>';

        if ($extra !== []) {
            $html .= '<details class="border-top"><summary class="small text-muted px-3 py-2 cursor-pointer">Kustom &amp; parameter lanjutan ('.count($extra).')</summary>'
                .'<div class="table-responsive param-scroll" style="max-height:320px;"><table class="table table-sm table-striped mb-0 param-table"><tbody>'
                .$renderRows($extra)
                .'</tbody></table></div></details>';
        }

        $html .= '<div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">';
        $html .= '<small class="text-muted" style="font-size:0.72rem;"><i class="fa-solid fa-circle-info me-1"></i>Hanya parameter yang nilainya Anda ubah yang dikirim sebagai task GenieACS (setParameterValues) dan diterapkan saat sesi inform berikutnya.</small>';
        $html .= '<button type="submit" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-save me-1"></i>Apply</button>';
        $html .= '</div></form>';
        return $html;
    };

    // â”€â”€ Form WAN terstruktur (gaya Huawei ONT) â”€â”€
    $findWanPath = function (array $needles, ?string $within = null, bool $exactOnly = false) use ($wanWritable) {
        $paths = array_keys($wanWritable);
        $norm = function ($s) { return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $s)); };
        $cand = null !== $within
            ? array_values(array_filter($paths, function ($p) use ($within, $norm) { return str_contains($norm($p), $norm($within)); }))
            : $paths;
        if ($cand === []) {
            $cand = $paths;
        }
        foreach ($needles as $ndl) {
            $ndlN = $norm($ndl);
            foreach ($cand as $p) {
                if ($norm(substr($p, strrpos($p, '.') + 1)) === $ndlN) {
                    return $p;
                }
            }
        }
        if ($exactOnly) {
            return null;
        }
        foreach ($needles as $ndl) {
            $ndlN = $norm($ndl);
            foreach ($cand as $p) {
                if (str_contains($norm(substr($p, strrpos($p, '.') + 1)), $ndlN)) {
                    return $p;
                }
            }
        }
        foreach ($needles as $ndl) {
            $ndlN = $norm($ndl);
            foreach ($cand as $p) {
                if (str_contains($norm($p), $ndlN)) {
                    return $p;
                }
            }
        }
        return null;
    };

    $wanSpecOrder = ['connection_name', 'service_list', 'mtu', 'enable_vlan', 'vlan_id', 'priority_8021p', 'ppp_username', 'ppp_password', 'auth_type', 'connection_trigger', 'ip_version', 'ip_address', 'subnet_mask', 'default_gateway', 'dns_servers', 'type', 'enable_nat'];
    $wanSpec = [
        'connection_name' => ['label' => 'Connection Name', 'needles' => ['name'], 'kind' => 'text', 'placeholder' => 'WAN PPPoE'],
        'enable_vlan' => ['label' => 'Enable VLAN', 'needles' => ['vlanidmark'], 'within' => 'WANGponLinkConfig', 'kind' => 'bool', 'placeholder' => 'Belum diisi'],
        'vlan_id' => ['label' => 'VLAN ID', 'needles' => ['vlanid'], 'within' => 'WANGponLinkConfig', 'exact_only' => true, 'kind' => 'int', 'placeholder' => 'Belum diisi'],
        'priority_8021p' => ['label' => '802.1p', 'needles' => ['8021p'], 'within' => 'WANGponLinkConfig', 'kind' => 'int', 'default' => '0', 'placeholder' => '0'],
        'service_list' => ['label' => 'Service List', 'needles' => ['servicelist'], 'kind' => 'select', 'options' => ['INTERNET' => 'INTERNET', 'TR069_INTERNET' => 'TR069_INTERNET', 'TR069' => 'TR069'], 'placeholder' => 'INTERNET'],
        'mtu' => ['label' => 'MTU', 'needles' => ['maxmtu'], 'kind' => 'int', 'default' => '1492', 'placeholder' => '1492'],
        'ppp_username' => ['label' => 'Username', 'needles' => ['username'], 'within' => 'WANPPPConnection', 'kind' => 'text', 'placeholder' => 'pppoe@isp'],
        'ppp_password' => ['label' => 'Password', 'needles' => ['password'], 'within' => 'WANPPPConnection', 'kind' => 'password', 'placeholder' => '•••••••• (belum diisi)'],
        'auth_type' => ['label' => 'Authentication Type', 'needles' => ['authprotocol', 'authtype'], 'kind' => 'select', 'options' => ['Auto' => 'Auto', 'PAP' => 'PAP', 'CHAP' => 'CHAP', 'MSCHAPv2' => 'MSCHAPv2'], 'placeholder' => 'Auto'],
        'connection_trigger' => ['label' => 'Connection Trigger', 'needles' => ['connectiontrigger'], 'kind' => 'select', 'options' => ['Always On' => 'Always On', 'On Demand' => 'On Demand'], 'placeholder' => 'Always On'],
        'ip_version' => ['label' => 'IP Version', 'needles' => ['ipversion'], 'kind' => 'select', 'options' => ['IPv4' => 'IPv4', 'IPv6' => 'IPv6', 'IPv4v6' => 'IPv4v6'], 'placeholder' => 'IPv4'],
        'ip_address' => ['label' => 'IP Address', 'needles' => ['ipaddress'], 'within' => 'WANIPConnection', 'kind' => 'text', 'placeholder' => 'x.x.x.x'],
        'subnet_mask' => ['label' => 'Subnet Mask', 'needles' => ['subnetmask'], 'within' => 'WANIPConnection', 'kind' => 'text', 'placeholder' => '255.255.255.0'],
        'default_gateway' => ['label' => 'Default Gateway', 'needles' => ['defaultgateway'], 'within' => 'WANIPConnection', 'kind' => 'text', 'placeholder' => 'x.x.x.x'],
        'dns_servers' => ['label' => 'Primary DNS', 'needles' => ['dnsservers'], 'within' => 'WANIPConnection', 'kind' => 'text', 'placeholder' => 'x.x.x.x,x.x.x.x'],
        'type' => ['label' => 'Type', 'needles' => ['connectiontype'], 'kind' => 'select', 'options' => ['PPPoE' => 'PPPoE', 'IPoE' => 'IPoE', 'Static' => 'Static'], 'placeholder' => 'PPPoE'],
        'enable_nat' => ['label' => 'Enable NAT', 'needles' => ['natenabled'], 'kind' => 'bool', 'placeholder' => 'Belum diisi'],
    ];
    $wanBinds = [];
    foreach ($wanSpec as $key => $def) {
        $wanBinds[$key] = $findWanPath($def['needles'], $def['within'] ?? null, $def['exact_only'] ?? false);
    }

    $renderWanField = function (string $key, ?string $id = null) use ($wanSpec, $wanBinds, $wanWritable, $pathValue, $renderConfigInput) {
        $def = $wanSpec[$key];
        $path = $wanBinds[$key];
        $label = $def['label'];
        if ($path) {
            $type = $wanWritable[$path];
            if (($def['kind'] ?? null) === 'select') {
                $v = $pathValue($path);
                $idAttr = $id !== null ? ' id="'.e($id).'"' : '';
                $html = '<select'.$idAttr.' class="form-select form-select-sm" data-path="'.e($path).'" data-orig="'.e($v).'" aria-label="'.e($label).'" style="font-family:var(--font-mono);font-size:0.75rem;">';
                foreach ($def['options'] as $val => $txt) {
                    $html .= '<option value="'.e($val).'"'.($v === $val ? ' selected' : '').'>'.e($txt).'</option>';
                }
                $html .= '</select>';
            } else {
                $html = $renderConfigInput($path, $type, $label);
            }
            return $html;
        }
        $ph = $def['placeholder'] ?? '';
        return '<input class="form-control form-control-sm text-muted" disabled placeholder="'.e($ph).'" aria-label="'.e($label).'" title="Parameter ini tidak ditemukan pada device ini.">';
    };

    $renderWanFieldBlock = function (string $key, string $section, int $col = 4, ?string $hint = null) use ($renderWanField) {
        $html = '<div class="col-md-'.$col.'"><label class="detail-label d-block mb-1">'.$section;
        if ($hint !== null) {
            $html .= ' <span class="text-muted" style="font-weight:400;">'.e($hint).'</span>';
        }
        return $html.'</label>'.$renderWanField($key).'</div>';
    };

    // â”€â”€ Form WAN: ADD WAN (Create/Configure) â”€â”€
    $renderToggle = function (string $key, string $label, ?string $suffix = null) use ($wanBinds, $pathValue) {
        $path = $wanBinds[$key] ?? null;
        $v = $path ? (string) $pathValue($path) : '';
        $boolVal = in_array(mb_strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true) ? 'true'
            : (in_array(mb_strtolower(trim($v)), ['0', 'false', 'off', 'no'], true) ? 'false' : $v);
        $checked = $boolVal === 'true' ? ' checked' : '';
        $attrs = $path ? ' data-path="'.e($path).'" data-orig="'.e($boolVal).'"' : '';
        $id = 'wan-'.$key.($suffix !== null && $suffix !== '' ? '-'.$suffix : '');
        $box = '<span class="wan-switch-box"><input class="form-check-input wan-switch" type="checkbox" role="switch" id="'.$id.'"'.$attrs.$checked.'><span class="wan-pos wan-off">OFF</span><span class="wan-pos wan-on">ON</span></span>';
        return '<div class="form-check form-switch m-0">'.$box.'<label class="form-check-label" for="'.$id.'" style="font-size:0.75rem;cursor:pointer;">'.e($label).'</label></div>';
    };

    $renderSel = function (string $id, array $opts, ?string $def = null) {
        $html = '<select id="'.e($id).'" class="form-select form-select-sm" style="font-size:0.75rem;">';
        foreach ($opts as $v => $t) {
            $html .= '<option value="'.e($v).'"'.($v === $def ? ' selected' : '').'>'.e($t).'</option>';
        }
        return $html.'</select>';
    };

    $renderNum = function (string $id, ?string $ph = null, ?int $min = null, ?int $max = null, ?string $val = null, ?string $bindKey = null) use ($wanBinds, $pathValue) {
        $attrs = 'id="'.e($id).'"';
        if ($bindKey !== null && ($bindPath = $wanBinds[$bindKey] ?? null) !== null) {
            $v = (string) $pathValue($bindPath);
            $attrs .= ' data-path="'.e($bindPath).'" data-orig="'.e($v).'"';
            if ($val === null && $v !== '') {
                $val = $v;
            }
        }
        if ($ph !== null) {
            $attrs .= ' placeholder="'.e($ph).'"';
        }
        if ($min !== null) {
            $attrs .= ' min="'.$min.'"';
        }
        if ($max !== null) {
            $attrs .= ' max="'.$max.'"';
        }
        if ($val !== null) {
            $attrs .= ' value="'.e($val).'"';
        }
        return '<input type="number" step="1" class="form-control form-control-sm" '.$attrs.' style="font-size:0.75rem;">';
    };

    $renderText = function (string $id, ?string $ph = null, ?string $bindKey = null) use ($wanBinds, $pathValue) {
        $attrs = 'id="'.e($id).'" class="form-control form-control-sm" autocomplete="off" style="font-size:0.75rem;"';
        if ($bindKey !== null && ($bindPath = $wanBinds[$bindKey] ?? null) !== null) {
            $v = (string) $pathValue($bindPath);
            $attrs .= ' data-path="'.e($bindPath).'" data-orig="'.e($v).'"';
            if ($v !== '') {
                $attrs .= ' value="'.e($v).'"';
            } elseif ($ph !== null) {
                $attrs .= ' placeholder="'.e($ph).'"';
            }
        } elseif ($ph !== null) {
            $attrs .= ' placeholder="'.e($ph).'"';
        }
        return '<input type="text" '.$attrs.'>';
    };

    $renderFieldHtml = function (string $label, string $inner, int $col = 4) {
        return '<div class="col-md-'.$col.'"><label class="detail-label d-block mb-1">'.e($label).'</label>'.$inner.'</div>';
    };

    $renderMtu = function (string $id) use ($renderNum) {
        return '<div class="col-md-3"><label class="detail-label d-block mb-1">MTU <span class="text-muted" style="font-weight:400;">[128,1492]</span></label>'
            .$renderNum($id, '1492', 128, 1492, '1492').'</div>';
    };

    // Tabel konfigurasi WAN yang sudah ada di ONT (baca penuh dari tree device)
    $collectLeaves = function ($node, string $prefix = '') use (&$collectLeaves): array {
        if (is_array($node) && array_key_exists('_value', $node)) {
            return [$prefix => $node['_value']];
        }
        if (! is_array($node)) {
            return $prefix === '' ? [] : [$prefix => $node];
        }
        $out = [];
        foreach ($node as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                continue;
            }
            $p = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $out = array_merge($out, $collectLeaves($v, $p));
            } else {
                $out[$p] = $v;
            }
        }
        return $out;
    };

    $nodeAt = function (string $path) use ($device) {
        $n = $device;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($n) || ! array_key_exists($seg, $n)) {
                return null;
            }
            $n = $n[$seg];
        }
        return $n;
    };

    $pickVal = function (array $flat, array $tests, bool $numericOnly = false) {
        $norm = function ($s) { return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $s)); };
        $ok = function ($v) use ($numericOnly) {
            if ($v === null || is_bool($v)) {
                return false;
            }
            $s = trim((string) $v);
            if ($s === '' || in_array($s, ['true', 'false', '0.0.0.0', '::', 'none', 'null'], true)) {
                return false;
            }
            if ($numericOnly && ! is_numeric($s)) {
                return false;
            }
            return true;
        };
        foreach ($tests as $t) {
            $tN = $norm($t);
            foreach ($flat as $p => $v) {
                if (! $ok($v)) {
                    continue;
                }
                if ($norm(substr($p, strrpos($p, '.') + 1)) === $tN) {
                    return (string) $v;
                }
            }
        }
        foreach ($tests as $t) {
            $tN = $norm($t);
            foreach ($flat as $p => $v) {
                if (! $ok($v)) {
                    continue;
                }
                $leafN = $norm(substr($p, strrpos($p, '.') + 1));
                if (str_contains($leafN, $tN)) {
                    return (string) $v;
                }
            }
        }
        return null;
    };

    $typeNorm = function ($t): string {
        $lt = strtolower((string) $t);
        if (str_contains($lt, 'ppp')) {
            return 'PPPoE';
        }
        if (str_contains($lt, 'bridge')) {
            return 'Bridge';
        }
        if ($lt === 'ip_routed' || $lt === 'ip_bridged' || $lt === 'dhcp' || $lt === 'static' || $lt === '') {
            return 'IPoE';
        }
        return (string) ($t ?: 'IPoE');
    };

    $connInfo = function (string $base) use ($collectLeaves, $nodeAt, $pickVal, $typeNorm): array {
        $segs = explode('.', $base);
        $parentBase = implode('.', array_slice($segs, 0, -2));
        $wanDevBase = implode('.', array_slice($segs, 0, -3));

        $connFlat = $collectLeaves($nodeAt($base), $base);
        $parentFlat = $collectLeaves($nodeAt($parentBase), $parentBase);
        $wanDevFlat = $collectLeaves($nodeAt($wanDevBase), $wanDevBase);

        $name = $pickVal($connFlat, ['name', 'devicename']);
        $rawType = $pickVal($connFlat, ['connectiontype']);
        if (str_contains($base, '.WANPPPConnection.')) {
            $type = 'PPPoE';
        } elseif ($rawType !== null) {
            $type = $typeNorm($rawType);
        } else {
            $type = 'IPoE';
        }
        $vlan = $pickVal($connFlat, ['vlanid','vlanidmark','vlan','x_hw_vlan','vlanmode'], true) ?? $pickVal($parentFlat, ['vlanid','vlanidmark','vlan','x_hw_vlan'], true) ?? $pickVal($wanDevFlat, ['vlanid','vlan'], true) ?? $pickVal($parentFlat, ['vlanidvalue', 'outervlanid'], true);
        $service = $pickVal($connFlat, ['servicelist']) ?? $pickVal($parentFlat, ['servicelist']);
        $ip = $pickVal($connFlat, ['externalipaddress']) ?? $pickVal($connFlat, ['ipaddress'])
            ?? $pickVal($parentFlat, ['externalipaddress']) ?? $pickVal($parentFlat, ['ipaddress'])
            ?? $pickVal($wanDevFlat, ['externalipaddress']) ?? $pickVal($wanDevFlat, ['ipaddress']);
        $user = $pickVal($connFlat, ['username']) ?? $pickVal($parentFlat, ['username']);
        $pass = $pickVal($connFlat, ['password','x_cms_password']) ?? $pickVal($parentFlat, ['password']);
        $mtu = $pickVal($connFlat, ['maxmrusize','maxmtu','mru','mtu'], true) ?? $pickVal($parentFlat, ['maxmrusize','maxmtu'], true);
        $lanIf = $pickVal($connFlat, ['laninterface','x_ct-com_laninterface','x_alu-com_laninterface']) ?? $pickVal($parentFlat, ['laninterface']);
        $vlanMode = $pickVal($connFlat, ['vlanmode','x_ct-com_vlanmode'], true);
        $prio = $pickVal($connFlat, ['802-1pmark','8021p','priority'], true) ?? $pickVal($parentFlat, ['802-1pmark'], true);
        $ipModeRaw = $pickVal($connFlat, ['ipmode','x_ct-com_ipmode','x_cms_ipmode','ipversion'], true) ?? $pickVal($parentFlat, ['ipmode'], true);
        $proto = 'IPv4';
        if ($ipModeRaw !== null) {
            $m = strtolower(trim((string)$ipModeRaw));
            if (in_array($m, ['2','ipv6','1pv6','ipv6only'], true)) $proto = 'IPv6';
            elseif (in_array($m, ['3','ipv4v6','dual','both'], true)) $proto = 'IPv4/IPv6';
            else $proto = 'IPv4';
        } elseif (str_contains($base, 'IPv6') || $pickVal($connFlat, ['ipv6enable'], true)) {
            $proto = 'IPv6';
        }
        $gateway = $pickVal($connFlat, ['defaultgateway','gateway','remoteipaddress']) ?? $pickVal($parentFlat, ['defaultgateway']);
        $dns = $pickVal($connFlat, ['dnsservers','dns','dnsoverride']) ?? $pickVal($parentFlat, ['dnsservers']) ?? $pickVal($wanDevFlat, ['dnsservers']);
        $mac = $pickVal($connFlat, ['macaddress','mac']) ?? $pickVal($parentFlat, ['macaddress']) ?? $pickVal($wanDevFlat, ['macaddress']);
        // Status: periksa Enable (bisa boolean) dan ConnectionStatus di connFlat maupun parent
        $connStatus = $pickVal($connFlat, ['connectionstatus']) ?? $pickVal($parentFlat, ['connectionstatus']);
        // Ambil Enable langsung dari tree untuk handle boolean true/false
        $enableRaw = $pickVal($connFlat, ['enable']);
        if ($enableRaw === null) {
            $enNode = $nodeAt($base)['Enable'] ?? $nodeAt($parentBase)['Enable'] ?? null;
            $enVal = is_array($enNode) ? ($enNode['_value'] ?? null) : $enNode;
            if (is_bool($enVal)) $enableRaw = $enVal ? 'true' : 'false';
            elseif ($enVal !== null) $enableRaw = (string)$enVal;
        }
        $hasIp = !empty($pickVal($connFlat, ['externalipaddress','ipaddress'])) && !in_array(trim((string)($pickVal($connFlat, ['externalipaddress','ipaddress']))), ['0.0.0.0','::',''], true);
        $isUp = (in_array(strtolower((string)$connStatus), ['connected','up'], true)) || in_array(strtolower((string)$enableRaw), ['1','true','enabled','enable'], true) || $hasIp;
        $status = $isUp ? 'Up' : 'Down';

        // Cari path aktual untuk VLAN agar edit form bisa set data-path yang tepat (Huawei X_HW_VLAN vs ZTE X_CT-COM)
        $vlanPath = null;
        $findVlanPath = function(array $flat) use ($vlan) {
            if ($vlan === null) return null;
            foreach ($flat as $path => $val) {
                if ((string)$val === (string)$vlan && stripos($path, 'vlan') !== false) return $path;
            }
            return null;
        };
        $vlanPath = $findVlanPath($connFlat) ?? $findVlanPath($parentFlat) ?? $findVlanPath($wanDevFlat);

        return [
            'name' => $name,
            'type' => $typeNorm($type),
            'vlan' => $vlan,
            'vlanPath' => $vlanPath,
            'service' => $service,
            'ip' => $ip,
            'dns' => $dns,
            'mac' => $mac,
            'gateway' => $gateway,
            'proto' => $proto,
            'status' => $status,
            'user' => $user,
            'pass' => $pass,
            'mtu' => $mtu,
            'lanIf' => $lanIf,
            'vlanMode' => $vlanMode,
            'prio' => $prio,
        ];
    };

    $connBases = [];
    foreach (($device['InternetGatewayDevice']['WANDevice'] ?? []) as $wdNum => $wd) {
        if (! is_array($wd)) {
            continue;
        }
        foreach (($wd['WANConnectionDevice'] ?? []) as $cdNum => $cd) {
            if (! is_array($cd)) {
                continue;
            }
            foreach (['WANIPConnection', 'WANPPPConnection'] as $cType) {
                foreach (($cd[$cType] ?? []) as $cNum => $conn) {
                    if (! is_array($conn)) {
                        continue;
                    }
                    $connBases[] = 'InternetGatewayDevice.WANDevice.'.$wdNum.'.WANConnectionDevice.'.$cdNum.'.'.$cType.'.'.$cNum;
                }
            }
        }
    }

    $wanConns = [];
    foreach ($connBases as $base) {
        $info = $connInfo($base);
        if ($info['name'] === null && $info['vlan'] === null && $info['service'] === null && $info['ip'] === null && $info['user'] === null) {
            continue;
        }
        $wanConns[] = ['base' => $base, 'short' => $shortBase($base)] + $info;
    }
    usort($wanConns, function ($a, $b) {
        return strnatcasecmp((string) ($a['name'] ?? $a['short']), (string) ($b['name'] ?? $b['short']));
    });
    $wanConnsTypeMap = [];
    foreach ($wanConns as $c) {
        $key = strtolower((string) ($c['type'] ?? 'IPoE'));
        if ($key === 'ipoe') {
            $key = 'ipe';
        }
        $wname = $c['name'] ?? $c['short'];
        if ($wname !== null && $wname !== '') {
            $wanConnsTypeMap[$wname] = $key;
        }
    }
@endphp

{{-- â•â•â• KARTU INFO â•â•â• --}}
<div class="row g-3 mb-3 justify-content-between acs-info-cards">
    @php
        $wanIp = $s['wan_ip'] ?? null;
        $pppoeIp = $s['pppoe_ip'] ?? null;
        $rx = isset($s['rx_power']) && $s['rx_power'] !== '' && $s['rx_power'] !== null ? (float) $s['rx_power'] : null;
        $temp = isset($s['temperature']) && $s['temperature'] !== '' && $s['temperature'] !== null ? (float) $s['temperature'] : null;
        $swVer = $pathValue('InternetGatewayDevice.DeviceInfo.SoftwareVersion');
        $tagList = is_array($device['_tags'] ?? null) ? $device['_tags'] : [];
    @endphp

    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 px-2 pt-2 pb-0">
                <h6 class="fw-bold mb-0" style="font-size:0.74rem;"><i class="fa-solid fa-circle-info me-1" style="color:var(--primary);"></i>Informasi</h6>
            </div>
            <div class="card-body py-1 px-2">
                <div class="row g-1" style="font-size:0.78rem;">
                    <div class="col-6">
                        <span class="detail-label">Serial:</span>
                        <code class="fw-semibold" style="font-size:0.74rem;">{{ $s['serial'] ?? 'Belum diisi' }}</code>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Type:</span>
                        <span class="fw-semibold">{{ $s['product_class'] ?? ($s['model'] ?? 'Belum diisi') }}</span>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Mode:</span>
                        <span class="fw-semibold">{{ $s['access_type'] ?? 'Ethernet/Converter' }}</span>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Status:</span>
                        <span class="status-dot {{ ($s['online'] ?? false) ? 'bg-success' : 'bg-danger' }}"></span>
                        <strong>{{ ($s['online'] ?? false) ? 'Online' : 'Offline' }}</strong>
                        @if(($s['last_inform'] ?? null) !== null)
                            <span class="text-muted" style="font-size:0.68rem;">{{ $s['last_inform']->format('d M Y H:i:s') }}</span>
                        @else
                            <span class="text-muted" style="font-size:0.68rem;">belum pernah inform</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Versi modem:</span>
                        <code style="font-size:0.74rem;">{{ $swVer !== '' ? $swVer : 'Belum diisi' }}</code>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Tag:</span>
                        @foreach(array_keys($tagList) as $tg)
                            <span class="badge acs-tag">{{ $tg }}</span>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-primary acd-action-btn" id="addTagBtn" title="Tambahkan tag untuk device ini" style="font-size:0.72rem;padding:0.15rem 0.5rem;">
                            <i class="fa-solid fa-plus me-1"></i>Tag
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 px-2 pt-2 pb-0">
                <h6 class="fw-bold mb-0" style="font-size:0.74rem;"><i class="fa-solid fa-link me-1" style="color:var(--primary);"></i>Koneksi</h6>
            </div>
            <div class="card-body py-1 px-2">
                <div class="row g-1" style="font-size:0.78rem;">
                    <div class="col-6">
                        <span class="detail-label">IP WAN / TR069:</span>
                        @if($wanIp !== null && filter_var($wanIp, FILTER_VALIDATE_IP))
                            <a href="http://{{ $wanIp }}" target="_blank" rel="noopener" class="text-decoration-none" title="Buka http://{{ $wanIp }}">
                                <code class="fw-semibold" style="font-size:0.74rem;">{{ $wanIp }}</code>
                            </a>
                        @elseif($wanIp !== null)
                            <code class="fw-semibold" style="font-size:0.74rem;">{{ $wanIp }}</code>
                        @else
                            <span class="text-muted" style="font-size:0.76rem;">Belum diisi</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <span class="detail-label">IP PPPoE:</span>
                        @if($pppoeIp !== null && filter_var($pppoeIp, FILTER_VALIDATE_IP))
                            <a href="http://{{ $pppoeIp }}" target="_blank" rel="noopener" class="text-decoration-none" title="Buka http://{{ $pppoeIp }}">
                                <code class="fw-semibold" style="font-size:0.74rem;">{{ $pppoeIp }}</code>
                            </a>
                        @elseif($pppoeIp !== null)
                            <code class="fw-semibold" style="font-size:0.74rem;">{{ $pppoeIp }}</code>
                        @else
                            <span class="text-muted" style="font-size:0.76rem;">Belum diisi</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <span class="detail-label">PPPoE:</span>
                        <span class="fw-semibold">{{ $s['pppoe_username'] ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Mac:</span>
                        <code style="font-size:0.74rem;">{{ $s['mac'] ?? 'Belum diisi' }}</code>
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Client WiFi:</span>
                        <span class="fw-semibold">{{ $s['wifi_clients'] ?? 0 }} client</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 px-2 pt-2 pb-0">
                <h6 class="fw-bold mb-0" style="font-size:0.74rem;"><i class="fa-solid fa-wave-square me-1" style="color:var(--primary);"></i>Optik</h6>
            </div>
            <div class="card-body py-1 px-2">
                <div class="row g-1" style="font-size:0.78rem;">
                    <div class="col-6">
                        <span class="detail-label">RX Power:</span>
                        @if($rx !== null)
                            <span class="fw-semibold">{{ number_format($rx, 1, '.', '') }} dBm</span>
                            <span class="badge {{ $rx >= -28 ? 'text-bg-success' : 'text-bg-danger' }}" style="font-size:0.62rem;">{{ $rx >= -28 ? 'Normal' : 'Abnormal' }}</span>
                        @else
                            <span class="text-muted" style="font-size:0.76rem;">Belum diisi</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Suhu:</span>
                        @if($temp !== null)
                            <span class="fw-semibold">{{ number_format($temp, 1, '.', '') }}&deg;C</span>
                            <span class="badge {{ $temp <= 70 ? 'text-bg-success' : 'text-bg-danger' }}" style="font-size:0.62rem;">{{ $temp <= 70 ? 'Normal' : 'Abnormal' }}</span>
                        @else
                            <span class="text-muted" style="font-size:0.76rem;">Belum diisi</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <span class="detail-label">Uptime:</span>
                        <span class="fw-semibold">{{ $s['uptime_human'] ?? 'Belum diisi' }}</span>
                        @if(($s['uptime'] ?? 0) > 0)
                            <span class="text-muted" style="font-size:0.66rem;">({{ number_format($s['uptime']) }} detik)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- â•â•â• TABS â•â•â• --}}
<ul class="nav nav-tabs nav-tabs-acs" id="detailTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-wlan" data-bs-toggle="tab" data-bs-target="#pane-wlan" type="button" role="tab" aria-controls="pane-wlan" aria-selected="true">
            <i class="fa-solid fa-wifi me-1"></i>WiFi
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-wan" data-bs-toggle="tab" data-bs-target="#pane-wan" type="button" role="tab" aria-controls="pane-wan" aria-selected="false">
            <i class="fa-solid fa-globe me-1"></i>WAN
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-lan" data-bs-toggle="tab" data-bs-target="#pane-lan" type="button" role="tab" aria-controls="pane-lan" aria-selected="false">
            <i class="fa-solid fa-tv me-1"></i>Perangkat Terhubung
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-user" data-bs-toggle="tab" data-bs-target="#pane-user" type="button" role="tab" aria-controls="pane-user" aria-selected="false">
            <i class="fa-solid fa-list-check me-1"></i>Tugas Antri
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-tr069" data-bs-toggle="tab" data-bs-target="#pane-tr069" type="button" role="tab" aria-controls="pane-tr069" aria-selected="false">
            <i class="fa-solid fa-server me-1"></i>TR069
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-summary" data-bs-toggle="tab" data-bs-target="#pane-summary" type="button" role="tab" aria-controls="pane-summary" aria-selected="false">
            <i class="fa-solid fa-table-list me-1"></i>Semua Parameter
        </button>
    </li>
</ul>

<div class="tab-content tab-pane-acs">
    {{-- ── SEMUA PARAMETER ── --}}
    <div class="tab-pane fade" id="pane-summary" role="tabpanel">
        @php $allRows = $flat($device['InternetGatewayDevice'] ?? $device, 'InternetGatewayDevice'); @endphp
        <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 pt-3 px-3 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-table-list me-1"></i>Semua Parameter ONT</h6>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="text-muted" style="font-size:0.72rem;">{{ count($allRows) }} parameter</span>
                    <div class="input-group input-group-sm" style="width:220px;">
                        <span class="input-group-text bg-white" style="font-size:0.7rem; padding:0.2rem 0.5rem;"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="paramSearch" class="form-control" placeholder="Cari parameter..." autocomplete="off" style="font-size:0.72rem; padding:0.2rem 0.5rem;">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive param-scroll" id="paramTableWrap">
                    {!! $renderParams($allRows) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- â”€â”€ WAN â”€â”€ --}}
    <div class="tab-pane fade wan-modern" id="pane-wan" role="tabpanel">
        <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;" id="cardWan">
            <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center pt-3 px-3 pb-2">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-network-wired me-1"></i>Konfigurasi WAN — ONT</h6>
                    <span class="acs-edit-chip" title="Tambah / ubah konfigurasi WAN connection"><i class="fa-solid fa-pen-to-square me-1"></i>Adjust</span>
                </div>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm acs-btn-xs" id="btnRefreshWan" title="Refresh Objek WAN"><i class="fa-solid fa-arrows-rotate me-1"></i>refresh</button><button type="button" class="btn btn-primary btn-sm acs-btn-grad" id="btnAddWan" style="font-size:0.72rem;padding:0.2rem 0.55rem;"><i class="fa-solid fa-plus me-1"></i>ADD WAN</button></div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 acs-wan-table">
                    <thead>
                        <tr>
                            <th class="ps-3">Connection Name</th>
                            <th>Type</th>
                            <th>VLAN</th>
                            <th>Protokol</th>
                            <th>Service List</th>
                            <th>IP</th>
                            <th>DNS</th>
                            <th>Mac Address</th>
                            <th>Status</th>
                            <th class="text-center" style="width:110px;">Operasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wanConns as $c)
                        @php $chip = ['PPPoE' => 'primary', 'IPoE' => 'info', 'Bridge' => 'secondary', 'Other' => 'dark'][$c['type'] ?? 'IPoE'] ?? 'dark'; @endphp
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $c['name'] ?? $c['short'] }}</td>
                            <td><span class="acs-wan-chip text-bg-{{ $chip }}">{{ $c['type'] }}</span></td>
                            <td><code>{{ $c['vlan'] ?? 'Belum diisi' }}</code></td>
                            <td><span class="badge bg-light text-dark border" style="font-size:0.68rem;">{{ $c['proto'] ?? 'IPv4' }}</span></td>
                            <td>{{ $c['service'] ?? 'Belum diisi' }}</td>
                            <td><code>{{ $c['ip'] ?? 'Belum diisi' }}</code></td>
                            <td><code style="font-size:0.68rem; word-break:break-all;">{{ $c['dns'] ?? 'Belum diisi' }}</code></td>
                            <td><code>{{ $c['mac'] ?? 'Belum diisi' }}</code></td>
                            <td>@php $isUp = ($c['status'] ?? 'Down') === 'Up'; @endphp<span class="badge {{ $isUp ? 'text-bg-success' : 'text-bg-secondary' }}" style="font-size:0.68rem;">{{ $c['status'] ?? 'Down' }}</span></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm acs-btn-xs wan-edit" data-base="{{ $c['base'] }}" data-name="{{ $c['name'] ?? $c['short'] }}" data-type="{{ strtolower($c['type']) }}" data-vlan="{{ $c['vlan'] ?? '' }}" data-vlan-path="{{ $c['vlanPath'] ?? '' }}" data-service="{{ $c['service'] ?? '' }}" data-username="{{ $c['user'] ?? '' }}" data-password="{{ $c['pass'] ?? '' }}" data-mtu="{{ $c['mtu'] ?? '' }}" data-lanif="{{ $c['lanIf'] ?? '' }}" data-prio="{{ $c['prio'] ?? '' }}" title="Edit {{ $c['name'] ?? $c['short'] }}"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button type="button" class="btn btn-outline-danger btn-sm acs-btn-xs wan-delete" data-base="{{ $c['base'] }}" data-name="{{ $c['name'] ?? $c['short'] }}" title="Hapus {{ $c['name'] ?? $c['short'] }}"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4" style="font-size:0.82rem;">Belum ada konfigurasi WAN yang terdeteksi pada device.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3 d-none" style="border-radius:12px;" id="cardWanForm">
            <div class="card-header bg-transparent border-0 pt-3 px-3 pb-2">
                <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-pen-to-square me-1"></i>Add / Configure WAN Connection</h6>
            </div>
            <form id="cfg-wan-connection" class="acs-config-form" autocomplete="off">
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="detail-label d-block mb-1">Connection Name</label>
                            <input type="text" id="wanName" list="wanConnList" class="form-control form-control-sm" placeholder="Pilih konfigurasi ONT yang ada atau ketik nama create new WAN" style="font-size:0.75rem;" autocomplete="off">
                            <datalist id="wanConnList">
                                @foreach($wanConns as $c)
                                <option value="{{ $c['name'] ?? $c['short'] }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="detail-label d-block mb-1">MODE</label>
                            <select id="wanType" class="form-select form-select-sm" style="font-size:0.75rem;">
                                <option value="pppoe">PPPoE</option>
                                <option value="ipe">IPoE</option>
                                <option value="bridge">Bridge</option>
                                <option value="dhcp">DHCP</option>
                                <option value="static">Static</option>
                                <option value="other">OTHER</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end pb-2">
                            {!! $renderToggle('enable_vlan', 'Enable VLAN') !!}
                        </div>
                    </div>

                    <div id="wan-vlan-opts" class="row g-3 mb-3 d-none">
                        {!! $renderFieldHtml('VLAN ID', $renderNum('wanVlanId', 'VLAN ID', 1, 4094, null, 'vlan_id'), 4) !!}
                        {!! $renderFieldHtml('Priority / 802.1p', $renderNum('wanPri', '802.1p', 0, 7, null, 'priority_8021p'), 4) !!}
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-xl-6">
                            <label class="detail-label d-block mb-1">Port Binding</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(['LAN1', 'LAN2', 'LAN3', 'LAN4'] as $port)
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="pb-{{ $port }}">
                                    <label class="form-check-label" for="pb-{{ $port }}" style="font-size:0.75rem;">{{ $port }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12 col-xl-6">
                            <label class="detail-label d-block mb-1">Wifi Binding</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(['SSID1', 'SSID2', 'SSID3', 'SSID4'] as $port)
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="wf-{{ $port }}">
                                    <label class="form-check-label" for="wf-{{ $port }}" style="font-size:0.75rem;">{{ $port }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div id="wanBlockPppoe">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><label class="detail-label d-block mb-1">Service List</label>{!! $renderSel('wanPppoeSvc', ['INTERNET' => 'INTERNET', 'TR069' => 'TR069', 'Other' => 'Other'], 'INTERNET') !!}</div>
                            {!! $renderWanFieldBlock('mtu', 'MTU', 3, '[128,1492]') !!}
                            {!! $renderFieldHtml('IP Version', $renderSel('wanIpVer', ['IPv4' => 'IPv4', 'IPv6' => 'IPv6', 'IPv4v6' => 'IPv4v6'], 'IPv4'), 3) !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">PPP</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderWanFieldBlock('ppp_username', 'Username', 6) !!}
                            {!! $renderWanFieldBlock('ppp_password', 'Password', 6) !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Koneksi PPP</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Authentication Type', $renderSel('wanAuth', ['Auto' => 'Auto', 'PAP' => 'PAP', 'CHAP' => 'CHAP', 'MSCHAPv2' => 'MSCHAPv2'], 'Auto'), 4) !!}
                            {!! $renderFieldHtml('Connection Trigger', $renderWanField('connection_trigger'), 4) !!}
                        </div>
                        <div class="row g-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Pppoe') !!}</div>
                        </div>
                    </div>

                    <div id="wanBlockIpe" class="d-none">
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Basic Configuration</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Mode', $renderSel('wanIpeMode', ['IPOE' => 'IPOE'], 'IPOE'), 3) !!}
                            {!! $renderFieldHtml('Service Type', $renderSel('wanIpeSvc', ['INTERNET' => 'INTERNET', 'TR069' => 'TR069', 'Other' => 'Other'], 'INTERNET'), 3) !!}
                            {!! $renderFieldHtml('IP Protocol', $renderSel('wanIpeIpProto', ['IPv4' => 'IPv4', 'IPv6' => 'IPv6'], 'IPv4'), 3) !!}
                            {!! $renderMtu('wanIpeMtu') !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">IPv4</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Ipe') !!}</div>
                        </div>
                        <div class="row g-3">
                            {!! $renderFieldHtml('Service Type', $renderSel('wanIpeIv4Svc', ['DHCP' => 'DHCP', 'Static IP' => 'Static IP'], 'DHCP'), 4) !!}
                            {!! $renderFieldHtml('Manual DNS', '<div class="form-check form-switch m-0 pt-1"><span class="wan-switch-box"><input class="form-check-input wan-switch" type="checkbox" role="switch" id="wanIpeManualDns"><span class="wan-pos wan-off">OFF</span><span class="wan-pos wan-on">ON</span></span><label class="form-check-label" for="wanIpeManualDns" style="font-size:0.75rem;cursor:pointer;">Aktif</label></div>', 4) !!}
                        </div>
                        <div class="row g-3 mt-1 d-none" id="wanIpeDnsWrap">
                            {!! $renderFieldHtml('Primary DNS', $renderText('wanIpeDns1', 'x.x.x.x,x.x.x.x', 'dns_servers'), 6) !!}
                            {!! $renderFieldHtml('Secondary DNS', $renderText('wanIpeDns2', 'x.x.x.x,x.x.x.x'), 6) !!}
                        </div>
                    </div>

                    <div id="wanBlockDhcp" class="d-none">
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Basic Configuration</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Service List', $renderSel('wanDhcpSvc', ['INTERNET' => 'INTERNET', 'TR069' => 'TR069', 'Other' => 'Other'], 'INTERNET'), 3) !!}
                            {!! $renderMtu('wanDhcpMtu') !!}
                            {!! $renderFieldHtml('IP Version', $renderSel('wanDhcpIpVer', ['IPv4' => 'IPv4', 'IPv6' => 'IPv6'], 'IPv4'), 3) !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">IPv4</h6>
                        <div class="row g-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Dhcp') !!}</div>
                        </div>
                    </div>

                    <div id="wanBlockStatic" class="d-none">
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Basic Configuration</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Service List', $renderSel('wanStatSvc', ['INTERNET' => 'INTERNET', 'TR069' => 'TR069', 'Other' => 'Other'], 'INTERNET'), 3) !!}
                            {!! $renderMtu('wanStatMtu') !!}
                            {!! $renderFieldHtml('IP Version', $renderSel('wanStatIpVer', ['IPv4' => 'IPv4', 'IPv6' => 'IPv6'], 'IPv4'), 3) !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">IPv4</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Stat') !!}</div>
                        </div>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('IP Address', $renderText('wanStatIp', 'x.x.x.x', 'ip_address'), 4) !!}
                            {!! $renderFieldHtml('Subnet Mask', $renderText('wanStatMask', '255.255.255.0', 'subnet_mask'), 4) !!}
                            {!! $renderFieldHtml('Default Gateway', $renderText('wanStatGw', 'x.x.x.x', 'default_gateway'), 4) !!}
                        </div>
                        <div class="row g-3">
                            {!! $renderFieldHtml('Manual DNS', '<div class="form-check form-switch m-0 pt-1"><span class="wan-switch-box"><input class="form-check-input wan-switch" type="checkbox" role="switch" id="wanStatManualDns"><span class="wan-pos wan-off">OFF</span><span class="wan-pos wan-on">ON</span></span><label class="form-check-label" for="wanStatManualDns" style="font-size:0.75rem;cursor:pointer;">Aktif</label></div>', 4) !!}
                        </div>
                        <div class="row g-3 mt-1 d-none" id="wanStatDnsWrap">
                            {!! $renderFieldHtml('DNS1', $renderText('wanStatDns1', 'x.x.x.x', 'dns_servers'), 4) !!}
                            {!! $renderFieldHtml('DNS2', $renderText('wanStatDns2', 'x.x.x.x'), 4) !!}
                            {!! $renderFieldHtml('DNS3', $renderText('wanStatDns3', 'x.x.x.x'), 4) !!}
                        </div>
                    </div>

                    <div id="wanBlockBridge" class="d-none">
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Basic Configuration</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Mode', $renderSel('wanBrMode', ['Bridge' => 'Bridge'], 'Bridge'), 3) !!}
                            {!! $renderFieldHtml('Service Type', $renderSel('wanBrSvc', ['Other' => 'Other', 'INTERNET' => 'INTERNET', 'TR069' => 'TR069'], 'Other'), 3) !!}
                        </div>
                        <h6 class="fw-bold mb-2" style="font-size:0.75rem;color:var(--primary);">Multicast VLAN</h6>
                        <div class="row g-3 mb-3">
                            {!! $renderFieldHtml('Multicast VLAN', '<div class="form-check form-switch m-0 pt-1"><span class="wan-switch-box"><input class="form-check-input wan-switch" type="checkbox" role="switch" id="wanBrMc"><span class="wan-pos wan-off">OFF</span><span class="wan-pos wan-on">ON</span></span><label class="form-check-label" for="wanBrMc" style="font-size:0.75rem;cursor:pointer;">Aktif</label></div>', 4) !!}
                            {!! $renderFieldHtml('VLAN ID', $renderNum('wanBrMcId', '100', 1, 4094), 4) !!}
                        </div>
                        <div class="row g-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Bridge') !!}</div>
                        </div>
                    </div>

                    <div id="wanBlockOther" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-12">{!! $renderToggle('enable_nat', 'Enable NAT', 'Other') !!}</div>
                        </div>
                        <div class="text-muted py-2" style="font-size:0.72rem;">Tipe OTHER — kolom VLAN, Port Binding, dan Wifi Binding dapat diisi di atas.</div>
                    </div>
                </div>

                <div class="p-3 d-flex justify-content-end gap-2 border-top" style="border-color:var(--border-subtle);">
                    <button type="button" class="btn btn-outline-secondary acd-action-btn" id="btnWanCancel"><i class="fa-solid fa-xmark me-1"></i>Batal</button>
                    <button type="submit" class="btn btn-primary acd-action-btn"><i class="fa-solid fa-save me-1"></i>Apply</button>
                </div>
            </form>
        </div>
    </div>

    {{-- â”€â”€ LAN (konfigurasi) â”€â”€ --}}
    <div class="tab-pane fade" id="pane-lan" role="tabpanel">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="text-muted" style="font-size:0.82rem;">
                <strong class="fw-semibold">{{ count($wifiClients) }}</strong>&nbsp;WiFi &nbsp;&middot;&nbsp;
                <strong class="fw-semibold">{{ count($lanClients) }}</strong>&nbsp;LAN
            </span>
            <button type="button" class="btn btn-sm acd-action-btn" id="clientsRefreshBtn" title="Muat ulang daftar perangkat terhubung">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
            </button>
        </div>

        {{-- WiFi / WLAN clients --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 pb-0 pt-3">
                <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-wifi me-1"></i>Koneksi WiFi (WLAN)</h6>
            </div>
            <div class="card-body p-0">
                @if(count($wifiClients) > 0)
                <div class="clients-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Perangkat</th>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Sumber</th>
                                <th>Status</th>
                                <th class="px-3">Via</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($wifiClients as $c)
                            <tr>
                                <td class="px-3">{{ $c['name'] !== '' ? $c['name'] : 'Belum diisi' }}</td>
                                <td><code>{{ $c['mac'] }}</code></td>
                                <td>{{ $c['ip'] !== '' ? $c['ip'] : 'Belum diisi' }}</td>
                                <td class="text-muted">WiFi</td>
                                <td><span class="badge text-bg-success" style="font-size:0.68rem;">Aktif</span></td>
                                <td class="px-3"><span class="badge text-bg-dark" style="font-size:0.65rem;">{{ $c['ssid'] !== '' ? $c['ssid'] : 'SSID belum diisi' }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-muted text-center py-4" style="font-size:0.85rem;">
                    <i class="fa-solid fa-wifi me-1"></i>Tidak ada client yang terhubung via WiFi.
                </div>
                @endif
            </div>
        </div>

        {{-- LAN clients --}}
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header bg-transparent border-0 pb-0 pt-3">
                <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-tv me-1"></i>Koneksi LAN (Modem &amp; Perangkat)</h6>
            </div>
            <div class="card-body p-0">
                @if(count($lanClients) > 0)
                <div class="clients-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Perangkat</th>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Sumber</th>
                                <th>Status</th>
                                <th class="px-3">Via</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lanClients as $c)
                            <tr>
                                <td class="px-3">{{ $c['name'] !== '' ? $c['name'] : 'Belum diisi' }}</td>
                                <td><code>{{ $c['mac'] }}</code></td>
                                <td>{{ $c['ip'] !== '' ? $c['ip'] : 'Belum diisi' }}</td>
                                <td class="text-muted">{{ $c['source'] !== '' ? ucfirst(mb_strtolower($c['source'])) : 'Belum diisi' }}</td>
                                <td><span class="badge text-bg-success" style="font-size:0.68rem;">Aktif</span></td>
                                <td class="px-3">
                                    @if($c['modem'])
                                    <span class="badge text-bg-warning text-dark" style="font-size:0.68rem;"><i class="fa-solid fa-server me-1"></i>Modem/Gateway</span>
                                    @else
                                    <span class="badge text-bg-light text-dark border" style="font-size:0.68rem;">Client</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-muted text-center py-4" style="font-size:0.85rem;">
                    <i class="fa-solid fa-tv me-1"></i>Tidak ada perangkat yang terhubung via LAN.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- â”€â”€ WLAN (konfigurasi langsung dari aplikasi) â”€â”€ --}}
    <div class="tab-pane fade show active" id="pane-wlan" role="tabpanel">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button type="button" class="btn btn-outline-secondary acd-action-btn" id="wlanRefreshBtn" title="Refresh WLAN">
                <i class="fa-solid fa-arrows-rotate me-1"></i>Refresh
            </button>
            <button type="button" class="btn btn-primary acd-action-btn wan-btn-grad" id="wlanAddBtn" title="Aktifkan slot WLAN kosong">
                <i class="fa-solid fa-plus me-1"></i>Tambah WLAN
            </button>
            <span class="text-muted ms-2" style="font-size:0.78rem;" id="wlanInfo">
                <strong class="fw-semibold">{{ $wlanDetected }}</strong>&nbsp;WLAN Terdeteksi,&nbsp;
                <strong class="fw-semibold">{{ $wlanActive }}</strong>&nbsp;WLAN Aktif
            </span>
        </div>

        @if($wlanDetected > 0)
        <div class="row g-2">
            @foreach($wlanBoxes as $b)
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 wlan-box"
                     data-base="{{ $b['base'] }}"
                     data-instance="{{ $b['instance'] }}"
                     data-active="{{ $b['active'] ? '1' : '0' }}"
                     data-enable-path="{{ $b['enablePath'] ?? '' }}"
                     data-ssid-path="{{ $b['ssidPath'] ?? '' }}"
                     data-key-path="{{ $b['keyPath'] ?? '' }}">
                    <div class="card-header border-0 bg-transparent d-flex align-items-center px-2 py-1">
                        <div class="me-auto d-flex align-items-center gap-2">
                            <span class="fw-bold" style="font-size:0.78rem;">WLAN{{ $b['instance'] }}</span>
                            <span class="acs-wan-chip" style="font-size:0.68rem;background:rgba(var(--primary-rgb),0.08);color:var(--primary);">{{ $b['mode'] }}</span>
                        </div>
                        @if($b['enablePath'])
                        <span class="wan-switch-box me-2" title="{{ $b['active'] ? 'Nonaktifkan' : 'Aktifkan' }} WLAN ini">
                            <input type="checkbox" class="form-check-input wan-switch wlan-toggle" role="switch"{{ $b['active'] ? ' checked' : '' }}>
                            <span class="wan-pos wan-off">OFF</span>
                            <span class="wan-pos wan-on">ON</span>
                        </span>
                        @else
                        <span class="text-muted me-2" style="font-size:0.7rem;" title="Enable tidak tersedia (bukan parameter writable)">&mdash;</span>
                        @endif
                        <button type="button" class="btn btn-sm wlan-del" title="Hapus WLAN" style="color:#dc2626;padding:0.1rem 0.35rem;font-size:0.72rem;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                    <div class="card-body pt-0 px-2 pb-2">
                        <label class="detail-label d-block mb-1" style="font-size:0.68rem;">SSID</label>
                        <input type="text" class="form-control form-control-sm mb-2 wlan-ssid" maxlength="32" value="{{ $b['active'] && $b['ssid'] !== '' ? $b['ssid'] : '' }}" placeholder="{{ $b['active'] && $b['ssid'] !== '' ? '' : 'Belum diisi' }}"{{ $b['ssidPath'] ? '' : ' disabled' }} style="font-size:0.75rem;">
                        <label class="detail-label d-block mb-1" style="font-size:0.68rem;">Password</label>
                        <div class="input-group input-group-sm mb-2">
                            <input type="password" class="form-control wlan-pass" value="{{ $b['keyPath'] ? e($b['pass']) : '' }}" data-orig="{{ $b['keyPath'] ? e($b['pass']) : '' }}" placeholder="{{ $b['pass'] !== '' ? '' : 'Password baru...' }}" autocomplete="new-password"{{ $b['keyPath'] ? '' : ' disabled' }} style="font-size:0.75rem;">
                            <button type="button" class="btn btn-outline-secondary wlan-eye" tabindex="-1"{{ $b['keyPath'] ? '' : ' disabled' }} style="padding:0.1rem 0.4rem;"><i class="fa-regular fa-eye"></i></button>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm wan-btn-grad wlan-apply w-100"{{ $b['ssidPath'] || $b['keyPath'] ? '' : ' disabled' }} style="font-size:0.75rem;padding:0.2rem 0.5rem;">
                            <i class="fa-solid fa-check me-1"></i>Apply
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-muted text-center py-4" style="font-size:0.85rem;">
            <i class="fa-solid fa-wifi me-1"></i>Tidak ada WLAN terdeteksi pada device ini. Klik <strong>Refresh</strong> untuk memindai ulang.
        </div>
        @endif
    </div>

    {{-- â”€â”€ USER â”€â”€ --}}
    <div class="tab-pane fade" id="pane-user" role="tabpanel">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="text-muted" style="font-size:0.82rem;">
                <strong class="fw-semibold">{{ count($tasks ?? []) }}</strong>&nbsp;tugas tercatat
            </span>
            <button type="button" class="btn btn-sm acd-action-btn" id="tasksRefreshBtn" title="Muat ulang antrian tugas">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
            </button>
        </div>

        @if(count($tasks ?? []) > 0)
        <div class="card border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-0">
                <div class="table-responsive param-scroll">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Waktu</th>
                                <th>Tugas</th>
                                <th>Status</th>
                                <th class="px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tasks ?? [] as $t)
                            @php
                                $status = strtolower((string) ($t['status'] ?? 'created'));
                                $badge = match ($status) {
                                    'completed' => 'success',
                                    'error' => 'danger',
                                    'active' => 'primary',
                                    default => 'secondary',
                                };
                                $when = isset($t['timestamp']) && $t['timestamp'] !== '' ? \Illuminate\Support\Carbon::parse((string) $t['timestamp'])->format('d M Y H:i:s') : 'Belum diisi';
                                $fault = $t['fault'] ?? null;
                                $taskName = $t['name'] ?? 'Belum diisi';
                                $objectName = $t['objectName'] ?? '';
                                $paramsList = $t['parameters'] ?? null;
                                $paramsSummary = '';
                                if (is_array($paramsList) && count($paramsList) > 0) {
                                    $leaf = function ($p) {
                                        $parts = explode('.', (string) $p);
                                        return count($parts) > 3 ? implode('.', array_slice($parts, -3)) : (string) $p;
                                    };
                                    $items = [];
                                    foreach ($paramsList as $pr) {
                                        $leafName = $leaf($pr['name'] ?? '');
                                        $val = (string) ($pr['value'] ?? '');
                                        $isSecret = mb_stripos((string) ($pr['name'] ?? ''), 'password') !== false
                                            || mb_stripos((string) ($pr['name'] ?? ''), 'keypassphrase') !== false;
                                        $items[] = $leafName !== '' ? $leafName.($val !== '' ? ': '.($isSecret ? str_repeat('*', 8) : $val) : '') : $val;
                                    }
                                    $shown = array_slice($items, 0, 2);
                                    $paramsSummary = implode(', ', $shown).(count($items) > 2 ? ' (+'.(count($items) - 2).')' : '');
                                } elseif ((string) $taskName === 'setParameterValues') {
                                    $paramsSummary = 'parameter konfigurasi ACS';
                                }
                            @endphp
                            <tr>
                                <td class="px-3 text-muted text-nowrap">{{ $when }}</td>
                                <td>
                                    <strong>{{ $taskName }}</strong>
                                    @if(! empty($t['type']) && $t['type'] === 'immediate')
                                    <span class="badge text-bg-light ms-1" style="font-size:0.65rem;">immediate</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $badge }}" style="font-size:0.68rem;text-transform:capitalize;">{{ $status }}</span>
                                </td>
                                <td class="px-3 text-muted">
                                    @if($status === 'error' && is_array($fault))
                                        {{ $fault['title'] ?? $fault['message'] ?? 'Gagal' }}
                                    @elseif($objectName !== '')
                                        <code class="small">{{ $objectName }}</code>
                                    @elseif($paramsSummary !== '')
                                        <code class="small">{{ $paramsSummary }}</code>
                                    @else
                                        Belum diisi
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="text-muted text-center py-4" style="font-size:0.85rem;">
            <i class="fa-solid fa-list-check me-1"></i>Tidak ada tugas yang sedang antri. Tugas muncul di sini saat menambah/mengubah WAN, WLAN, dsb.
        </div>
        @endif
    </div>

    {{-- â”€â”€ TR069 â”€â”€ --}}
    <div class="tab-pane fade" id="pane-tr069" role="tabpanel">
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-sm acd-action-btn" id="tr069RefreshBtn" title="Muat ulang nilai ACS/TR-069 dari device">
                <i class="fa-solid fa-rotate me-1"></i>Refresh
            </button>
        </div>
        <form id="cfg-tr069" class="acs-config-form" autocomplete="off">
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center pt-3 px-3 pb-2">
                    <h6 class="fw-bold mb-0" style="font-size:0.8rem;"><i class="fa-solid fa-tower-broadcast me-1"></i>Konfigurasi ACS (TR-069) &mdash; ONT</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($tr069Fields as $label => $field)
                        @php [$path, $type] = $field; @endphp
                        <div class="col-md-6">
                            <label class="detail-label d-block mb-1">{{ $label }}</label>
                            {!! $renderConfigInput($path, $type, $label) !!}
                        </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary acs-btn-grad btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
const deviceId = @json($deviceId);
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function postAction(url, body = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'OK' : 'Failed'));
        if (data.success) location.reload();
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

document.getElementById('btnSummon')?.addEventListener('click', () => {
    if (confirm('Summon device ' + deviceId + '? Perangkat akan diminta inform segera.'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/summon');
});

document.getElementById('btnRefreshAll')?.addEventListener('click', () => {
    if (confirm('Refresh semua parameter device ' + deviceId + '?'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/refresh', { object: 'InternetGatewayDevice' });
});

document.getElementById('btnReboot')?.addEventListener('click', () => {
    if (confirm('Reboot ONT ' + deviceId + '? Perangkat akan restart.'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/reboot');
});

document.getElementById('btnFactoryReset')?.addEventListener('click', () => {
    if (confirm('FACTORY RESET device ' + deviceId + '? Semua konfigurasi akan hilang!'))
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/factory-reset');
});

document.getElementById('btnUpgrade')?.addEventListener('click', () => {
    const file = prompt('Nama file firmware untuk upgrade:', '');
    if (file && file.trim())
        postAction('/noc/genieacs/' + encodeURIComponent(deviceId) + '/download', { file: file.trim() });
});

document.getElementById('btnDelete')?.addEventListener('click', async () => {
    if (!confirm('Hapus device ' + deviceId + ' dari GenieACS? Tindakan tidak dapat dibatalkan.')) return;
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'OK' : 'Failed'));
        if (data.success) window.location.href = '{{ route('noc.genieacs.devices') }}';
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

document.getElementById('paramSearch')?.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#pane-summary table tbody tr');
    let visible = 0;
    rows.forEach(tr => {
        const txt = tr.innerText.toLowerCase();
        const show = !q || txt.includes(q);
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    // update count
    const cnt = document.querySelector('#pane-summary .card-header span.text-muted');
    if (cnt) cnt.textContent = visible + ' / ' + rows.length + ' parameter';
});

document.getElementById('btnRefreshWan')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnRefreshWan');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>refresh';
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/refresh', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrfToken, 'Accept':'application/json','Content-Type':'application/json'},
            body: JSON.stringify({object: 'InternetGatewayDevice.WANDevice'})
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'Refresh dikirim' : 'Failed'));
        if (data.success) setTimeout(()=> location.reload(), 2000);
    } catch(e){ alert('Error: '+e.message); }
    btn.disabled = false;
    btn.innerHTML = orig;
});

const wanType = document.getElementById('wanType');
const wanBlocks = {
    pppoe: document.getElementById('wanBlockPppoe'),
    ipe: document.getElementById('wanBlockIpe'),
    bridge: document.getElementById('wanBlockBridge'),
    dhcp: document.getElementById('wanBlockDhcp'),
    static: document.getElementById('wanBlockStatic'),
    other: document.getElementById('wanBlockOther'),
};
const wanVlanId = document.getElementById('wanVlanId');
const wanPri = document.getElementById('wanPri');
const wanName = document.getElementById('wanName');
const wanConnsMap = @json($wanConnsTypeMap ?? []);
const wanConnNames = Object.keys(wanConnsMap);
const isExistingWan = () => wanConnNames.includes((wanName?.value || '').trim());
let wanNameAuto = true;
const wanDnsGroups = [
    ['wanIpeManualDns', 'wanIpeDnsWrap'],
    ['wanStatManualDns', 'wanStatDnsWrap'],
];
const wanSvcSelections = {
    pppoe: document.getElementById('wanPppoeSvc'),
    ipe: document.getElementById('wanIpeSvc'),
    dhcp: document.getElementById('wanDhcpSvc'),
    static: document.getElementById('wanStatSvc'),
    bridge: document.getElementById('wanBrSvc'),
};
const currentServiceList = () => {
    const sel = wanSvcSelections[wanType?.value] ?? null;
    return (sel && sel.value && sel.value.trim() !== '') ? sel.value.trim() : 'INTERNET';
};
for (const sel of Object.values(wanSvcSelections)) {
    if (sel) sel.addEventListener('change', () => { if (wanNameAuto) suggestWanName(); });
}
function applyDnsToggles() {
    for (const [toggleId, wrapId] of wanDnsGroups) {
        const t = document.getElementById(toggleId);
        const w = document.getElementById(wrapId);
        if (t && w) w.classList.toggle('d-none', !t.checked);
    }
}
function suggestWanName() {
    if (!wanName) return;
    const vid = (wanVlanId && wanVlanId.value && wanVlanId.value.trim() !== '') ? wanVlanId.value.trim() : '100';
    const mode = wanType?.value === 'bridge' ? 'B' : 'R';
    const next = currentServiceList() + '_' + mode + '_VID_' + vid + '_O';
    if (wanNameAuto || wanName.value.trim() === '') {
        wanName.value = next;
        wanNameAuto = true;
    }
}
function applyWanType() {
    const t = wanType.value;
    for (const [k, el] of Object.entries(wanBlocks)) if (el) el.classList.toggle('d-none', k !== t);
    if (!isExistingWan()) {
        const defs = { pppoe: { v: '', p: '' }, ipe: { v: '300', p: '5' }, bridge: { v: '200', p: '0' }, dhcp: { v: '', p: '' }, static: { v: '', p: '' }, other: { v: '', p: '' } };
        if (wanVlanId) wanVlanId.value = defs[t].v;
        if (wanPri) wanPri.value = defs[t].p;
        suggestWanName();
    }
}
if (wanType) { wanType.addEventListener('change', applyWanType); applyWanType(); }
for (const [toggleId, wrapId] of wanDnsGroups) {
    const t = document.getElementById(toggleId);
    if (t) t.addEventListener('change', applyDnsToggles);
}
applyDnsToggles();

const wanVlanToggle = document.getElementById('wan-enable_vlan');
const wanVlanOpts = document.getElementById('wan-vlan-opts');
function applyVlan() { if (wanVlanOpts) wanVlanOpts.classList.toggle('d-none', !wanVlanToggle.checked); }
if (wanVlanToggle) { wanVlanToggle.addEventListener('change', applyVlan); applyVlan(); }

const btnAddWan = document.getElementById('btnAddWan');
const cardWanForm = document.getElementById('cardWanForm');
const btnWanCancel = document.getElementById('btnWanCancel');
if (btnAddWan && cardWanForm) {
    btnAddWan.addEventListener('click', () => {
        cardWanForm.classList.remove('d-none');
        btnAddWan.classList.add('d-none');
        cardWanForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Otomatis isi MTU 1492 jika masih kosong
        setTimeout(() => {
            document.querySelectorAll('#cardWanForm input[placeholder*="1492"]').forEach(inp => {
                if (!inp.value) inp.value = '1492';
                if (inp.disabled) { inp.disabled = false; inp.removeAttribute('disabled'); }
            });
        }, 100);
    });
}
if (btnWanCancel && cardWanForm) {
    btnWanCancel.addEventListener('click', () => {
        cardWanForm.classList.add('d-none');
        btnAddWan.classList.remove('d-none');
    });
}

if (wanVlanId) {
    wanVlanId.addEventListener('input', () => { if (wanNameAuto) suggestWanName(); });
}
if (wanName) {
    wanName.addEventListener('input', () => { wanNameAuto = false; });
    wanName.addEventListener('change', () => {
        const name = wanName.value.trim();
        if (wanConnNames.includes(name)) {
            wanNameAuto = false;
            const t = wanConnsMap[name] || 'ipe';
            if (t !== wanType.value) { wanType.value = t; applyWanType(); }
        }
    });
}
document.getElementById('cfg-wan-connection')?.addEventListener('submit', suggestWanName);

// WAN list: Edit & Hapus aksi
document.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.wan-edit');
    if (editBtn) {
        const name = editBtn.dataset.name || '';
        const type = (editBtn.dataset.type || 'ipe').toLowerCase();
        const base = editBtn.dataset.base || '';
        const vlan = editBtn.dataset.vlan || '';
        const prio = editBtn.dataset.prio || '';
        const mtu = editBtn.dataset.mtu || '';
        const username = editBtn.dataset.username || '';
        const password = editBtn.dataset.password || '';
        const lanIf = editBtn.dataset.lanif || '';
        if (wanName) { wanName.value = name; wanNameAuto = false; }
        const typeMap = {pppoe:'pppoe', ipoe:'ipe', ipe:'ipe', bridge:'bridge', dhcp:'dhcp', static:'static'};
        if (wanType && typeMap[type]) { wanType.value = typeMap[type]; applyWanType(); }
        // Populate VLAN - gunakan path aktual dari device agar sesuai vendor (Huawei X_HW_VLAN vs ZTE X_CT-COM)
        if (wanVlanId) {
            wanVlanId.value = vlan;
            const vlanPath = editBtn.dataset.vlanPath || '';
            if (vlanPath) wanVlanId.dataset.path = vlanPath;
            else if (base && vlan) wanVlanId.dataset.path = base + '.X_HW_VLAN';
            if (vlan) wanVlanId.dataset.orig = vlan;
        }
        if (wanPri) {
            wanPri.value = prio;
            if (base && prio) wanPri.dataset.path = base + '.X_CT-COM_802-1pMark';
        }
        if (wanVlanToggle) { wanVlanToggle.checked = !!vlan; applyVlan(); }
        // Populate PPPoE / IP fields after block is visible
        setTimeout(() => {
            const pppUser = document.querySelector('#wanBlockPppoe [data-path*="Username"]');
            if (pppUser) {
                pppUser.value = username;
                if (base) pppUser.dataset.path = base + '.Username';
                pppUser.dataset.orig = username;
            }
            const pppPass = document.querySelector('#wanBlockPppoe [data-path*="Password"]');
            if (pppPass) {
                pppPass.value = password;
                if (base) {
                    // Prefer X_CMS_Password for this vendor, fallback to Password
                    const isXcms = pppPass.dataset.path && pppPass.dataset.path.includes('X_CMS');
                    pppPass.dataset.path = base + (isXcms ? '.X_CMS_Password' : '.Password');
                }
                pppPass.dataset.orig = password;
            }
            // MTU - otomatis 1492 jika kosong, handle input yang disabled/tanpa data-path
            const mtuVal = mtu || '1492';
            let mtuEl = document.querySelector('#wanBlockPppoe [data-path*="MaxMRUSize"], #wanBlockPppoe [data-path*="MTU"]');
            if (!mtuEl) mtuEl = document.querySelector('#wanBlockPppoe .row.g-3.mb-3 > div:nth-child(2) input');
            if (mtuEl) {
                if (mtuEl.disabled) { mtuEl.disabled = false; mtuEl.removeAttribute('disabled'); }
                mtuEl.value = mtuVal;
                if (base) mtuEl.dataset.path = base + '.MaxMRUSize';
                mtuEl.dataset.orig = mtuVal;
                // ensure not disabled
                mtuEl.classList.remove('text-muted');
            }
            // Also handle IPoE MTU and other blocks
            const mtuSelectors = ['#wanBlockIpe [data-path*="MaxMRUSize"]','#wanBlockIpe [data-path*="MTU"]','#wanBlockIpe .row.g-3.mb-3 > div:nth-child(2) input','#wanBlockPppoe .row.g-3.mb-3 > div:nth-child(2) input'];
            // Ensure any visible MTU input in active block gets 1492 if empty
            document.querySelectorAll('.wan-modern .form-control, #cardWanForm .form-control').forEach(inp => {
                if (inp.placeholder && inp.placeholder.includes('1492') && !inp.value) {
                    inp.value = '1492';
                }
            });
            const mtuIpe = document.querySelector('#wanBlockIpe [data-path*="MaxMRUSize"], #wanBlockIpe [data-path*="MTU"]');
            if (mtuIpe && wanType && wanType.value === 'ipe') {
                let mipVal = mtu || '1492';
                if (mtuIpe.disabled) { mtuIpe.disabled = false; mtuIpe.removeAttribute('disabled'); }
                mtuIpe.value = mipVal;
                if (base) mtuIpe.dataset.path = base + '.MaxMRUSize';
                mtuIpe.dataset.orig = mipVal;
            }
            // Port Binding & Wifi Binding checkboxes
            if (lanIf) {
                const lans = lanIf.split(',').map(s=>s.trim().toUpperCase());
                ['LAN1','LAN2','LAN3','LAN4'].forEach(port => {
                    const cb = document.getElementById('pb-'+port);
                    if (cb) cb.checked = lans.includes(port);
                });
                ['SSID1','SSID2','SSID3','SSID4'].forEach(ssid => {
                    const cb = document.getElementById('wf-'+ssid);
                    if (cb) cb.checked = lans.includes(ssid) || lans.includes(ssid.replace('SSID','WLAN'));
                });
            } else {
                ['LAN1','LAN2','LAN3','LAN4'].forEach(port => {
                    const cb = document.getElementById('pb-'+port);
                    if (cb) cb.checked = false;
                });
                ['SSID1','SSID2','SSID3','SSID4'].forEach(ssid => {
                    const cb = document.getElementById('wf-'+ssid);
                    if (cb) cb.checked = false;
                });
            }
        }, 80);
        if (cardWanForm && cardWanForm.classList.contains('d-none')) {
            cardWanForm.classList.remove('d-none');
            btnAddWan?.classList.add('d-none');
        }
        cardWanForm?.scrollIntoView({behavior:'smooth', block:'start'});
        return;
    }
    const delBtn = e.target.closest('.wan-delete');
    if (delBtn) {
        const base = delBtn.dataset.base || '';
        const name = delBtn.dataset.name || base;
        if (!base) { alert('Base WAN tidak ditemukan.'); return; }
        if (!confirm('Hapus konfigurasi WAN "' + name + '" (' + base + ')? Tindakan akan menghapus instance WAN tersebut.')) return;
        delBtn.disabled = true;
        try {
            const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/delete-object', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrfToken, 'Accept':'application/json', 'Content-Type':'application/json'},
                body: JSON.stringify({object: base})
            });
            const data = await res.json();
            alert(data.message || (data.success ? 'OK' : 'Failed'));
            if (data.success) setTimeout(()=> location.reload(), 2000);
        } catch (err) { alert('Error: '+err.message); }
        delBtn.disabled = false;
    }
});

async function saveConfig(form) {
    const parameters = [];
    form.querySelectorAll('[data-path]').forEach((el) => {
        if (el.closest('.d-none')) return;
        const value = el.tagName === 'SELECT'
            ? el.value
            : (el.type === 'checkbox' ? (el.checked ? 'true' : 'false') : el.value.trim());
        if (value === '' || value === (el.dataset.orig ?? '')) return;
        parameters.push({ path: el.dataset.path, value: String(value) });
    });
    if (!parameters.length) {
        alert('Tidak ada perubahan yang akan disimpan pada form ini.');
        return;
    }
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/params', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ parameters }),
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'OK' : 'Failed'));
        if (data.success) setTimeout(() => location.reload(), 2500);
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

document.querySelectorAll('.acs-config-form').forEach((f) => {
    f.addEventListener('submit', (e) => {
        e.preventDefault();
        if (confirm('Kirim konfigurasi ini ke device? Perubahan langsung berlaku pada layanan.'))
            saveConfig(f);
    });
});

// ── Tab WiFi: konfigurasi WLAN ONT langsung dari aplikasi (event delegation) ──
const wlanBoxOf = (el) => el.closest('.wlan-box');

async function wlanPost(parameters, reloadDelay = 2500) {
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/params', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ parameters }),
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'OK' : 'Failed'));
        if (data.success && reloadDelay) setTimeout(() => location.reload(), reloadDelay);
        return data.success === true;
    } catch (e) {
        alert('Error: ' + e.message);
        return false;
    }
}

// Toggle ON/OFF per WLAN
document.addEventListener('change', async (e) => {
    if (!e.target.classList.contains('wlan-toggle')) return;
    const sw = e.target;
    const box = wlanBoxOf(sw);
    const enablePath = box?.dataset.enablePath;
    if (!enablePath) {
        sw.checked = !sw.checked;
        alert('WLAN ini tidak bisa diaktifkan/nonaktifkan (parameter Enable tidak writable).');
        return;
    }
    sw.disabled = true;
    const ok = await wlanPost([{ path: enablePath, value: sw.checked ? 'true' : 'false' }]);
    sw.disabled = false;
    if (!ok) sw.checked = !sw.checked;
});

document.addEventListener('click', async (e) => {
    // Lihat/sembunyikan password
    const eye = e.target.closest('.wlan-eye');
    if (eye) {
        const input = eye.closest('.input-group')?.querySelector('.wlan-pass');
        if (input) {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const ico = eye.querySelector('i');
            if (ico) ico.className = show ? 'fa-solid fa-eye-slash' : 'fa-regular fa-eye';
        }
        return;
    }

    const pwEye = e.target.closest('.acs-pw-eye');
    if (pwEye) {
        const input = pwEye.closest('.input-group')?.querySelector('.acs-pw-input');
        if (input) {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const ico = pwEye.querySelector('i');
            if (ico) ico.className = show ? 'fa-solid fa-eye-slash' : 'fa-regular fa-eye';
        }
        return;
    }

    // Apply SSID/password baru
    const apply = e.target.closest('.wlan-apply');
    if (apply) {
        const box = wlanBoxOf(apply);
        const ssidPath = box?.dataset.ssidPath;
        const keyPath = box?.dataset.keyPath;
        const ssidInput = box?.querySelector('.wlan-ssid');
        const keyInput = box?.querySelector('.wlan-pass');
        const ssid = (ssidInput?.value || '').trim();
        const pass = (keyInput?.value || '').trim();
        const passOrig = (keyInput?.dataset.orig || '').trim();
        const passChanged = pass !== '' && pass !== passOrig;

        if (ssid.length > 32) {
            alert('SSID maksimal 32 karakter.');
            ssidInput?.focus();
            return;
        }
        if (pass !== '' && pass.length < 8) {
            alert('Password minimal 8 karakter.');
            keyInput?.focus();
            return;
        }

        const parameters = [];
        if (ssidPath && ssid !== '') parameters.push({ path: ssidPath, value: ssid });
        if (keyPath && passChanged) parameters.push({ path: keyPath, value: pass });

        if (!parameters.length) {
            alert('Isi SSID dan/atau password baru terlebih dahulu.');
            (ssidInput || keyInput)?.focus();
            return;
        }

        const what = [
            ssidPath && ssid !== '' ? 'SSID "' + ssid + '"' : null,
            keyPath && passChanged ? 'password baru' : null,
        ].filter(Boolean).join(' dan ') || 'perubahan';

        if (!confirm('Terapkan ' + what + ' untuk ' + box.dataset.base + '?')) return;

        apply.disabled = true;
        const ok = await wlanPost(parameters);
        apply.disabled = false;
        if (ok && keyInput && passChanged) keyInput.value = '';
        return;
    }

    // Hapus WLAN → nonaktifkan + kosongkan SSID/kunci
    const del = e.target.closest('.wlan-del');
    if (del) {
        const box = wlanBoxOf(del);
        if (!box) return;
        const instance = box.dataset.instance;
        if (!confirm('Hapus WLAN' + instance + '? WLAN akan dinonaktifkan dan konfigurasi SSID/kunci dikosongkan.')) return;
        const params = [];
        const en = box.dataset.enablePath;
        if (en) params.push({ path: en, value: 'false' });
        const ss = box.dataset.ssidPath;
        if (ss) params.push({ path: ss, value: '' });
        const kp = box.dataset.keyPath;
        if (kp) params.push({ path: kp, value: '' });
        if (!params.length) {
            alert('Tidak ada parameter writable untuk menghapus WLAN ini.');
            return;
        }
        del.disabled = true;
        await wlanPost(params);
        del.disabled = false;
    }
});

// Refresh pemindaian WLAN
document.getElementById('wlanRefreshBtn')?.addEventListener('click', async () => {
    const btn = document.getElementById('wlanRefreshBtn');
    const ico = btn.querySelector('i');
    const old = ico ? ico.className : '';
    btn.disabled = true;
    if (ico) ico.className = 'fa-solid fa-spinner fa-spin';
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/refresh', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ object: 'InternetGatewayDevice.LANDevice' }),
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'Refresh dikirim' : 'Failed'));
        if (data.success) setTimeout(() => location.reload(), 4500);
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        setTimeout(() => { btn.disabled = false; if (ico) ico.className = old; }, 4500);
    }
});

// +Tambah WLAN → aktifkan slot kosong pertama lalu fokus ke box-nya
document.getElementById('wlanAddBtn')?.addEventListener('click', async () => {
    const inactive = Array.from(document.querySelectorAll('.wlan-box')).find((b) => b.dataset.active === '0');
    if (!inactive) {
        alert('Semua slot WLAN sudah aktif.');
        return;
    }
    const enablePath = inactive.dataset.enablePath;
    if (!enablePath) {
        alert('Slot WLAN' + inactive.dataset.instance + ' tidak bisa diaktifkan (Enable tidak writable).');
        return;
    }
    sessionStorage.setItem('wlan_focus', inactive.dataset.base);
    const ok = await wlanPost([{ path: enablePath, value: 'true' }]);
    if (!ok) sessionStorage.removeItem('wlan_focus');
});

// Setelah reload dari +Tambah WLAN: sorot & fokus box target
(function focusWlanTarget() {
    const base = sessionStorage.getItem('wlan_focus');
    if (!base) return;
    sessionStorage.removeItem('wlan_focus');
    const box = document.querySelector('.wlan-box[data-base="' + CSS.escape(base) + '"]');
    if (!box) return;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    box.style.boxShadow = '0 0 0 3px rgba(var(--primary-rgb),0.35)';
    setTimeout(() => { box.style.boxShadow = ''; }, 3000);
    const pass = box.querySelector('.wlan-pass');
    if (pass && !pass.disabled) pass.focus();
})();

// Muat ulang antrian tugas
document.getElementById('tasksRefreshBtn')?.addEventListener('click', () => location.reload());
// Muat ulang daftar perangkat terhubung
document.getElementById('clientsRefreshBtn')?.addEventListener('click', () => location.reload());
// Muat ulang nilai ACS/TR-069
document.getElementById('tr069RefreshBtn')?.addEventListener('click', () => location.reload());
// Tambah tag device
document.getElementById('addTagBtn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const name = prompt('Nama tag baru untuk device ini:');
    if (name === null || !name.trim()) return;
    try {
        const res = await fetch('/noc/genieacs/' + encodeURIComponent(deviceId) + '/tags', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ name: name.trim() }),
        });
        const data = await res.json().catch(() => ({}));
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Gagal menambahkan tag.');
        }
    } catch (err) {
        alert('Gagal terhubung ke server.');
    }
});

// Pertahankan tab aktif saat halaman di-refresh (mis. setelah Apply WLAN)
(function rememberActiveTab() {
    const tabs = document.querySelectorAll('#detailTabs .nav-link');
    if (!tabs.length) return;
    const saved = sessionStorage.getItem('acs_active_tab');
    if (saved) {
        const target = Array.from(tabs).find((t) => t.id === saved || t.dataset.bsTarget === saved);
        if (target) {
            const pane = document.querySelector(target.dataset.bsTarget);
            tabs.forEach((t) => t.classList.remove('active'));
            target.classList.add('active');
            target.setAttribute('aria-selected', 'true');
            if (pane) {
                document.querySelectorAll('.tab-pane.active.show').forEach((p) => p.classList.remove('active', 'show'));
                pane.classList.add('active', 'show');
            }
        }
    }
    tabs.forEach((t) => {
        t.addEventListener('shown.bs.tab', () => {
            sessionStorage.setItem('acs_active_tab', t.id);
        });
    });
})();
</script>
@endpush
