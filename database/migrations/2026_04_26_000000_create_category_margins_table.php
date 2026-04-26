<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_margins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->unique();
            $table->decimal('min_margin_percent', 5, 2)->default(30.00);
            $table->decimal('price_increase_alert_threshold', 5, 2)->default(10.00);
            $table->decimal('price_increase_unpublish_threshold', 5, 2)->default(30.00);
            $table->timestamps();

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_margins');
    }
};
