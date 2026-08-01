<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_payment_status_index')) {
                $table->index('payment_status');
            }
            if (! Schema::hasIndex('invoices', 'invoices_billing_period_index')) {
                $table->index('billing_period');
            }
            if (! Schema::hasIndex('invoices', 'invoices_paid_at_index')) {
                $table->index('paid_at');
            }
            if (! Schema::hasIndex('invoices', 'invoices_payment_method_index')) {
                $table->index('payment_method');
            }
            if (! Schema::hasIndex('invoices', 'invoices_status_period_index')) {
                $table->index(['payment_status', 'billing_period']);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasIndex('customers', 'customers_status_index')) {
                $table->index('status');
            }
            if (! Schema::hasIndex('customers', 'customers_due_date_index')) {
                $table->index('due_date');
            }
            if (! Schema::hasIndex('customers', 'customers_package_id_index')) {
                $table->index('package_id');
            }
        });

        Schema::table('odp_ports', function (Blueprint $table) {
            if (! Schema::hasIndex('odp_ports', 'odp_ports_status_index')) {
                $table->index('status');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasIndex('activity_logs', 'activity_logs_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['billing_period']);
            $table->dropIndex(['paid_at']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['payment_status', 'billing_period']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['package_id']);
        });

        Schema::table('odp_ports', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
