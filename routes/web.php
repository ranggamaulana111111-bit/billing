<?php

use App\Http\Controllers\Api\OdpruteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HotspotCustomerController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\MikrotikRouterController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\Noc\AutomationController;
use App\Http\Controllers\Noc\ConfigModuleController;
use App\Http\Controllers\Noc\ConfigRepositoryController;
use App\Http\Controllers\Noc\DashboardController as NocDashboardController;
use App\Http\Controllers\Noc\FeaturesController;
use App\Http\Controllers\Noc\GenieacsController;
use App\Http\Controllers\Noc\InterfaceCenterController;
use App\Http\Controllers\Noc\InternetServiceController;
use App\Http\Controllers\Noc\MikrotikDashboardController;
use App\Http\Controllers\Noc\MikrotikDeviceController;
use App\Http\Controllers\Noc\NetworkConfigController;
use App\Http\Controllers\Noc\SecurityPolicyController;
use App\Http\Controllers\Noc\SyncDashboardController;
use App\Http\Controllers\Noc\TrafficEngineeringController;
use App\Http\Controllers\NocController;
use App\Http\Controllers\OdcController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\OnuHealthController;
use App\Http\Controllers\OnuHotspotController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicVoucherController;
use App\Http\Controllers\QosHealthController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TeknisiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\VoucherPrintTemplateController;
use App\Http\Controllers\VoucherProfileController;
use App\Http\Controllers\VoucherReportController;
use App\Http\Controllers\VoucherTemplateController;
use App\Http\Controllers\XenditController;
use App\Models\Package;
use App\Models\Setting;
use App\Models\VoucherTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── HOTSPOT STATIC PAGES ──
Route::match(['GET', 'POST'], '/hotspot/templates/{template}/{path?}', function (VoucherTemplate $template, string $path, Request $request) {
    if ($request->isMethod('POST')) {
        $loginUrl = url('hotspot/templates/'.$template->id.'/login.html');

        $dst = $request->input('dst', $loginUrl);

        return redirect($dst.(str_contains($dst, '?') ? '&' : '?').'error=login-failed');
    }

    $filePath = $template->templatePath().DIRECTORY_SEPARATOR.ltrim($path, '/\\');

    if (str_contains($path, '..') || ! file_exists($filePath) || is_dir($filePath)) {
        abort(404);
    }

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';

    return response()->file($filePath, ['Content-Type' => $mime]);
})->where('path', '.*');

Route::get('/hotspot/{page}', function (string $page) {
    $path = public_path("hotspot/$page");
    if (! str_contains($page, '..') && file_exists($path)) {
        return response()->file($path);
    }
    abort(404);
})->where('page', '.*');

// ── SITEMAP ──
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// ── AUTH ──
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');
Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('auth.callback');

Route::get('/', function () {
    $packages = Package::where('is_active', true)->orderBy('price')->get();

    $company = [
        'name' => Setting::get('company_name', 'ALKONEKbill'),
        'address' => Setting::get('company_address', ''),
        'phone' => Setting::get('company_phone', ''),
        'email' => 'admin@alkonek.net',
    ];

    return view('welcome', compact('packages', 'company'));
});

// ── MIDTRANS (auth required for pay & finish, not for notification) ──
Route::post('/midtrans/notification', [MidtransController::class, 'notification'])->name('midtrans.notification');

// ── XENDIT (auth required for pay, not for notification & finish) ──
Route::post('/xendit/notification', [XenditController::class, 'notification'])->name('xendit.notification');
Route::get('/xendit/finish', [XenditController::class, 'finish'])->name('xendit.finish');

// ── PORTAL PELANGGAN (public) ──
Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
Route::post('/portal', [PortalController::class, 'lookup'])->name('portal.lookup');
Route::get('/portal/bayar/{invoice}', [PortalController::class, 'bayar'])->name('portal.bayar');
Route::get('/portal/bayar-xendit/{invoice}', [PortalController::class, 'bayarXendit'])->name('portal.bayar-xendit');
Route::get('/portal/finish', [PortalController::class, 'finish'])->name('portal.finish');

// ── VOUCHER PUBLIC ──
Route::get('/vouchers/public', [PublicVoucherController::class, 'index'])->name('vouchers.public.index');
Route::post('/vouchers/public/generate', [PublicVoucherController::class, 'generate'])->name('vouchers.public.generate');
Route::get('/vouchers/check', [PublicVoucherController::class, 'check'])->name('vouchers.public.check');
Route::post('/vouchers/check-status', [PublicVoucherController::class, 'checkStatus'])->name('vouchers.check-status');

