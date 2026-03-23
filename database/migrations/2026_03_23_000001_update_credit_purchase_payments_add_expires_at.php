<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('credit_purchase_payments', function (Blueprint $table) {
            $table->enum('payment_method', ['pix_offline', 'bank_transfer'])->nullable()->default(null)->change();
            $table->timestamp('expires_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('credit_purchase_payments', function (Blueprint $table) {
            $table->dropColumn('expires_at');
            $table->enum('payment_method', ['pix_offline', 'bank_transfer'])->nullable(false)->default('bank_transfer')->change();
        });
    }
};
