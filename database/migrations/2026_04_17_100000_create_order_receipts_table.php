<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_receipts', function (Blueprint $table) {
            $table->id();

            // ── Core references ────────────────────────────────────────────────
            $table->unsignedBigInteger('purchase_order_id')->unique();
            $table->string('invoice_number', 30)->nullable()->index();

            // ── Customer snapshot (frozen at payment time) ─────────────────────
            $table->string('customer_name',  120);
            $table->string('customer_email', 160);
            $table->string('shipping_address')->nullable();

            // ── Payment proof (chargeback defense) ────────────────────────────
            $table->string('payment_transaction_id',   100)->nullable();   // dLocal payment_id
            $table->string('payment_authorization_code', 60)->nullable();  // from dLocal response
            $table->string('payment_method_type',       30)->nullable();   // CARD, WALLET, etc.
            $table->string('payment_card_brand',        20)->nullable();   // VISA, MASTERCARD…
            $table->string('payment_last_4',             4)->nullable();
            $table->decimal('payment_amount',  10, 2)->nullable();
            $table->string('payment_currency',  3)->nullable();
            $table->timestamp('payment_approved_at')->nullable();

            // ── Network evidence ───────────────────────────────────────────────
            $table->string('customer_ip', 45)->nullable();                 // IPv4 or IPv6
            $table->string('user_agent')->nullable();

            // ── Full order snapshot (JSON) ─────────────────────────────────────
            // Immutable copy of everything at time of payment — products, prices, taxes
            $table->json('order_snapshot')->nullable();

            // ── PDF storage path ───────────────────────────────────────────────
            $table->string('receipt_pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // ── Immutable timestamps (NEVER delete these rows) ─────────────────
            $table->timestamps();

            $table->foreign('purchase_order_id')
                  ->references('id')
                  ->on('purchase_order_headers')
                  ->onDelete('restrict');   // prevent cascade delete
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_receipts');
    }
};
