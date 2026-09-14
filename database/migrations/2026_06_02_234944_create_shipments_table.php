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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 100)->nullable();          // DHL, FedEx, UPS
            $table->string('service', 100)->nullable();          // Express, Ground
            $table->string('tracking_number', 200)->nullable();
            $table->string('tracking_url')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('weight', 8, 3)->nullable();
            $table->json('items')->nullable();                   // which order_item ids are in this shipment
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->enum('status', ['pending', 'shipped', 'in_transit', 'delivered', 'failed', 'returned'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
