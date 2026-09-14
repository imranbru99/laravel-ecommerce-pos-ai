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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();  // INV-2024-000001
            $table->enum('status', ['draft', 'sent', 'paid', 'void', 'overdue'])->default('draft');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->text('footer')->nullable();
            $table->string('pdf_path')->nullable();          // storage path for generated PDF
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
