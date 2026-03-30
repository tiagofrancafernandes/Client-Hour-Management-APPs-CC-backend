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
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('token', 6); // 6-digit numeric code
            $table->string('hash')->unique(); // Signed URL hash
            $table->enum('type', ['registration', 'password_reset'])->default('registration');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('email');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
