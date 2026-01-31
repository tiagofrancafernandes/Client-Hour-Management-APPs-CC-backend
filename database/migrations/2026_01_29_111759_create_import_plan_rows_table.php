<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('import_plan_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_plan_id')->constrained()->cascadeOnDelete();
            $table->integer('row_number');
            $table->date('reference_date')->index()->useCurrent();
            $table->decimal('hours', 8, 2);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('validation_errors')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_plan_rows');
    }
};
