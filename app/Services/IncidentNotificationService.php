<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Incident;
use App\Models\IncidentNotification;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IncidentNotificationService
{
    protected FonnteService $fonnte;

    protected ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->fonnte = new FonnteService($tenantId);
    }

    public function notifyCreated(Incident $incident, ?array $customerIds = null): void
    {
        $this->notifyTechnician($incident, $this->buildTechnicianCreatedMessage($incident));
        $this->notifyAffectedCustomers($incident, 'created', $customerIds);
    }

    public function notifyStatusChange(Incident $incident, string $newStatus): void
    {
        $statusLabel = match ($newStatus) {
            'investigating' => 'SEDANG DITANGANI',
            'resolved' => 'SELESAI',
            'closed' => 'DITUTUP',
            default => strtoupper($newStatus),
        };

        $customerIds = $incident->notifiable_customer_ids;

        if ($newStatus === 'investigating') {
            $this->notifyAffectedCustomers($incident, 'status_change', $customerIds);
        }

        if ($newStatus === 'resolved') {
            $this->notifyAffectedCustomers($incident, 'resolved', $customerIds);
        }
    }

    public function notifySlaBreached(Incident $incident): void
    {
        $message = $this->buildSlaBreachMessage($incident);

        $this->notifyTechnician($incident, $message, 'sla_warning');
    }

    protected function notifyTechnician(Incident $incident, string $message, string $notificationType = 'created'): void
    {
        $tenantId = $incident->tenant_id;
        $phone = Setting::get('notif_phone_teknisikoordinator', null, $tenantId)
              ?? Setting::get('admin_phone', null, $tenantId)
              ?? '';

        if (! $phone) {
            return;
        }

        $notification = IncidentNotification::create([
            'incident_id' => $incident->id,
            'recipient_phone' => $phone,
            'recipient_type' => 'technician',
            'recipient_name' => 'Teknisi Koordinator',
            'message' => $message,
            'notification_type' => $notificationType,
            'status' => 'pending',
        ]);

        $result = $this->fonnte->send($phone, $message);

        if ($result['success']) {
            $notification->markSent();
        } else {
            $notification->markFailed();
            Log::warning("IncidentNotification: gagal kirim WA ke teknisi: {$result['error']}");
        }
    }

    protected function notifyAffectedCustomers(Incident $incident, string $type, ?array $customerIds = null): void
    {
        $query = $incident->affectedCustomers()
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if (! empty($customerIds)) {
            $query->whereIn('customers.id', $customerIds);
        }

        $customers = $query->get();

        Log::info('IncidentNotificationService::notifyAffectedCustomers', [
            'incident_id' => $incident->id,
            'odp_id' => $incident->odp_id,
            'type' => $type,
            'customer_ids_filter' => $customerIds,
            'customers_found' => $customers->count(),
            'customer_names' => $customers->pluck('name', 'id')->toArray(),
        ]);

        foreach ($customers as $customer) {
            $cooldownKey = "incident_wa_{$incident->odp_id}_{$customer->id}";
            if (Cache::has($cooldownKey)) {
                Log::info("IncidentNotification: skip {$customer->name} - cooldown (24h per ODP)");

                continue;
            }

            $message = match ($type) {
                'created' => $this->buildCustomerCreatedMessage($incident, $customer),
                'status_change' => $this->buildCustomerInvestigatingMessage($incident, $customer),
                'resolved' => $this->buildCustomerResolvedMessage($incident, $customer),
                default => '',
            };

            if (! $message) {
                continue;
            }

            $notification = IncidentNotification::create([
                'incident_id' => $incident->id,
                'recipient_phone' => $customer->phone,
                'recipient_type' => 'customer',
                'recipient_name' => $customer->name,
                'customer_id' => $customer->id,
                'message' => $message,
                'notification_type' => $type === 'investigating' ? 'status_change' : $type,
                'status' => 'pending',
            ]);

            $result = $this->fonnte->send($customer->phone, $message);

            if ($result['success']) {
                $notification->markSent();
                Cache::put($cooldownKey, true, now()->addHours(24));
            } else {
                $notification->markFailed();
                Log::warning("IncidentNotification: gagal kirim WA ke {$customer->name}: {$result['error']}");
            }

            usleep(1500000);
        }
    }

    protected function buildTechnicianCreatedMessage(Incident $incident): string
    {
        $severityLabel = match ($incident->severity) {
            'critical' => '🔴 CRITICAL',
            'high' => '🟠 TINGGI',
            'medium' => '🟡 SEDANG',
            'low' => '🟢 RENDAH',
            default => $incident->severity,
        };

        $odpName = $incident->odp?->nama_odp ?? '-';
        $odcName = $incident->odc?->nama_odc ?? $incident->odp?->odc?->nama_odc ?? '-';
        $deadline = $incident->sla_deadline ? $incident->sla_deadline->format('d/m/Y H:i') : '-';
        $tube = $incident->odp?->kabel_tube_color ?? '-';
        $core = $incident->odp?->kabel_core_number ?? '-';

        $msg = "⚠️ *ALERT GANGGUAN*\n\n"
            ."ID: #{$incident->id}\n"
            ."Judul: {$incident->title}\n"
            ."Severity: {$severityLabel}\n\n"
            ."Lokasi:\n"
            ."ODP: {$odpName}\n"
            ."ODC: {$odcName}\n"
            ."Tube: {$tube} | Core: {$core}\n\n"
            ."SLA Deadline: {$deadline}\n";

        if ($incident->description) {
            $msg .= "\nDetail: {$incident->description}\n";
        }

        $msg .= "\nSegera lakukan pengecekan dan penanganan.";

        return $msg;
    }

    protected function buildSlaBreachMessage(Incident $incident): string
    {
        $odpName = $incident->odp?->nama_odp ?? '-';
        $deadline = $incident->sla_deadline ? $incident->sla_deadline->format('d/m/Y H:i') : '-';
        $elapsed = $incident->sla_deadline ? now()->diffForHumans($incident->sla_deadline, true).' yang lalu' : '-';

        return "🚨 *SLA BREACHED*\n\n"
            ."Incident #{$incident->id}: {$incident->title}\n"
            ."Lokasi: {$odpName}\n"
            ."Deadline: {$deadline}\n"
            ."Terlambat: {$elapsed}\n"
            .'Status: '.strtoupper($incident->status)."\n\n"
            .'Gangguan ini sudah melewati batas waktu perbaikan. Segera tindak lanjuti!';
    }

    protected function buildCustomerCreatedMessage(Incident $incident, Customer $customer): string
    {
        $odpName = $incident->odp?->nama_odp ?? '-';
        $severityLabel = match ($incident->severity) {
            'critical' => 'Kritis',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
            default => $incident->severity,
        };
        $deadline = $incident->sla_deadline ? $incident->sla_deadline->format('d/m/Y H:i') : 'Secepatnya';

        $msg = "Yth. Bapak/Ibu *{$customer->name}*,\n\n"
            ."Kami informasikan terjadi gangguan jaringan di area Anda (ODP {$odpName}).\n\n"
            ."Tingkat gangguan: {$severityLabel}\n"
            ."Estimasi perbaikan: {$deadline}\n";

        if ($incident->description) {
            $msg .= "\nDetail: {$incident->description}\n";
        }

        $msg .= "\nKami mohon maaf atas ketidaknyamanan ini. Tim teknisi sedang berupaya menangani gangguan secepatnya."
            ."\n\n_ALKONEK NETWORK ACCESS_";

        return $msg;
    }

    protected function buildCustomerInvestigatingMessage(Incident $incident, Customer $customer): string
    {
        $odpName = $incident->odp?->nama_odp ?? '-';
        $deadline = $incident->sla_deadline ? $incident->sla_deadline->format('d/m/Y H:i') : 'Secepatnya';

        return "Yth. Bapak/Ibu *{$customer->name}*,\n\n"
            ."Update gangguan di area Anda (ODP {$odpName}):\n"
            ."Tim teknisi sedang dalam proses penanganan.\n\n"
            ."Estimasi perbaikan: {$deadline}\n\n"
            .'Kami mohon kesabaran Anda.'
            ."\n\n_ALKONEK NETWORK ACCESS_";
    }

    protected function buildCustomerResolvedMessage(Incident $incident, Customer $customer): string
    {
        $odpName = $incident->odp?->nama_odp ?? '-';

        return "Yth. Bapak/Ibu *{$customer->name}*,\n\n"
            ."✅ Gangguan di area Anda (ODP {$odpName}) sudah *SELESAI* dan layanan normal kembali.\n\n"
            .'Terima kasih atas kesabaran Anda.'
            ."\n\n_ALKONEK NETWORK ACCESS_";
    }
}
