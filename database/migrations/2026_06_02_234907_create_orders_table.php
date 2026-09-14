<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // guest checkout
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 30)->unique(); // ORD-2024-000001
            $table->enum('status', [
                'pending',          // just placed, awaiting payment
                'processing',       // payment confirmed
                'on_hold',          // needs manual review
                'confirmed',        // seller confirmed
                'shipped',          // dispatched
                'out_for_delivery', // local delivery
                'delivered',        // completed
                'cancelled',        // cancelled
                'refunded',         // fully refunded
                'partially_refunded',
                'failed',           // payment failed
            ])->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid', 'partially_paid', 'refunded', 'failed'])->default('unpaid');
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2);         // sum of line items before discounts
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);            // final charged amount
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->string('shipping_method', 100)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'payment_status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
