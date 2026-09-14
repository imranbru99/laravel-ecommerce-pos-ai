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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general'); // general, mail, payment, seo
            $table->string('key', 100);
            $table->longText('value')->nullable();
            $table->enum('type', ['text', 'textarea', 'boolean', 'integer', 'float', 'json', 'image', 'file'])->default('text');
            $table->string('label', 200)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // expose to frontend?
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
