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
        Schema::create('credit_purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_purchase_id');
            $table->enum('payment_method', ['pix_offline', 'bank_transfer'])->default('bank_transfer');
            $table->enum('payment_status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->string('pix_receipt_path')->nullable();
            $table->unsignedBigInteger('receipt_approved_by')->nullable();
            $table->timestamp('receipt_approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreign('credit_purchase_id')->references('id')->on('credit_purchases')->onDelete('cascade');
            $table->foreign('receipt_approved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_purchase_payments');
    }
};
