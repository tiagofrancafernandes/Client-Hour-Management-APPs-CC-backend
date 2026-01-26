<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('hourly_rate_reference', 10, 2)->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
