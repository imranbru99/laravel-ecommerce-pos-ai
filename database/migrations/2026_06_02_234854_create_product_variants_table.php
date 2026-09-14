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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->unique()->nullable();
            $table->decimal('price', 12, 2)->nullable();     // null = inherit from product
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('weight', 8, 3)->nullable();
            $table->string('image')->nullable();              // variant-specific image
            $table->json('attribute_values');                 // {"color":3,"size":7} — attribute_value IDs
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // Pivot: which attribute values belong to which variant (for filtering)
Schema::create('product_attribute_value', function (Blueprint $table) {
    $table->foreignId('product_variant_id')
          ->constrained('product_variants')
          ->cascadeOnDelete();

    $table->foreignId('attribute_value_id')
          ->constrained('attribute_values')
          ->cascadeOnDelete();

    $table->primary([
        'product_variant_id',
        'attribute_value_id'
    ]);
});;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
