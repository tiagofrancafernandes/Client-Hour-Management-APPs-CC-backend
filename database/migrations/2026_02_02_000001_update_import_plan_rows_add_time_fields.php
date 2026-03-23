<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('import_plan_rows', function (Blueprint $table) {
            $table->dateTime('start_time')->nullable()->after('reference_date');
            $table->dateTime('end_time')->nullable()->after('start_time');
            $table->enum('input_type', ['debit', 'credit', 'adjustment'])->default('debit')->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('import_plan_rows', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'input_type']);
        });
    }
};
