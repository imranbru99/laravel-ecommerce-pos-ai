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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 200)->nullable();
            $table->enum('type', ['percent', 'fixed', 'free_shipping'])->default('percent');
            $table->decimal('value', 10, 2);               // percent OR fixed amount
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // cap on % discounts
            $table->unsignedInteger('usage_limit')->nullable();        // total uses allowed
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('applies_to_sale_items')->default(false);
            $table->json('product_ids')->nullable();        // restrict to specific products
            $table->json('category_ids')->nullable();       // restrict to specific categories
            $table->json('user_ids')->nullable();           // restrict to specific users
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
