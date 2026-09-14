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
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');             // seoable_type + seoable_id
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('keywords')->nullable();
            $table->string('og_title', 160)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->enum('robots', ['index,follow','noindex,follow','index,nofollow','noindex,nofollow'])->default('index,follow');
            $table->json('schema_markup')->nullable(); // JSON-LD structured data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};
