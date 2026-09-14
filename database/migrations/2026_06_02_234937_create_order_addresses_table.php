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
        Schema::create('order_addresses', function (Blueprint $table) {
           $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['shipping', 'billing']);
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('company', 100)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20);
            $table->string('country', 2)->default('US');
            $table->string('phone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
