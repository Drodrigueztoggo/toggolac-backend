<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add postal code to orders so US direct-ship addresses are complete
        if (!Schema::hasColumn('purchase_order_headers', 'destination_postal_code')) {
            Schema::table('purchase_order_headers', function (Blueprint $table) {
                $table->string('destination_postal_code', 20)->nullable()->after('destination_address');
            });
        }

        Schema::create('fulfillment_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('supplier_url', 512);
            $table->string('gateway', 20);                  // 'dlocal' | 'stripe'
            $table->string('shipping_mode', 20);            // 'consolidation' | 'direct'
            $table->string('shipping_name');
            $table->string('shipping_address1');
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_state');
            $table->string('shipping_zip', 20);
            $table->string('shipping_country', 5)->default('US');
            $table->string('status', 20)->default('pending'); // pending|processing|completed|failed|skipped
            $table->string('lots_order_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['status', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_jobs');
        Schema::table('purchase_order_headers', function (Blueprint $table) {
            $table->dropColumn('destination_postal_code');
        });
    }
};
