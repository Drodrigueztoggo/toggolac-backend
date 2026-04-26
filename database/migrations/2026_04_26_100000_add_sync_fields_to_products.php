<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('supplier_last_checked_at')->nullable()->after('supplier_cost');
            $table->boolean('supplier_in_stock')->default(true)->after('supplier_last_checked_at');
            $table->unsignedTinyInteger('supplier_consecutive_errors')->default(0)->after('supplier_in_stock');
            $table->boolean('sync_frozen')->default(false)->after('supplier_consecutive_errors');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_last_checked_at',
                'supplier_in_stock',
                'supplier_consecutive_errors',
                'sync_frozen',
            ]);
        });
    }
};
