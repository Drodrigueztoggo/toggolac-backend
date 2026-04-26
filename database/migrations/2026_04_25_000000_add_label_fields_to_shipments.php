<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('package_length', 8, 2)->nullable()->after('pounds_weight');
            $table->decimal('package_width',  8, 2)->nullable()->after('package_length');
            $table->decimal('package_height', 8, 2)->nullable()->after('package_width');
            $table->string('shippo_transaction_id')->nullable()->after('package_height');
            $table->string('label_url', 500)->nullable()->after('shippo_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['package_length', 'package_width', 'package_height', 'shippo_transaction_id', 'label_url']);
        });
    }
};
