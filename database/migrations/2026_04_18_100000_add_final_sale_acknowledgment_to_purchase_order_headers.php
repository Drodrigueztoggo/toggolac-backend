<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_headers', function (Blueprint $table) {
            $table->boolean('final_sale_acknowledged')->default(false)->after('invoice_number');
            $table->timestamp('final_sale_acknowledged_at')->nullable()->after('final_sale_acknowledged');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_headers', function (Blueprint $table) {
            $table->dropColumn(['final_sale_acknowledged', 'final_sale_acknowledged_at']);
        });
    }
};
