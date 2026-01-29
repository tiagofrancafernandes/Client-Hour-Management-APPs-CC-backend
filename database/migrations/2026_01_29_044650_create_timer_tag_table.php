<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('timer_tag', function (Blueprint $table) {
            $table->foreignId('timer_id')->constrained('timers')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();

            $table->primary(['timer_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timer_tag');
    }
};
