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
        Schema::create('flash_deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('status')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('banner')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });

        Schema::create('flash_deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('discount_type', ['fixed', 'percentage'])->default('percentage');
            $table->unsignedInteger('purchase_limit')->default(0); // 0 = unlimited
            $table->timestamps();

            $table->unique(['flash_deal_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flash_deal_products');
        Schema::dropIfExists('flash_deals');
    }
};
