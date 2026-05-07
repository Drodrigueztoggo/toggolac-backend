<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        DB::statement("ALTER TABLE products ADD COLUMN lots_category VARCHAR(50) NULL DEFAULT NULL AFTER segment");
        DB::statement("ALTER TABLE products ADD INDEX idx_products_lots_category (lots_category)");
    }

    public function down(): void {
        DB::statement("ALTER TABLE products DROP INDEX idx_products_lots_category");
        DB::statement("ALTER TABLE products DROP COLUMN lots_category");
    }
};
