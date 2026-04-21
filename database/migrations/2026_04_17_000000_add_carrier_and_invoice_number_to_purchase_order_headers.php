<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_headers', function (Blueprint $table) {
            // Human-readable shipping carrier name (e.g. "FedEx", "DHL", "UPS")
            // Separate from conveyor_id which is the internal FK to the conveyors table
            $table->string('carrier', 100)->nullable()->after('guide_number');

            // Auto-generated invoice number in format TGG-YYYY-000001
            // Used on receipts, View Details page, and email
            $table->string('invoice_number', 30)->nullable()->unique()->after('carrier');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_headers', function (Blueprint $table) {
            $table->dropColumn(['carrier', 'invoice_number']);
        });
    }
};
