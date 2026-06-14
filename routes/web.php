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
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\OltController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

// ── AUTH ──
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');
Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::get('/auth/{provider}/redirect', [SocialiteController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'callback'])->name('auth.callback');

Route::get('/', function () {
    return view('welcome');
});

// ── MIDTRANS (auth required for pay & finish, not for notification) ──
Route::post('/midtrans/notification', [MidtransController::class, 'notification'])->name('midtrans.notification');

// ── PORTAL PELANGGAN (public) ──
Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
Route::post('/portal', [PortalController::class, 'lookup'])->name('portal.lookup');
Route::get('/portal/bayar/{invoice}', [PortalController::class, 'bayar'])->name('portal.bayar');
Route::get('/portal/finish', [PortalController::class, 'finish'])->name('portal.finish');

// ── TEKNISI & ADMIN: all authenticated users ──
Route::middleware(['auth', 'teknisi'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customer/create', [CustomerController::class, 'create'])->name('customer.create');
    Route::post('/customer', [CustomerController::class, 'store'])->name('customer.store');
    Route::get('/customer/{customer}/edit', [CustomerController::class, 'edit'])->name('customer.edit');
    Route::put('/customer/{customer}', [CustomerController::class, 'update'])->name('customer.update');
    Route::delete('/customer/{customer}', [CustomerController::class, 'destroy'])->name('customer.destroy');
    Route::post('/customer/{customer}/suspend', [CustomerController::class, 'suspend'])->name('customer.suspend');
    Route::post('/customer/{customer}/activate', [CustomerController::class, 'activate'])->name('customer.activate');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoice/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoice.edit');
    Route::put('/invoice/{invoice}', [InvoiceController::class, 'update'])->name('invoice.update');
    Route::delete('/invoice/{invoice}', [InvoiceController::class, 'destroy'])->name('invoice.destroy');
    Route::get('/invoice/paid/{invoice}', [InvoiceController::class, 'markPaid'])->name('invoice.paid');
    Route::get('/invoice/print/{invoice}', [InvoiceController::class, 'print'])->name('invoice.print');
    Route::get('/invoice/reminder/{invoice}', [InvoiceController::class, 'sendReminder'])->name('invoice.reminder');
    Route::get('/invoice/email-reminder/{invoice}', [InvoiceController::class, 'sendEmailReminder'])->name('invoice.email-reminder');
    Route::get('/invoice/email-payment/{invoice}', [InvoiceController::class, 'sendEmailPayment'])->name('invoice.email-payment');

    Route::get('/payment/create/{invoice}', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payment/history/{invoice}', [PaymentController::class, 'history'])->name('payment.history');
    Route::delete('/payment/{payment}', [PaymentController::class, 'destroy'])->name('payment.destroy');

    Route::get('/mikrotik', [MikrotikController::class, 'dashboard'])->name('mikrotik.dashboard');
    Route::get('/mikrotik/profiles', [MikrotikController::class, 'profiles'])->name('mikrotik.profiles');
    Route::get('/mikrotik/active', [MikrotikController::class, 'activeSessions'])->name('mikrotik.active');
    Route::post('/mikrotik/active/disconnect/{sessionId}', [MikrotikController::class, 'disconnectHotspot'])->name('mikrotik.active.disconnect');
    Route::post('/mikrotik/active/ppp-disconnect/{sessionId}', [MikrotikController::class, 'disconnectPpp'])->name('mikrotik.active.ppp-disconnect');
    Route::get('/mikrotik/ppp', [MikrotikController::class, 'pppSecrets'])->name('mikrotik.ppp');
    Route::get('/mikrotik/queues', [MikrotikController::class, 'queues'])->name('mikrotik.queues');
    Route::get('/monitoring', [MikrotikController::class, 'monitoring'])->name('monitoring.index');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');

    // ── OLT (teknisi full access) ──
    Route::get('/olts', [OltController::class, 'index'])->name('olt.index');
    Route::get('/olts/create', [OltController::class, 'create'])->name('olt.create');
    Route::post('/olts', [OltController::class, 'store'])->name('olt.store');
    Route::get('/olts/{olt}', [OltController::class, 'show'])->name('olt.show');
    Route::get('/olts/{olt}/edit', [OltController::class, 'edit'])->name('olt.edit');
    Route::put('/olts/{olt}', [OltController::class, 'update'])->name('olt.update');
    Route::delete('/olts/{olt}', [OltController::class, 'destroy'])->name('olt.destroy');
    Route::post('/olts/{olt}/test', [OltController::class, 'testConnection'])->name('olt.test');
    Route::post('/olts/{olt}/scan', [OltController::class, 'scanOnus'])->name('olt.scan');
    Route::post('/olts/{olt}/onu/{onu}/reboot', [OltController::class, 'rebootOnu'])->name('olt.onu.reboot');
    Route::delete('/olts/{olt}/onu/{onu}', [OltController::class, 'removeOnu'])->name('olt.onu.remove');
    Route::post('/olts/{olt}/ports', [OltController::class, 'syncPorts'])->name('olt.ports.sync');
    Route::post('/onu/{onu}/link-customer', [OltController::class, 'linkCustomer'])->name('olt.onu.link');
    Route::get('/olts-monitoring', [OltController::class, 'monitoring'])->name('olt.monitoring');
    Route::get('/olts/map', [OltController::class, 'map'])->name('olt.map');
    Route::get('/olts/export', [OltController::class, 'exportOlt'])->name('olt.export');
    Route::get('/onus/export', [OltController::class, 'exportOnu'])->name('onu.export');
    Route::get('/onus/search', [OltController::class, 'searchOnu'])->name('onu.search');

    Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('/vouchers/{voucher}/print', [VoucherController::class, 'print'])->name('vouchers.print');
    Route::match(['get', 'post'], '/vouchers/print-batch', [VoucherController::class, 'printBatch'])->name('vouchers.print-batch');
    Route::post('/vouchers/{voucher}/used', [VoucherController::class, 'markUsed'])->name('vouchers.used');

    Route::get('/api/odp-routes', [OdpruteController::class, 'routes']);
    Route::get('/api/odp-points', [OdpruteController::class, 'points']);

    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');

    Route::get('/invoice/pdf/{invoice}', [InvoiceController::class, 'downloadPdf'])->name('invoice.pdf');

    Route::get('/midtrans/pay/{invoice}', [MidtransController::class, 'pay'])->name('midtrans.pay');
    Route::get('/midtrans/finish', [MidtransController::class, 'finish'])->name('midtrans.finish');

});

// ── ADMIN ONLY ──
Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/test-mikrotik', [SettingController::class, 'testMikrotik'])->name('settings.test-mikrotik');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::post('/mikrotik/profiles', [MikrotikController::class, 'storeProfile'])->name('mikrotik.profiles.store');
    Route::delete('/mikrotik/profiles/{profileId}', [MikrotikController::class, 'destroyProfile'])->name('mikrotik.profiles.destroy');
    Route::post('/mikrotik/ppp', [MikrotikController::class, 'storePppSecret'])->name('mikrotik.ppp.store');
    Route::delete('/mikrotik/ppp/{secretId}', [MikrotikController::class, 'destroyPppSecret'])->name('mikrotik.ppp.destroy');
    Route::post('/mikrotik/queues', [MikrotikController::class, 'storeQueue'])->name('mikrotik.queues.store');
    Route::delete('/mikrotik/queues/{queueId}', [MikrotikController::class, 'destroyQueue'])->name('mikrotik.queues.destroy');
    Route::post('/mikrotik/backup', [MikrotikController::class, 'backup'])->name('mikrotik.backup');

    Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');
    Route::post('/distribution/odcs', [DistributionController::class, 'storeOdc'])->name('distribution.odcs.store');
    Route::put('/distribution/odcs/{odc}', [DistributionController::class, 'updateOdc'])->name('distribution.odcs.update');
    Route::delete('/distribution/odcs/{odc}', [DistributionController::class, 'destroyOdc'])->name('distribution.odcs.destroy');
    Route::post('/distribution/routes', [DistributionController::class, 'storeRoute'])->name('distribution.routes.store');
    Route::put('/distribution/routes/{odpRoute}', [DistributionController::class, 'updateRoute'])->name('distribution.routes.update');
    Route::delete('/distribution/routes/{odpRoute}', [DistributionController::class, 'destroyRoute'])->name('distribution.routes.destroy');
    Route::post('/distribution/points', [DistributionController::class, 'storePoint'])->name('distribution.points.store');
    Route::put('/distribution/points/{odpPoint}', [DistributionController::class, 'updatePoint'])->name('distribution.points.update');
    Route::delete('/distribution/points/{odpPoint}', [DistributionController::class, 'destroyPoint'])->name('distribution.points.destroy');

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

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::get('/backups/download/{filename}', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::post('/backups/database', [BackupController::class, 'database'])->name('backups.database');

    Route::get('/export/invoices', [ExportController::class, 'invoices'])->name('export.invoices');
    Route::get('/export/payments', [ExportController::class, 'payments'])->name('export.payments');

});