// ── TEKNISI & ADMIN: all authenticated users ──
Route::middleware(['auth', 'teknisi'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/teknisi/dashboard', [TeknisiController::class, 'dashboard'])->name('dashboard.teknisi');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customer/create', [CustomerController::class, 'create'])->name('customer.create');
    Route::post('/customer', [CustomerController::class, 'store'])->name('customer.store');
    Route::get('/customer/tambah-existing', [CustomerController::class, 'createExisting'])->name('customer.create-existing');
    Route::post('/customer/store-existing', [CustomerController::class, 'storeExisting'])->name('customer.store-existing');
    Route::get('/customer/{customer}/edit', [CustomerController::class, 'edit'])->name('customer.edit');
    Route::put('/customer/{customer}', [CustomerController::class, 'update'])->name('customer.update');
    Route::delete('/customer/{customer}', [CustomerController::class, 'destroy'])->name('customer.destroy');
    Route::post('/customer/{customer}/suspend', [CustomerController::class, 'suspend'])->name('customer.suspend');
    Route::post('/customer/{customer}/activate', [CustomerController::class, 'activate'])->name('customer.activate');
    Route::get('/customers/activation', [CustomerController::class, 'activation'])->name('customers.activation');
    Route::get('/customers/suspended', [CustomerController::class, 'suspended'])->name('customers.suspended');
    Route::get('/customers/history', [CustomerController::class, 'history'])->name('customers.history');
    Route::post('/customer/{customer}/sync-onu', [CustomerController::class, 'syncSingleOnu'])->name('customer.sync-single-onu');
    Route::get('/customer/{customer}/print-thermal', [CustomerController::class, 'printThermal'])->name('customer.print-thermal');
    Route::get('/customer/{customer}/print-a4', [CustomerController::class, 'printA4'])->name('customer.print-a4');
    Route::get('/customer/{customer}/pdf', [CustomerController::class, 'downloadPdf'])->name('customer.pdf');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoice/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoice.edit');
    Route::put('/invoice/{invoice}', [InvoiceController::class, 'update'])->name('invoice.update');
    Route::delete('/invoice/{invoice}', [InvoiceController::class, 'destroy'])->name('invoice.destroy');
    Route::get('/invoice/paid/{invoice}', [InvoiceController::class, 'markPaid'])->name('invoice.paid');
    Route::get('/invoice/print/{invoice}', [InvoiceController::class, 'print'])->name('invoice.print');
    Route::get('/invoice/print-thermal/{invoice}', [InvoiceController::class, 'printThermal'])->name('invoice.print-thermal');
    Route::get('/invoice/email-reminder/{invoice}', [InvoiceController::class, 'sendEmailReminder'])->name('invoice.email-reminder');
    Route::get('/invoice/email-payment/{invoice}', [InvoiceController::class, 'sendEmailPayment'])->name('invoice.email-payment');

    Route::get('/payment/create/{invoice}', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payment/history/{invoice}', [PaymentController::class, 'history'])->name('payment.history');
    Route::delete('/payment/{payment}', [PaymentController::class, 'destroy'])->name('payment.destroy');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payment-gateway', [MidtransController::class, 'settings'])->name('payment-gateway.index');
    Route::post('/payment-gateway', [MidtransController::class, 'updateSettings'])->name('payment-gateway.update');

    Route::get('/vouchers/report', [VoucherReportController::class, 'index'])->name('vouchers.report');

    // ── MIKROTIK PAGES (hidden per request) ──
    // Route::get('/mikrotik', fn () => redirect()->route('noc.mikrotik.dashboard'))->name('mikrotik.dashboard');
    // Route::get('/mikrotik/profiles', [MikrotikController::class, 'profiles'])->name('mikrotik.profiles');
    // Route::post('/mikrotik/profiles/sync', [MikrotikController::class, 'syncProfiles'])->name('mikrotik.profiles.sync');
    // Route::get('/mikrotik/active', [MikrotikController::class, 'activeSessions'])->name('mikrotik.active');
    // Route::post('/mikrotik/active/disconnect/{sessionId}', [MikrotikController::class, 'disconnectHotspot'])->name('mikrotik.active.disconnect');
    // Route::post('/mikrotik/active/ppp-disconnect/{sessionId}', [MikrotikController::class, 'disconnectPpp'])->name('mikrotik.active.ppp-disconnect');
    // Route::get('/mikrotik/ppp', [MikrotikController::class, 'pppSecrets'])->name('mikrotik.ppp');
    // Route::get('/mikrotik/queues', [MikrotikController::class, 'queues'])->name('mikrotik.queues');
    // Route::post('/mikrotik/queues/sync', [MikrotikController::class, 'syncQueue'])->name('mikrotik.queues.sync');
    // Route::get('/mikrotik/ppp-profiles', [MikrotikController::class, 'pppProfiles'])->name('mikrotik.ppp-profiles');
    // Route::post('/mikrotik/ppp-profiles/sync', [MikrotikController::class, 'syncPppProfiles'])->name('mikrotik.ppp-profiles.sync');
    // Route::get('/mikrotik/hotspot-users', [MikrotikController::class, 'hotspotUsers'])->name('mikrotik.hotspot-users');
    // Route::post('/mikrotik/hotspot-users/sync', [MikrotikController::class, 'syncHotspotUsers'])->name('mikrotik.hotspot-users.sync');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    // ── INCIDENTS / GANGGUAN ──
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/settings', [IncidentController::class, 'settings'])->name('incidents.settings');
    Route::post('/incidents/settings', [IncidentController::class, 'updateSettings'])->name('incidents.settings.update');
    Route::post('/incidents/purge', [IncidentController::class, 'purge'])->name('incidents.purge');
    Route::get('/incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::put('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::post('/incidents/{incident}/investigating', [IncidentController::class, 'investigating'])->name('incidents.investigating');
    Route::post('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
    Route::post('/incidents/{incident}/close', [IncidentController::class, 'close'])->name('incidents.close');

    // ── DISTRIBUTION (hidden per request, OLT menu) ──
    // Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');

    // ── OLT (hidden per request) ──
    // Route::get('/olts', [OltController::class, 'index'])->name('olt.index');
    // Route::get('/olts/create', [OltController::class, 'create'])->name('olt.create');
    // Route::post('/olts', [OltController::class, 'store'])->name('olt.store');
    // Route::get('/olts/map', [OltController::class, 'map'])->name('olt.map');
    // Route::get('/olts-monitoring', [OltController::class, 'monitoring'])->name('olt.monitoring');
    // Route::get('/olts/export', [OltController::class, 'exportOlt'])->name('olt.export');
    // Route::get('/olts/{olt}', [OltController::class, 'show'])->name('olt.show');
    // Route::get('/olts/{olt}/edit', [OltController::class, 'edit'])->name('olt.edit');
    // Route::put('/olts/{olt}', [OltController::class, 'update'])->name('olt.update');
    // Route::delete('/olts/{olt}', [OltController::class, 'destroy'])->name('olt.destroy');
    // Route::post('/olts/{olt}/test', [OltController::class, 'testConnection'])->name('olt.test');
    // Route::post('/olts/{olt}/scan', [OltController::class, 'scanOnus'])->name('olt.scan');
    // Route::post('/olts/{olt}/onu/{onu}/reboot', [OltController::class, 'rebootOnu'])->name('olt.onu.reboot');
    // Route::delete('/olts/{olt}/onu/{onu}', [OltController::class, 'removeOnu'])->name('olt.onu.remove');
    // Route::post('/olts/{olt}/ports', [OltController::class, 'syncPorts'])->name('olt.ports.sync');
    // Route::post('/onu/{onu}/link-customer', [OltController::class, 'linkCustomer'])->name('olt.onu.link');
    // Route::post('/onu/{onu}/unlink-customer', [OltController::class, 'unlinkCustomer'])->name('olt.onu.unlink');
    // Route::post('/olts/{olt}/sync-mikrotik', [OltController::class, 'syncFromMikrotik'])->name('olt.sync-mikrotik');
    // Route::get('/olts/{olt}/live', [OltController::class, 'liveData'])->name('olt.live');
    // Route::get('/onus/export', [OltController::class, 'exportOnu'])->name('onu.export');
    // Route::get('/onus/search', [OltController::class, 'searchOnu'])->name('onu.search');
    Route::get('/onus/available', [OltController::class, 'availableOnus'])->name('onu.available');
    Route::get('/pppoe/available', [CustomerController::class, 'pppoeAvailable'])->name('pppoe.available');

    // ── ONU HEALTH MONITORING (hidden per request, OLT menu) ──
    // Route::prefix('onu-health')->name('onu-health.')->group(function () {
    //     Route::get('/', [OnuHealthController::class, 'dashboard'])->name('dashboard');
    //     Route::get('/topology/graph', [OnuHealthController::class, 'topology'])->name('topology');
    //     Route::get('/ping/monitor', [OnuHealthController::class, 'pingMonitor'])->name('ping');
    //     Route::post('/ping/execute', [OnuHealthController::class, 'pingExecute'])->name('ping.execute');
    //     Route::get('/speedtest', [OnuHealthController::class, 'speedTest'])->name('speedtest');
    //     Route::get('/api/live', [OnuHealthController::class, 'liveDashboardData'])->name('live');
    //     Route::get('/{onu}', [OnuHealthController::class, 'detail'])->name('detail');
    //     Route::get('/{onu}/diagnosis', [OnuHealthController::class, 'diagnosis'])->name('diagnosis');
    //     Route::post('/{onu}/snapshot', [OnuHealthController::class, 'recordSnapshot'])->name('snapshot');
    // });

    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/{voucher}/print', [VoucherController::class, 'print'])->name('vouchers.print');
    Route::match(['get', 'post'], '/vouchers/print-batch', [VoucherController::class, 'printBatch'])->name('vouchers.print-batch');
    Route::post('/vouchers/{voucher}/used', [VoucherController::class, 'markUsed'])->name('vouchers.used');

    // ── ONU HOTSPOT ──
    Route::get('/onu-hotspot', [OnuHotspotController::class, 'index'])->name('onu-hotspot.index');
    Route::get('/onu-hotspot/{onu}', [OnuHotspotController::class, 'show'])->name('onu-hotspot.show');
    Route::put('/onu-hotspot/{onu}', [OnuHotspotController::class, 'update'])->name('onu-hotspot.update');
    Route::post('/onu-hotspot/{onu}/unlink', [OnuHotspotController::class, 'unlink'])->name('onu-hotspot.unlink');
    Route::post('/onu-hotspot/{onu}/link-customer', [OnuHotspotController::class, 'linkCustomer'])->name('onu-hotspot.link-customer');
    Route::post('/onu-hotspot/sync', [OnuHotspotController::class, 'syncFromOlt'])->name('onu-hotspot.sync');

    // ── PELANGGAN HOTSPOT ──
    Route::get('/hotspot-customers', [HotspotCustomerController::class, 'index'])->name('hotspot-customers.index');
    Route::post('/hotspot-customers/scan/{olt}', [HotspotCustomerController::class, 'scan'])->name('hotspot-customers.scan');
    Route::get('/hotspot-customers/create', [HotspotCustomerController::class, 'create'])->name('hotspot-customers.create');
    Route::post('/hotspot-customers', [HotspotCustomerController::class, 'store'])->name('hotspot-customers.store');

    // ── MONITORING ──
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/live', [MonitoringController::class, 'liveData'])->name('monitoring.live');

    Route::get('/api/odp-routes', [OdpruteController::class, 'routes']);
    Route::get('/api/odp-points', [OdpruteController::class, 'points']);
    Route::get('/api/customers/search', [CustomerController::class, 'searchApi'])->name('api.customers.search');

    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');

    Route::get('/invoice/pdf/{invoice}', [InvoiceController::class, 'downloadPdf'])->name('invoice.pdf');

    Route::get('/midtrans/pay/{invoice}', [MidtransController::class, 'pay'])->name('midtrans.pay');
    Route::get('/midtrans/finish', [MidtransController::class, 'finish'])->name('midtrans.finish');

    Route::get('/xendit/pay/{invoice}', [XenditController::class, 'pay'])->name('xendit.pay');
    Route::get('/xendit/gateway', [XenditController::class, 'settings'])->name('xendit.gateway');
    Route::post('/xendit/gateway', [XenditController::class, 'updateSettings'])->name('xendit.gateway.update');

    // ── NOC CONTROL CENTER ──
    // Route::get('/noc/traffic-analyzer', [NocController::class, 'trafficAnalyzer'])->name('noc.traffic-analyzer');

    // ── NOC DASHBOARD ──
    Route::middleware(['auth', 'teknisi'])->group(function () {
        Route::get('/noc/dashboard', [NocDashboardController::class, 'index'])->name('noc.dashboard');
        Route::get('/noc/features/map', [FeaturesController::class, 'map'])->name('noc.features.map');
        Route::get('/noc/features/map/search', [FeaturesController::class, 'search'])->name('noc.features.map.search');
        Route::get('/noc/features/map/mikrotik', [FeaturesController::class, 'mikrotikList'])->name('noc.features.map.mikrotik');
        Route::get('/noc/features/map/mikrotik/wan-traffic', [FeaturesController::class, 'mikrotikWanTraffic'])->name('noc.features.map.mikrotik.wan-traffic');
        Route::get('/noc/features/map/hotspot-tower-traffic', [FeaturesController::class, 'hotspotTowerTraffic'])->name('noc.features.map.hotspot-tower-traffic');
        Route::post('/noc/features/map/mikrotik/save', [FeaturesController::class, 'mikrotikSave'])->name('noc.features.map.mikrotik.save');
        Route::post('/noc/features/map/mikrotik/connect', [FeaturesController::class, 'mikrotikConnect'])->name('noc.features.map.mikrotik.connect');
        Route::post('/noc/features/map/mikrotik/sync-all', [FeaturesController::class, 'mikrotikSyncAll'])->name('noc.features.map.mikrotik.sync-all');
        Route::get('/noc/features/map/mikrotik/pppoe', [FeaturesController::class, 'mikrotikPppoe'])->name('noc.features.map.mikrotik.pppoe');
        Route::get('/noc/features/map/mikrotik/pppoe-session', [FeaturesController::class, 'pppoeSession'])->name('noc.features.map.mikrotik.pppoe-session');
        Route::get('/noc/features/map/hotspot', [FeaturesController::class, 'hotspotList'])->name('noc.features.map.hotspot');
        Route::post('/noc/features/map/mikrotik/delete', [FeaturesController::class, 'mikrotikDelete'])->name('noc.features.map.mikrotik.delete');
        Route::get('/noc/features/map/olt', [FeaturesController::class, 'oltList'])->name('noc.features.map.olt');
        Route::get('/noc/features/map/olt/pon-traffic', [FeaturesController::class, 'oltPonTraffic'])->name('noc.features.map.olt.pon-traffic');
        Route::get('/noc/features/map/olt-live/{id}', [FeaturesController::class, 'oltLive'])->name('noc.features.map.olt-live');
        Route::post('/noc/features/map/olt/save', [FeaturesController::class, 'oltSave'])->name('noc.features.map.olt.save');
        Route::post('/noc/features/map/olt/connect', [FeaturesController::class, 'oltConnect'])->name('noc.features.map.olt.connect');
        Route::post('/noc/features/map/olt/sync-all', [FeaturesController::class, 'oltSyncAll'])->name('noc.features.map.olt.sync-all');
        Route::post('/noc/features/map/olt/delete', [FeaturesController::class, 'oltDelete'])->name('noc.features.map.olt.delete');
        Route::get('/noc/features/map/genieacs', [FeaturesController::class, 'genieacsConfig'])->name('noc.features.map.genieacs');
        Route::post('/noc/features/map/genieacs/save', [FeaturesController::class, 'genieacsSave'])->name('noc.features.map.genieacs.save');
        Route::post('/noc/features/map/genieacs/sync', [FeaturesController::class, 'genieacsSync'])->name('noc.features.map.genieacs.sync');

        Route::get('/noc/features/map/notif/config', [FeaturesController::class, 'notifConfig'])->name('noc.features.map.notif.config');
        Route::post('/noc/features/map/notif/save', [FeaturesController::class, 'notifSave'])->name('noc.features.map.notif.save');

        Route::get('/noc/features/map/users', [FeaturesController::class, 'usersConfig'])->name('noc.features.map.users');
        Route::post('/noc/features/map/users/save', [FeaturesController::class, 'usersSave'])->name('noc.features.map.users.save');
        Route::post('/noc/features/map/users/delete', [FeaturesController::class, 'usersDelete'])->name('noc.features.map.users.delete');

        Route::get('/noc/features/map/backup/config', [FeaturesController::class, 'backupConfig'])->name('noc.features.map.backup.config');

        Route::post('/noc/features/map/backup/save', [FeaturesController::class, 'backupSave'])->name('noc.features.map.backup.save');

        Route::post('/noc/features/map/backup/send', [FeaturesController::class, 'backupSendNow'])->name('noc.features.map.backup.send');

        Route::post('/noc/features/map/backup/restore', [FeaturesController::class, 'backupRestore'])->name('noc.features.map.backup.restore');

        Route::get('/noc/features/map/backup/excel-export', [FeaturesController::class, 'excelExport'])->name('noc.features.map.backup.excel-export');

        Route::post('/noc/features/map/backup/excel-import', [FeaturesController::class, 'excelImport'])->name('noc.features.map.backup.excel-import');

        Route::get('/noc/features/map/backup/kmz-export', [FeaturesController::class, 'kmzExport'])->name('noc.features.map.backup.kmz-export');

        Route::post('/noc/features/map/backup/kmz-import', [FeaturesController::class, 'kmzImport'])->name('noc.features.map.backup.kmz-import');

        Route::get('/noc/features/map/markers', [FeaturesController::class, 'mapMarkers'])->name('noc.features.map.markers');

        Route::get('/noc/features/map/device', [FeaturesController::class, 'deviceList'])->name('noc.features.map.device');
        Route::get('/noc/features/map/odc-stats/{id}', [FeaturesController::class, 'odcStats'])->name('noc.features.map.odc-stats');
        Route::get('/noc/features/map/odp-stats/{id}', [FeaturesController::class, 'odpStats'])->name('noc.features.map.odp-stats');

        Route::get('/noc/features/map/device/parents', [FeaturesController::class, 'deviceParents'])->name('noc.features.map.device.parents');

        Route::post('/noc/features/map/device/save', [FeaturesController::class, 'deviceSave'])->name('noc.features.map.device.save');

        Route::post('/noc/features/map/device/cable', [FeaturesController::class, 'deviceCableSave'])->name('noc.features.map.device.cable');

        Route::post('/noc/features/map/device/status', [FeaturesController::class, 'deviceStatus'])->name('noc.features.map.device.status');

        Route::post('/noc/features/map/device/delete', [FeaturesController::class, 'deviceDelete'])->name('noc.features.map.device.delete');
        Route::post('/noc/features/map/device/delete-all', [FeaturesController::class, 'deviceDeleteAll'])->name('noc.features.map.device.delete-all');
        Route::post('/noc/features/map/customer/delete', [FeaturesController::class, 'customerDelete'])->name('noc.features.map.customer.delete');
        Route::post('/noc/features/map/customer/delete-all', [FeaturesController::class, 'customerDeleteAll'])->name('noc.features.map.customer.delete-all');

        Route::get('/noc/features/map/customer/detail', [FeaturesController::class, 'customerDetail'])->name('noc.features.map.customer.detail');

        Route::post('/noc/features/map/customer/ping', [FeaturesController::class, 'customerPing'])->name('noc.features.map.customer.ping');

        Route::post('/noc/features/map/customer/acs', [FeaturesController::class, 'customerAcs'])->name('noc.features.map.customer.acs');
        Route::post('/noc/features/map/customer/acs/set', [FeaturesController::class, 'customerAcsSet'])->name('noc.features.map.customer.acs.set');

        Route::post('/noc/features/map/customer/duplicate', [FeaturesController::class, 'customerDuplicate'])->name('noc.features.map.customer.duplicate');

        Route::post('/noc/features/map/onu/reboot', [FeaturesController::class, 'onuReboot'])->name('noc.features.map.onu.reboot');

        Route::get('/noc/features/map/onu-table', [FeaturesController::class, 'onuTable'])->name('noc.features.map.onu-table');
        Route::get('/noc/features/map/onu-table/print', [FeaturesController::class, 'onuTablePrint'])->name('noc.features.map.onu-table.print');
        Route::get('/noc/features/map/onu-table/export', [FeaturesController::class, 'onuTableExport'])->name('noc.features.map.onu-table.export');
    });

    // ── GENIEACS (hidden per request, MikroTik-related) ──
    // Route::get('/noc/genieacs', [GenieacsController::class, 'dashboard'])->name('noc.genieacs');
    // Route::get('/noc/genieacs/devices', [GenieacsController::class, 'devices'])->name('noc.genieacs.devices');
    // Route::get('/noc/genieacs/devices/{deviceId}', [GenieacsController::class, 'deviceDetail'])->name('noc.genieacs.device-detail');
    // Route::get('/noc/genieacs/presets', [GenieacsController::class, 'presets'])->name('noc.genieacs.presets');
    // Route::get('/noc/genieacs/faults', [GenieacsController::class, 'faults'])->name('noc.genieacs.faults');
    // Route::get('/noc/genieacs/settings', [GenieacsController::class, 'settings'])->name('noc.genieacs.settings');
    // Route::post('/noc/genieacs/test-connection', [GenieacsController::class, 'testConnection'])->name('noc.genieacs.test-connection');
    // Route::post('/noc/genieacs/{deviceId}/reboot', [GenieacsController::class, 'reboot'])->name('noc.genieacs.reboot');
    // Route::post('/noc/genieacs/{deviceId}/factory-reset', [GenieacsController::class, 'factoryReset'])->name('noc.genieacs.factory-reset');
    // Route::post('/noc/genieacs/{deviceId}/refresh', [GenieacsController::class, 'refreshObject'])->name('noc.genieacs.refresh');
    Route::get('/noc/linux-server', [NocController::class, 'linuxServer'])->name('noc.linux-server');
    Route::get('/noc/dns', [NocController::class, 'dns'])->name('noc.dns');
    Route::get('/noc/vpn', [NocController::class, 'vpn'])->name('noc.vpn');
    Route::get('/noc/speedtest', [NocController::class, 'speedtest'])->name('noc.speedtest');
    Route::get('/noc/automation', [NocController::class, 'automation'])->name('noc.automation');
    Route::get('/noc/configuration', [NocController::class, 'configuration'])->name('noc.configuration');
    Route::get('/noc/scripts', [NocController::class, 'scripts'])->name('noc.scripts');
    Route::get('/noc/mass-deployment', [NocController::class, 'massDeployment'])->name('noc.mass-deployment');
    Route::get('/noc/ai-assistant', [NocController::class, 'aiAssistant'])->name('noc.ai-assistant');
    Route::get('/noc/capacity-planning', [NocController::class, 'capacityPlanning'])->name('noc.capacity-planning');
    Route::get('/noc/audit', [NocController::class, 'audit'])->name('noc.audit');
    Route::get('/noc/knowledge-base', [NocController::class, 'knowledgeBase'])->name('noc.knowledge-base');
    Route::get('/noc/settings', [NocController::class, 'nocSettings'])->name('noc.settings');
    // Route::get('/noc/pon-manager', [NocController::class, 'ponManager'])->name('noc.pon-manager');

    // ── MIKROTIK DASHBOARD (NOC) — hidden per request ──
    // Route::get('/noc/mikrotik', [MikrotikDashboardController::class, 'index'])->name('noc.mikrotik.dashboard');
    // Route::get('/noc/mikrotik/{mikrotikDevice}', [MikrotikDashboardController::class, 'detail'])->name('noc.mikrotik.detail');
    // Route::get('/api/noc/mikrotik/live', [MikrotikDashboardController::class, 'liveApi'])->name('noc.mikrotik.live-api');
    // Route::get('/api/noc/mikrotik/{mikrotikDevice}/live', [MikrotikDashboardController::class, 'liveDetailApi'])->name('noc.mikrotik.live-detail-api');

    // ── MIKROTIK DEVICE MANAGER (NOC) — hidden per request ──
    // Route::prefix('noc/mikrotik-devices')->name('noc.mikrotik-devices.')->group(function () {
    //     Route::get('/', [MikrotikDeviceController::class, 'index'])->name('index');
    //     Route::get('/create', [MikrotikDeviceController::class, 'create'])->name('create');
    //     Route::post('/', [MikrotikDeviceController::class, 'store'])->name('store');
    //     Route::get('/{mikrotikDevice}', [MikrotikDeviceController::class, 'show'])->name('show');
    //     Route::get('/{mikrotikDevice}/edit', [MikrotikDeviceController::class, 'edit'])->name('edit');
    //     Route::put('/{mikrotikDevice}', [MikrotikDeviceController::class, 'update'])->name('update');
    //     Route::delete('/{mikrotikDevice}', [MikrotikDeviceController::class, 'destroy'])->name('destroy');
    //     Route::post('/{mikrotikDevice}/test-connection', [MikrotikDeviceController::class, 'testConnection'])->name('test-connection');
    //     Route::post('/{mikrotikDevice}/toggle-status', [MikrotikDeviceController::class, 'toggleStatus'])->name('toggle-status');
    // });

    // ── INTERFACE CENTER (NOC) — hidden per request ──
    // Route::get('/noc/interface-center', [InterfaceCenterController::class, 'dashboard'])->name('noc.interface-center.dashboard');
    // Route::get('/noc/interface-center/all', [InterfaceCenterController::class, 'index'])->name('noc.interface-center.index');
    // Route::get('/noc/interface-center/{routerId}/{interfaceName}', [InterfaceCenterController::class, 'detail'])->name('noc.interface-center.detail');
    // Route::put('/noc/interface-center/{routerId}/{interfaceName}', [InterfaceCenterController::class, 'update'])->name('noc.interface-center.update');
    // Route::put('/noc/interface-center/{routerId}/{interfaceName}/metadata', [InterfaceCenterController::class, 'updateMetadata'])->name('noc.interface-center.update-metadata');
    // Route::post('/noc/interface-center/bulk', [InterfaceCenterController::class, 'bulk'])->name('noc.interface-center.bulk');
    // Route::get('/noc/interface-center/{routerId}/{interfaceName}/history', [InterfaceCenterController::class, 'history'])->name('noc.interface-center.history');
    // Route::get('/api/noc/interface-center/live', [InterfaceCenterController::class, 'liveApi'])->name('noc.interface-center.live-api');
    // Route::get('/api/noc/interface-center/{routerId}/live/{interfaceName}', [InterfaceCenterController::class, 'liveDetailApi'])->name('noc.interface-center.live-detail-api');

    // ── CONFIG SYNC DASHBOARD (NOC) — hidden per request ──
    // Route::prefix('noc/sync')->name('noc.sync.')->group(function () {
    //     Route::get('/', [SyncDashboardController::class, 'dashboard'])->name('dashboard');
    //     Route::get('/logs', [SyncDashboardController::class, 'logs'])->name('logs');
    //     Route::get('/configs', [SyncDashboardController::class, 'configs'])->name('configs');
    //     Route::post('/sync-now', [SyncDashboardController::class, 'syncNow'])->name('sync-now');
    //     Route::post('/sync-all', [SyncDashboardController::class, 'syncAll'])->name('sync-all');
    // });
    // Route::get('/api/noc/sync/live', [SyncDashboardController::class, 'liveApi'])->name('noc.sync.live-api');

    // ── CONFIGURATION CENTER (NOC) — hidden per request ──
    // Route::get('/noc/config-center', [ConfigModuleController::class, 'modules'])->name('noc.config.modules');
    // Route::prefix('noc/config-center/{module}')->name('noc.config.')->group(function () {
    //     Route::get('/', [ConfigModuleController::class, 'index'])->name('module');
    //     Route::get('/detail', [ConfigModuleController::class, 'detail'])->name('detail');
    //     Route::get('/history', [ConfigModuleController::class, 'history'])->name('history');
    //     Route::get('/create', [ConfigModuleController::class, 'create'])->name('create');
    //     Route::post('/store', [ConfigModuleController::class, 'store'])->name('store');
    //     Route::get('/edit', [ConfigModuleController::class, 'edit'])->name('edit');
    //     Route::post('/update', [ConfigModuleController::class, 'update'])->name('update');
    //     Route::post('/destroy', [ConfigModuleController::class, 'destroy'])->name('destroy');
    //     Route::post('/sync', [ConfigModuleController::class, 'syncModule'])->name('sync-module');
    //     Route::post('/sync-all', [ConfigModuleController::class, 'syncAll'])->name('sync-all');
    // });
    // Route::get('/api/noc/config-center/{module}/live', [ConfigModuleController::class, 'liveApi'])->name('noc.config.live-api');

    // ── CONFIGURATION REPOSITORY (NOC) — disabled (MikroTik hidden) ──
    // Route::prefix('noc/config-repository')->name('noc.repo.')->group(function () {
    //     Route::get('/', [ConfigRepositoryController::class, 'index'])->name('index');
    //     Route::get('/changes', [ConfigRepositoryController::class, 'changes'])->name('changes');
    //     Route::get('/compare', [ConfigRepositoryController::class, 'compare'])->name('compare');
    //     Route::get('/{id}', [ConfigRepositoryController::class, 'show'])->name('show');
    //     Route::get('/{routerId}/{module}/{itemId}/history', [ConfigRepositoryController::class, 'itemHistory'])->name('item-history');
    // });

    // ── AUTOMATION ENGINE (NOC) ──
    Route::prefix('noc/automation')->name('noc.automation.')->group(function () {
        Route::get('/', [AutomationController::class, 'index'])->name('index');
        Route::get('/jobs', [AutomationController::class, 'jobs'])->name('jobs');
        Route::get('/jobs/create', [AutomationController::class, 'create'])->name('create');
        Route::post('/jobs', [AutomationController::class, 'store'])->name('store');
        Route::get('/jobs/{id}', [AutomationController::class, 'show'])->name('show');
        Route::get('/jobs/{id}/edit', [AutomationController::class, 'edit'])->name('edit');
        Route::put('/jobs/{id}', [AutomationController::class, 'update'])->name('update');
        Route::delete('/jobs/{id}', [AutomationController::class, 'destroy'])->name('destroy');
        Route::post('/jobs/{id}/dispatch', [AutomationController::class, 'dispatch'])->name('dispatch');
        Route::post('/jobs/{id}/cancel', [AutomationController::class, 'cancel'])->name('cancel');
        Route::post('/jobs/{id}/retry', [AutomationController::class, 'retry'])->name('retry');
        Route::post('/jobs/{id}/reset', [AutomationController::class, 'reset'])->name('reset');
        Route::get('/logs', [AutomationController::class, 'logs'])->name('logs');
        Route::post('/trigger-scheduler', [AutomationController::class, 'triggerScheduler'])->name('trigger-scheduler');
        Route::post('/trigger-worker', [AutomationController::class, 'triggerWorker'])->name('trigger-worker');
    });

    // ── NETWORK CONFIGURATION CENTER (NOC) ──
    Route::prefix('noc/netconfig')->name('noc.netconfig.')->group(function () {
        Route::get('/', [NetworkConfigController::class, 'dashboard'])->name('dashboard');
        Route::get('/audit', [NetworkConfigController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/{resource}', [NetworkConfigController::class, 'index'])->name('index');
        Route::post('/{resource}', [NetworkConfigController::class, 'store'])->name('store');
        Route::put('/{resource}/{itemId}', [NetworkConfigController::class, 'update'])->name('update');
        Route::delete('/{resource}/{itemId}', [NetworkConfigController::class, 'destroy'])->name('destroy');
        Route::post('/{resource}/{itemId}/toggle', [NetworkConfigController::class, 'toggle'])->name('toggle');
        Route::post('/{resource}/bulk', [NetworkConfigController::class, 'bulk'])->name('bulk');
        Route::post('/{resource}/sync', [NetworkConfigController::class, 'sync'])->name('sync');
    });

    // ── INTERNET SERVICE CENTER (NOC) — hidden per request (MikroTik Center / PPPoE) ──
    // Route::prefix('noc/internet')->name('noc.internet.')->group(function () {
    //     Route::get('/', [InternetServiceController::class, 'dashboard'])->name('dashboard');
    //     Route::get('/audit', [InternetServiceController::class, 'auditLogs'])->name('audit');
    //     Route::get('/radius', [InternetServiceController::class, 'radius'])->name('radius');
    //     Route::get('/active', [InternetServiceController::class, 'activeSessions'])->name('active');
    //     Route::post('/disconnect/{type}/{sessionId}', [InternetServiceController::class, 'disconnectSession'])->name('disconnect-session');

    //     // IP Pool
    //     Route::get('/ippool', [InternetServiceController::class, 'ipPools'])->name('ippool');
    //     Route::post('/ippool', [InternetServiceController::class, 'ipPoolStore'])->name('ippool-store');
    //     Route::put('/ippool/{itemId}', [InternetServiceController::class, 'ipPoolUpdate'])->name('ippool-update');
    //     Route::delete('/ippool/{itemId}', [InternetServiceController::class, 'ipPoolDestroy'])->name('ippool-destroy');
    //     Route::post('/ippool/{itemId}/toggle', [InternetServiceController::class, 'ipPoolToggle'])->name('ippool-toggle');
    //     Route::post('/ippool/bulk', [InternetServiceController::class, 'ipPoolBulk'])->name('ippool-bulk');

    //     // DHCP Server
    //     Route::get('/dhcp', [InternetServiceController::class, 'dhcpServers'])->name('dhcp');
    //     Route::post('/dhcp', [InternetServiceController::class, 'dhcpStore'])->name('dhcp-store');
    //     Route::put('/dhcp/{itemId}', [InternetServiceController::class, 'dhcpUpdate'])->name('dhcp-update');
    //     Route::delete('/dhcp/{itemId}', [InternetServiceController::class, 'dhcpDestroy'])->name('dhcp-destroy');
    //     Route::post('/dhcp/{itemId}/toggle', [InternetServiceController::class, 'dhcpToggle'])->name('dhcp-toggle');
    //     Route::post('/dhcp/bulk', [InternetServiceController::class, 'dhcpBulk'])->name('dhcp-bulk');

    //     // DHCP Lease
    //     Route::get('/dhcplease', [InternetServiceController::class, 'dhcpLeases'])->name('dhcplease');
    //     Route::post('/dhcplease', [InternetServiceController::class, 'dhcpLeaseStore'])->name('dhcplease-store');
    //     Route::put('/dhcplease/{itemId}', [InternetServiceController::class, 'dhcpLeaseUpdate'])->name('dhcplease-update');
    //     Route::delete('/dhcplease/{itemId}', [InternetServiceController::class, 'dhcpLeaseDestroy'])->name('dhcplease-destroy');
    //     Route::post('/dhcplease/{itemId}/make-static', [InternetServiceController::class, 'dhcpLeaseMakeStatic'])->name('dhcplease-make-static');
    //     Route::post('/dhcplease/bulk', [InternetServiceController::class, 'dhcpLeaseBulk'])->name('dhcplease-bulk');

    //     // PPP Profile
    //     Route::get('/pppprofile', [InternetServiceController::class, 'pppProfiles'])->name('pppprofile');
    //     Route::post('/pppprofile', [InternetServiceController::class, 'pppProfileStore'])->name('pppprofile-store');
    //     Route::put('/pppprofile/{itemId}', [InternetServiceController::class, 'pppProfileUpdate'])->name('pppprofile-update');
    //     Route::delete('/pppprofile/{itemId}', [InternetServiceController::class, 'pppProfileDestroy'])->name('pppprofile-destroy');
    //     Route::post('/pppprofile/bulk', [InternetServiceController::class, 'pppProfileBulk'])->name('pppprofile-bulk');

    //     // PPP Secret
    //     Route::get('/pppsecret', [InternetServiceController::class, 'pppSecrets'])->name('pppsecret');
    //     Route::post('/pppsecret', [InternetServiceController::class, 'pppSecretStore'])->name('pppsecret-store');
    //     Route::put('/pppsecret/{itemId}', [InternetServiceController::class, 'pppSecretUpdate'])->name('pppsecret-update');
    //     Route::delete('/pppsecret/{itemId}', [InternetServiceController::class, 'pppSecretDestroy'])->name('pppsecret-destroy');
    //     Route::post('/pppsecret/{itemId}/toggle', [InternetServiceController::class, 'pppSecretToggle'])->name('pppsecret-toggle');
    //     Route::post('/pppsecret/bulk', [InternetServiceController::class, 'pppSecretBulk'])->name('pppsecret-bulk');

    //     // Hotspot Server
    //     Route::get('/hotspot', [InternetServiceController::class, 'hotspotServers'])->name('hotspot');
    //     Route::post('/hotspot', [InternetServiceController::class, 'hotspotServerStore'])->name('hotspot-store');
    //     Route::put('/hotspot/{itemId}', [InternetServiceController::class, 'hotspotServerUpdate'])->name('hotspot-update');
    //     Route::delete('/hotspot/{itemId}', [InternetServiceController::class, 'hotspotServerDestroy'])->name('hotspot-destroy');
    //     Route::post('/hotspot/{itemId}/toggle', [InternetServiceController::class, 'hotspotServerToggle'])->name('hotspot-toggle');
    //     Route::post('/hotspot/bulk', [InternetServiceController::class, 'hotspotServerBulk'])->name('hotspot-bulk');

    //     // Hotspot User
    //     Route::get('/hotspotuser', [InternetServiceController::class, 'hotspotUsers'])->name('hotspotuser');
    //     Route::post('/hotspotuser', [InternetServiceController::class, 'hotspotUserStore'])->name('hotspotuser-store');
    //     Route::put('/hotspotuser/{itemId}', [InternetServiceController::class, 'hotspotUserUpdate'])->name('hotspotuser-update');
    //     Route::delete('/hotspotuser/{itemId}', [InternetServiceController::class, 'hotspotUserDestroy'])->name('hotspotuser-destroy');
    //     Route::post('/hotspotuser/{itemId}/toggle', [InternetServiceController::class, 'hotspotUserToggle'])->name('hotspotuser-toggle');
    //     Route::post('/hotspotuser/bulk', [InternetServiceController::class, 'hotspotUserBulk'])->name('hotspotuser-bulk');

    //     // Hotspot Profile
    //     Route::get('/hotspotprofile', [InternetServiceController::class, 'hotspotProfiles'])->name('hotspotprofile');
    //     Route::post('/hotspotprofile', [InternetServiceController::class, 'hotspotProfileStore'])->name('hotspotprofile-store');
    //     Route::put('/hotspotprofile/{itemId}', [InternetServiceController::class, 'hotspotProfileUpdate'])->name('hotspotprofile-update');
    //     Route::delete('/hotspotprofile/{itemId}', [InternetServiceController::class, 'hotspotProfileDestroy'])->name('hotspotprofile-destroy');

    //     // Hotspot Host
    //     Route::get('/host', [InternetServiceController::class, 'hotspotHosts'])->name('host');

    //     // Hotspot Cookie
    //     Route::get('/cookie', [InternetServiceController::class, 'hotspotCookies'])->name('cookie');

    //     // Hotspot Login History
    //     Route::get('/login-history', [InternetServiceController::class, 'hotspotLoginHistory'])->name('login-history');

    //     // Monitoring Center
    //     Route::get('/monitoring', [InternetServiceController::class, 'monitoring'])->name('monitoring');
    //     Route::get('/monitoring/interface-rates', [InternetServiceController::class, 'interfaceRates'])->name('monitoring.interface-rates');
    //     Route::get('/monitoring/router-status', [InternetServiceController::class, 'routerStatus'])->name('monitoring.router-status');
    //     Route::get('/monitoring/active-sessions', [InternetServiceController::class, 'activeSessionsLive'])->name('monitoring.active-sessions');

    //     // IP Conflicts
    //     Route::get('/conflicts', [InternetServiceController::class, 'ipConflicts'])->name('conflicts');

    //     // Bulk Comment (per resource)
    //     Route::post('/ippool/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('ippool-bulk-comment');
    //     Route::post('/dhcp/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('dhcp-bulk-comment');
    //     Route::post('/dhcplease/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('dhcplease-bulk-comment');
    //     Route::post('/pppprofile/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('pppprofile-bulk-comment');
    //     Route::post('/pppsecret/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('pppsecret-bulk-comment');
    //     Route::post('/hotspot/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('hotspot-bulk-comment');
    //     Route::post('/hotspotuser/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('hotspotuser-bulk-comment');
    //     Route::post('/hotspotprofile/bulk-comment', [InternetServiceController::class, 'bulkComment'])->name('hotspotprofile-bulk-comment');

    //     // Bulk Refresh (per resource)
    //     Route::post('/ippool/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('ippool-refresh');
    //     Route::post('/dhcp/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('dhcp-refresh');
    //     Route::post('/dhcplease/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('dhcplease-refresh');
    //     Route::post('/pppprofile/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('pppprofile-refresh');
    //     Route::post('/pppsecret/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('pppsecret-refresh');
    //     Route::post('/hotspot/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('hotspot-refresh');
    //     Route::post('/hotspotuser/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('hotspotuser-refresh');
    //     Route::post('/hotspotprofile/refresh', [InternetServiceController::class, 'bulkRefresh'])->name('hotspotprofile-refresh');
    // });

    // ── SECURITY POLICY CENTER ──
    Route::prefix('noc/security')->name('noc.security.')->group(function () {
        Route::get('/', [SecurityPolicyController::class, 'dashboard'])->name('dashboard');
        Route::get('/audit', [SecurityPolicyController::class, 'auditLogs'])->name('audit');

        // ── Firewall Filter ──
        Route::get('/firewall', [SecurityPolicyController::class, 'firewallFilter'])->name('firewall-filter');
        Route::post('/firewall', [SecurityPolicyController::class, 'firewallFilterStore'])->name('firewall-filter-store');
        Route::put('/firewall/{itemId}', [SecurityPolicyController::class, 'firewallFilterUpdate'])->name('firewall-filter-update');
        Route::delete('/firewall/{itemId}', [SecurityPolicyController::class, 'firewallFilterDestroy'])->name('firewall-filter-destroy');
        Route::post('/firewall/{itemId}/toggle', [SecurityPolicyController::class, 'firewallFilterToggle'])->name('firewall-filter-toggle');
        Route::post('/firewall/{itemId}/move', [SecurityPolicyController::class, 'firewallFilterMove'])->name('firewall-filter-move');
        Route::post('/firewall/{itemId}/copy', [SecurityPolicyController::class, 'firewallFilterCopy'])->name('firewall-filter-copy');
        Route::post('/firewall/bulk', [SecurityPolicyController::class, 'firewallFilterBulk'])->name('firewall-filter-bulk');

        // ── NAT ──
        Route::get('/nat', [SecurityPolicyController::class, 'nat'])->name('nat');
        Route::post('/nat', [SecurityPolicyController::class, 'natStore'])->name('nat-store');
        Route::put('/nat/{itemId}', [SecurityPolicyController::class, 'natUpdate'])->name('nat-update');
        Route::delete('/nat/{itemId}', [SecurityPolicyController::class, 'natDestroy'])->name('nat-destroy');
        Route::post('/nat/{itemId}/toggle', [SecurityPolicyController::class, 'natToggle'])->name('nat-toggle');
        Route::post('/nat/{itemId}/move', [SecurityPolicyController::class, 'natMove'])->name('nat-move');
        Route::post('/nat/{itemId}/copy', [SecurityPolicyController::class, 'natCopy'])->name('nat-copy');
        Route::post('/nat/bulk', [SecurityPolicyController::class, 'natBulk'])->name('nat-bulk');

        // ── Mangle ──
        Route::get('/mangle', [SecurityPolicyController::class, 'mangle'])->name('mangle');
        Route::post('/mangle', [SecurityPolicyController::class, 'mangleStore'])->name('mangle-store');
        Route::put('/mangle/{itemId}', [SecurityPolicyController::class, 'mangleUpdate'])->name('mangle-update');
        Route::delete('/mangle/{itemId}', [SecurityPolicyController::class, 'mangleDestroy'])->name('mangle-destroy');
        Route::post('/mangle/{itemId}/toggle', [SecurityPolicyController::class, 'mangleToggle'])->name('mangle-toggle');
        Route::post('/mangle/{itemId}/move', [SecurityPolicyController::class, 'mangleMove'])->name('mangle-move');
        Route::post('/mangle/{itemId}/copy', [SecurityPolicyController::class, 'mangleCopy'])->name('mangle-copy');
        Route::post('/mangle/bulk', [SecurityPolicyController::class, 'mangleBulk'])->name('mangle-bulk');

        // ── Address List ──
        Route::get('/address-list', [SecurityPolicyController::class, 'addressList'])->name('address-list');
        Route::post('/address-list', [SecurityPolicyController::class, 'addressListStore'])->name('address-list-store');
        Route::put('/address-list/{itemId}', [SecurityPolicyController::class, 'addressListUpdate'])->name('address-list-update');
        Route::delete('/address-list/{itemId}', [SecurityPolicyController::class, 'addressListDestroy'])->name('address-list-destroy');
        Route::post('/address-list/bulk', [SecurityPolicyController::class, 'addressListBulk'])->name('address-list-bulk');

        // ── Raw Firewall ──
        Route::get('/raw', [SecurityPolicyController::class, 'raw'])->name('raw');
        Route::post('/raw', [SecurityPolicyController::class, 'rawStore'])->name('raw-store');
        Route::put('/raw/{itemId}', [SecurityPolicyController::class, 'rawUpdate'])->name('raw-update');
        Route::delete('/raw/{itemId}', [SecurityPolicyController::class, 'rawDestroy'])->name('raw-destroy');
        Route::post('/raw/{itemId}/toggle', [SecurityPolicyController::class, 'rawToggle'])->name('raw-toggle');
        Route::post('/raw/{itemId}/move', [SecurityPolicyController::class, 'rawMove'])->name('raw-move');
        Route::post('/raw/{itemId}/copy', [SecurityPolicyController::class, 'rawCopy'])->name('raw-copy');
        Route::post('/raw/bulk', [SecurityPolicyController::class, 'rawBulk'])->name('raw-bulk');

        // ── Layer7 ──
        Route::get('/layer7', [SecurityPolicyController::class, 'layer7'])->name('layer7');
        Route::post('/layer7', [SecurityPolicyController::class, 'layer7Store'])->name('layer7-store');
        Route::put('/layer7/{itemId}', [SecurityPolicyController::class, 'layer7Update'])->name('layer7-update');
        Route::delete('/layer7/{itemId}', [SecurityPolicyController::class, 'layer7Destroy'])->name('layer7-destroy');
        Route::post('/layer7/bulk', [SecurityPolicyController::class, 'layer7Bulk'])->name('layer7-bulk');
    });

    // ── TRAFFIC ENGINEERING & QoS CENTER (NOC) ──
    Route::prefix('noc/traffic-eng')->name('noc.traffic_eng.')->group(function () {
        Route::get('/', [TrafficEngineeringController::class, 'dashboard'])->name('dashboard');
        Route::get('/audit', [TrafficEngineeringController::class, 'auditLogs'])->name('audit');

        // ── Simple Queue ──
        Route::get('/simple-queue', [TrafficEngineeringController::class, 'simpleQueues'])->name('simple-queue');
        Route::post('/simple-queue', [TrafficEngineeringController::class, 'simpleQueueStore'])->name('simple-queue-store');
        Route::put('/simple-queue/{itemId}', [TrafficEngineeringController::class, 'simpleQueueUpdate'])->name('simple-queue-update');
        Route::delete('/simple-queue/{itemId}', [TrafficEngineeringController::class, 'simpleQueueDestroy'])->name('simple-queue-destroy');
        Route::post('/simple-queue/{itemId}/toggle', [TrafficEngineeringController::class, 'simpleQueueToggle'])->name('simple-queue-toggle');
        Route::post('/simple-queue/{itemId}/move', [TrafficEngineeringController::class, 'simpleQueueMove'])->name('simple-queue-move');
        Route::post('/simple-queue/{itemId}/copy', [TrafficEngineeringController::class, 'simpleQueueCopy'])->name('simple-queue-copy');
        Route::post('/simple-queue/bulk', [TrafficEngineeringController::class, 'simpleQueueBulk'])->name('simple-queue-bulk');

        // ── Queue Tree ──
        Route::get('/queue-tree', [TrafficEngineeringController::class, 'queueTrees'])->name('queue-tree');
        Route::post('/queue-tree', [TrafficEngineeringController::class, 'queueTreeStore'])->name('queue-tree-store');
        Route::put('/queue-tree/{itemId}', [TrafficEngineeringController::class, 'queueTreeUpdate'])->name('queue-tree-update');
        Route::delete('/queue-tree/{itemId}', [TrafficEngineeringController::class, 'queueTreeDestroy'])->name('queue-tree-destroy');
        Route::post('/queue-tree/{itemId}/toggle', [TrafficEngineeringController::class, 'queueTreeToggle'])->name('queue-tree-toggle');
        Route::post('/queue-tree/bulk', [TrafficEngineeringController::class, 'queueTreeBulk'])->name('queue-tree-bulk');

        // ── Queue Type ──
        Route::get('/queue-type', [TrafficEngineeringController::class, 'queueTypes'])->name('queue-type');
        Route::post('/queue-type', [TrafficEngineeringController::class, 'queueTypeStore'])->name('queue-type-store');
        Route::put('/queue-type/{itemId}', [TrafficEngineeringController::class, 'queueTypeUpdate'])->name('queue-type-update');
        Route::delete('/queue-type/{itemId}', [TrafficEngineeringController::class, 'queueTypeDestroy'])->name('queue-type-destroy');
        Route::post('/queue-type/bulk', [TrafficEngineeringController::class, 'queueTypeBulk'])->name('queue-type-bulk');

        // ── Traffic Classification ──
        Route::get('/classification', [TrafficEngineeringController::class, 'trafficClassification'])->name('classification');

        // ── QoS Policy ──
        Route::get('/qos-policy', [TrafficEngineeringController::class, 'qosPolicy'])->name('qos-policy');

        // ── Bufferbloat Analyzer ──
        Route::get('/bufferbloat', [TrafficEngineeringController::class, 'bufferbloat'])->name('bufferbloat');

        // ── Traffic Analytics ──
        Route::get('/analytics', [TrafficEngineeringController::class, 'analytics'])->name('analytics');
    });

    // ── SMART QOS (standalone, outside NOC) — hidden per request (MikroTik) ──
    // Route::prefix('qos')->name('qos.')->group(function () {
    //     Route::get('/health', [QosHealthController::class, 'index'])->name('health');
    //     Route::get('/health/json', [QosHealthController::class, 'jsonHealth'])->name('health.json');
    //     Route::post('/sync', [QosHealthController::class, 'syncAll'])->name('sync-all');
    //     Route::post('/optimize', [QosHealthController::class, 'optimizeNow'])->name('optimize-now');
    // });

});

// ── ADMIN ONLY ──
Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/test-mikrotik', [SettingController::class, 'testMikrotik'])->name('settings.test-mikrotik');

    // ── INTEGRASI MIKROTIK & OLT ──
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations');
    Route::post('/settings/integrations/mikrotik', [IntegrationController::class, 'storeMikrotik'])->name('settings.integrations.mikrotik.store');
    Route::put('/settings/integrations/mikrotik/{mikrotikRouter}', [IntegrationController::class, 'updateMikrotik'])->name('settings.integrations.mikrotik.update');
    Route::delete('/settings/integrations/mikrotik/{mikrotikRouter}', [IntegrationController::class, 'destroyMikrotik'])->name('settings.integrations.mikrotik.destroy');
    Route::post('/settings/integrations/mikrotik/{mikrotikRouter}/test', [IntegrationController::class, 'testMikrotik'])->name('settings.integrations.mikrotik.test');
    Route::get('/settings/integrations/mikrotik/{mikrotikRouter}/live', [IntegrationController::class, 'liveMikrotik'])->name('settings.integrations.mikrotik.live');
    Route::post('/settings/integrations/olt', [IntegrationController::class, 'storeOlt'])->name('settings.integrations.olt.store');
    Route::put('/settings/integrations/olt/{olt}', [IntegrationController::class, 'updateOlt'])->name('settings.integrations.olt.update');
    Route::delete('/settings/integrations/olt/{olt}', [IntegrationController::class, 'destroyOlt'])->name('settings.integrations.olt.destroy');
    Route::post('/settings/integrations/olt/{olt}/test', [IntegrationController::class, 'testOlt'])->name('settings.integrations.olt.test');
    Route::get('/settings/integrations/olt/{olt}/live', [IntegrationController::class, 'liveOlt'])->name('settings.integrations.olt.live');

    // ── USER MANAGEMENT ──
    Route::get('/settings/users', [UserController::class, 'index'])->name('settings.users');
    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');
    Route::get('/settings/users/{user}/password', [UserController::class, 'password'])->name('settings.users.password');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // ── INVENTORY ──
    Route::get('/inventory/items', [InventoryController::class, 'items'])->name('inventory.items');
    Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items.store');
    Route::put('/inventory/items/{inventoryItem}', [InventoryController::class, 'updateItem'])->name('inventory.items.update');
    Route::delete('/inventory/items/{inventoryItem}', [InventoryController::class, 'destroyItem'])->name('inventory.items.destroy');
    Route::get('/inventory/masuk', [InventoryController::class, 'masuk'])->name('inventory.masuk');
    Route::post('/inventory/masuk', [InventoryController::class, 'storeMasuk'])->name('inventory.masuk.store');
    Route::get('/inventory/keluar', [InventoryController::class, 'keluar'])->name('inventory.keluar');
    Route::post('/inventory/keluar', [InventoryController::class, 'storeKeluar'])->name('inventory.keluar.store');
    Route::get('/inventory/laporan-aset', [InventoryController::class, 'laporanAset'])->name('inventory.laporan-aset');

    // ── MIKROTIK ACTIONS (hidden per request) ──
    // Route::post('/mikrotik/profiles', [MikrotikController::class, 'storeProfile'])->name('mikrotik.profiles.store');
    // Route::put('/mikrotik/profiles/{profileId}', [MikrotikController::class, 'updateProfile'])->name('mikrotik.profiles.update');
    // Route::delete('/mikrotik/profiles/{profileId}', [MikrotikController::class, 'destroyProfile'])->name('mikrotik.profiles.destroy');
    // Route::post('/mikrotik/ppp', [MikrotikController::class, 'storePppSecret'])->name('mikrotik.ppp.store');
    // Route::delete('/mikrotik/ppp/{secretId}', [MikrotikController::class, 'destroyPppSecret'])->name('mikrotik.ppp.destroy');
    // Route::post('/mikrotik/queues', [MikrotikController::class, 'storeQueue'])->name('mikrotik.queues.store');
    // Route::put('/mikrotik/queues/{queueId}', [MikrotikController::class, 'updateQueue'])->name('mikrotik.queues.update');
    // Route::delete('/mikrotik/queues/{queueId}', [MikrotikController::class, 'destroyQueue'])->name('mikrotik.queues.destroy');
    // Route::post('/mikrotik/ppp-profiles', [MikrotikController::class, 'storePppProfile'])->name('mikrotik.ppp-profiles.store');
    // Route::put('/mikrotik/ppp-profiles/{profileId}', [MikrotikController::class, 'updatePppProfile'])->name('mikrotik.ppp-profiles.update');
    // Route::delete('/mikrotik/ppp-profiles/{profileId}', [MikrotikController::class, 'destroyPppProfile'])->name('mikrotik.ppp-profiles.destroy');
    // Route::post('/mikrotik/hotspot-users/{userId}/toggle', [MikrotikController::class, 'toggleHotspotUser'])->name('mikrotik.hotspot-users.toggle');
    // Route::post('/mikrotik/hotspot-users', [MikrotikController::class, 'storeHotspotUser'])->name('mikrotik.hotspot-users.store');
    // Route::put('/mikrotik/hotspot-users/{userId}', [MikrotikController::class, 'updateHotspotUser'])->name('mikrotik.hotspot-users.update');
    // Route::delete('/mikrotik/hotspot-users/{userId}', [MikrotikController::class, 'destroyHotspotUser'])->name('mikrotik.hotspot-users.destroy');
    // Route::post('/mikrotik/backup', [MikrotikController::class, 'backup'])->name('mikrotik.backup');
    // Route::get('/mikrotik/live', [MikrotikController::class, 'liveData'])->name('mikrotik.live');

    // ── VOUCHER PROFILES (MikroTik) ──
    Route::get('/voucher-profiles', [VoucherProfileController::class, 'index'])->name('voucher-profiles.index');
    Route::post('/voucher-profiles', [VoucherProfileController::class, 'store'])->name('voucher-profiles.store');
    Route::post('/voucher-profiles/sync-mikrotik', [VoucherProfileController::class, 'syncMikrotik'])->name('voucher-profiles.sync-mikrotik');
    Route::post('/voucher-profiles/delete-mikrotik/{profileId}', [VoucherProfileController::class, 'destroyMikrotik'])->name('voucher-profiles.destroy-mikrotik');
    Route::post('/voucher-profiles/update-mikrotik/{profileId}', [VoucherProfileController::class, 'updateMikrotik'])->name('voucher-profiles.update-mikrotik');

    // ── MIKROTIK ROUTERS — hidden per request ──
    // Route::get('/mikrotik-routers', [MikrotikRouterController::class, 'index'])->name('mikrotik-routers.index');
    // Route::post('/mikrotik-routers', [MikrotikRouterController::class, 'store'])->name('mikrotik-routers.store');
    // Route::put('/mikrotik-routers/{mikrotikRouter}', [MikrotikRouterController::class, 'update'])->name('mikrotik-routers.update');
    // Route::delete('/mikrotik-routers/{mikrotikRouter}', [MikrotikRouterController::class, 'destroy'])->name('mikrotik-routers.destroy');
    // Route::post('/mikrotik-routers/{mikrotikRouter}/test', [MikrotikRouterController::class, 'test'])->name('mikrotik-routers.test');
    // ── DISTRIBUTION / ODC / ODP — hidden per request (OLT menu) ──
    // Route::post('/distribution/odcs', [DistributionController::class, 'storeOdc'])->name('distribution.odcs.store');
    // Route::put('/distribution/odcs/{odc}', [DistributionController::class, 'updateOdc'])->name('distribution.odcs.update');
    // Route::delete('/distribution/odcs/{odc}', [DistributionController::class, 'destroyOdc'])->name('distribution.odcs.destroy');
    // Route::post('/distribution/routes', [DistributionController::class, 'storeRoute'])->name('distribution.routes.store');
    // Route::put('/distribution/routes/{odpRoute}', [DistributionController::class, 'updateRoute'])->name('distribution.routes.update');
    // Route::delete('/distribution/routes/{odpRoute}', [DistributionController::class, 'destroyRoute'])->name('distribution.routes.destroy');
    // Route::post('/distribution/points', [DistributionController::class, 'storePoint'])->name('distribution.points.store');
    // Route::put('/distribution/points/{odpPoint}', [DistributionController::class, 'updatePoint'])->name('distribution.points.update');
    // Route::delete('/distribution/points/{odpPoint}', [DistributionController::class, 'destroyPoint'])->name('distribution.points.destroy');
    // Route::post('/distribution/odps', [DistributionController::class, 'storeOdp'])->name('distribution.odps.store');
    // Route::delete('/distribution/odps/{odp}', [DistributionController::class, 'destroyOdp'])->name('distribution.odps.destroy');
    // Route::get('/odc/{odc}', [OdcController::class, 'show'])->name('odc.show');
    // Route::get('/odp/{odp}', [OdpController::class, 'show'])->name('odp.show');
    // Route::post('/odp/{odp}/toggle-jalur', [OdpController::class, 'toggleJalur'])->name('odp.toggle-jalur');

    Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::get('/vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
    Route::post('/vouchers/quick-print', [VoucherController::class, 'quickPrint'])->name('vouchers.quick-print');
    Route::delete('/vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    Route::post('/vouchers/sync-mikrotik', [VoucherController::class, 'syncMikrotik'])->name('vouchers.sync-mikrotik');

    Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
    Route::post('/packages/mass-bill', [PackageController::class, 'massBill'])->name('packages.mass-bill');

    Route::post('/customers/sync-pppoe', [CustomerController::class, 'syncPppoe'])->name('customers.sync-pppoe');
    Route::post('/olts/sync-all-onu', [CustomerController::class, 'syncAllOnu'])->name('olt.sync-all-onu');

    Route::get('/voucher-templates/{template}/preview', [VoucherTemplateController::class, 'preview'])->name('voucher-templates.preview');
    Route::get('/voucher-templates/{template}/preview/{page?}', [VoucherTemplateController::class, 'preview'])->name('voucher-templates.preview-page');
    Route::post('/voucher-templates', [VoucherTemplateController::class, 'store'])->name('voucher-templates.store');
    Route::put('/voucher-templates/{template}', [VoucherTemplateController::class, 'update'])->name('voucher-templates.update');
    Route::delete('/voucher-templates/{template}', [VoucherTemplateController::class, 'destroy'])->name('voucher-templates.destroy');

    Route::get('/voucher-templates', [VoucherTemplateController::class, 'index'])->name('voucher-templates.index');
    Route::get('/voucher-templates/{template}/edit', [VoucherTemplateController::class, 'edit'])->name('voucher-templates.edit');

    // Voucher print templates (edit desain struk)
    Route::get('/voucher-print-templates', [VoucherPrintTemplateController::class, 'index'])->name('voucher-print-templates.index');
    Route::get('/voucher-print-templates/create', [VoucherPrintTemplateController::class, 'create'])->name('voucher-print-templates.create');
    Route::post('/voucher-print-templates', [VoucherPrintTemplateController::class, 'store'])->name('voucher-print-templates.store');
    Route::get('/voucher-print-templates/{template}/edit', [VoucherPrintTemplateController::class, 'edit'])->name('voucher-print-templates.edit');
    Route::put('/voucher-print-templates/{template}', [VoucherPrintTemplateController::class, 'update'])->name('voucher-print-templates.update');
    Route::delete('/voucher-print-templates/{template}', [VoucherPrintTemplateController::class, 'destroy'])->name('voucher-print-templates.destroy');
    Route::post('/voucher-print-templates/{template}/activate', [VoucherPrintTemplateController::class, 'activate'])->name('voucher-print-templates.activate');
    Route::get('/voucher-print-templates/{template}/preview', [VoucherPrintTemplateController::class, 'preview'])->name('voucher-print-templates.preview');
    Route::get('/voucher-print-templates/preview', [VoucherPrintTemplateController::class, 'preview'])->name('voucher-print-templates.preview-active');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::get('/backups/download/{filename}', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::post('/backups/database', [BackupController::class, 'database'])->name('backups.database');
    Route::get('/backups/restore', [BackupController::class, 'restoreForm'])->name('backups.restore-form');
    Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('/backups/upload', [BackupController::class, 'upload'])->name('backups.upload');
    Route::get('/backups/customers', [BackupController::class, 'customersBackupList'])->name('backups.customers');
    Route::post('/backups/customers', [BackupController::class, 'customersBackup'])->name('backups.customers.backup');
    Route::get('/backups/customers/{filename}/download', [BackupController::class, 'customersBackupDownload'])->name('backups.customers.download');

    Route::get('/export/invoices', [ExportController::class, 'invoices'])->name('export.invoices');
    Route::get('/export/payments', [ExportController::class, 'payments'])->name('export.payments');

});
