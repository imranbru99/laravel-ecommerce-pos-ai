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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('gateway', ['stripe', 'paypal', 'cod', 'bank_transfer', 'wallet'])->default('stripe');
            $table->string('transaction_id', 255)->nullable()->unique(); // gateway's transaction ID
            $table->string('payment_intent_id', 255)->nullable();        // Stripe PaymentIntent
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('method', 50)->nullable();       // card, bank_account, paypal_account
            $table->string('card_brand', 20)->nullable();   // visa, mastercard
            $table->string('card_last4', 4)->nullable();
            $table->string('card_exp_month', 2)->nullable();
            $table->string('card_exp_year', 4)->nullable();
            $table->json('gateway_response')->nullable();   // full raw response
            $table->json('metadata')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message')->nullable();
            $table->string('receipt_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
            $table->index('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
