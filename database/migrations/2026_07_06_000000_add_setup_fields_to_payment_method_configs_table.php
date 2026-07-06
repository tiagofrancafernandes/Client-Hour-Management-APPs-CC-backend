<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('payment_method_configs', function (Blueprint $table) {
            $table->json('setup_fields')->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_configs', function (Blueprint $table) {
            $table->dropColumn('setup_fields');
        });
    }
};
