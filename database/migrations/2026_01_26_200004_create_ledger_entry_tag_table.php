<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('ledger_entry_tag', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->constrained('ledger_entries')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['ledger_entry_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entry_tag');
    }
};
