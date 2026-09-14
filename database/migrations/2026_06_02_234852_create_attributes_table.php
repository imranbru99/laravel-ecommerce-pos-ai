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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);      // Color, Size, Material
            $table->string('slug', 120)->unique();
            $table->enum('type', ['select', 'color', 'button', 'radio'])->default('select');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
