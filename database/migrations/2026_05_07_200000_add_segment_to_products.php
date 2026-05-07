<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products ADD COLUMN segment VARCHAR(20) NULL DEFAULT NULL AFTER name_product_en");
        DB::statement("ALTER TABLE products ADD INDEX idx_products_segment (segment)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products DROP INDEX idx_products_segment");
        DB::statement("ALTER TABLE products DROP COLUMN segment");
    }
};
