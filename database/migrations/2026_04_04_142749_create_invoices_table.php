<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->unsignedInteger('invoice_number')->unique();
            $table->unsignedInteger('client_invoice_number');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
            $table->json('issued_by')->nullable();
            $table->json('bill_to')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'client_invoice_number']);
            $table->index('invoice_number');
            $table->index('client_id');
            $table->index('issue_date');
            $table->index('status');
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
