<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Onu;
use Illuminate\Console\Command;

class BatchLinkOnu extends Command
{
    protected $signature = 'olt:batch-link {--link-by-ont : Auto-link by ONT ID order (risky)}';

    protected $description = 'Bulk link OLT ONU records to customers by serial_number';

    public function handle(): int
    {
        $unlinkedOnus = Onu::whereNull('customer_id')
            ->whereNotNull('serial_number')
            ->where('onu_id', 'like', '%/%/%')
            ->get();

        if ($unlinkedOnus->isEmpty()) {
            $this->info('Semua ONU sudah tertautkan.');

            return 0;
        }

        $unlinkedCustomers = Customer::where('status', 'active')
            ->whereNotNull('pppoe_username')
            ->whereNull('serial_number')
            ->whereDoesntHave('onus', fn ($q) => $q->whereNotNull('serial_number'))
            ->get();

        $this->info("OLT ONU tanpa pelanggan: {$unlinkedOnus->count()}");
        $this->info("Pelanggan tanpa OLT ONU: {$unlinkedCustomers->count()}");

        $this->newLine();
        $this->info('=== OLT ONU yang belum ditautkan ===');
        foreach ($unlinkedOnus as $onu) {
            $this->line("  SN: {$onu->serial_number}  |  ONU ID: {$onu->onu_id}  |  Rx: ".($onu->rx_power ?? '-').' dBm');
        }

        $this->newLine();
        $this->info('=== Pelanggan yang belum ditautkan ===');
        foreach ($unlinkedCustomers as $c) {
            $this->line("  ID: {$c->id}  |  {$c->name}  |  PPPoE: {$c->pppoe_username}");
        }

        if ($this->option('link-by-ont')) {
            return $this->linkByOntOrder($unlinkedOnus, $unlinkedCustomers);
        }

        $this->newLine();
        $this->warn('Gunakan --link-by-ont untuk auto-link atau hubungkan manual dari halaman monitoring.');

        return 0;
    }

    private function linkByOntOrder($unlinkedOnus, $unlinkedCustomers): int
    {
        if ($unlinkedOnus->count() !== $unlinkedCustomers->count()) {
            $this->error("Jumlah ONU ({$unlinkedOnus->count()}) ≠ jumlah pelanggan ({$unlinkedCustomers->count()}). Tidak bisa auto-link.");

            return 1;
        }

        if ($unlinkedOnus->isEmpty()) {
            $this->info('Tidak ada ONU untuk ditautkan.');

            return 0;
        }

        $sortedOnus = $unlinkedOnus->sortBy('onu_id')->values();
        $sortedCustomers = $unlinkedCustomers->sortBy('id')->values();

        $this->warn('Matching '.count($sortedOnus)." pasang berdasarkan urutan ONT ID. Ketik 'yes' untuk konfirmasi:");

        foreach ($sortedOnus as $i => $onu) {
            $customer = $sortedCustomers[$i];
            $this->line("  {$onu->serial_number} (ONT {$onu->onu_id}) → {$customer->name} ({$customer->pppoe_username})");
        }

        if (! $this->confirm('Lanjutkan auto-link?')) {
            $this->info('Dibatalkan.');

            return 0;
        }

        $linked = 0;
        foreach ($sortedOnus as $i => $onu) {
            $customer = $sortedCustomers[$i];
            $onu->update(['customer_id' => $customer->id]);
            $customer->update(['serial_number' => $onu->serial_number]);
            $linked++;
        }

        $this->info("Berhasil menautkan {$linked} ONU ke pelanggan.");

        return 0;
    }
}
