<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_carts', function (Blueprint $table) {
            $table->timestamp('reserved_at')->nullable()->after('by');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_carts', function (Blueprint $table) {
            $table->dropColumn('reserved_at');
        });
    }
};
