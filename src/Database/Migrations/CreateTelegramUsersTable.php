<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->bigInteger('chat_id');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language_code', 10)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('role', 50)->default('user');
            $table->json('permissions')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('step')->nullable();
            $table->json('temp_data')->nullable();
            $table->string('current_panel', 20)->default('user');
            $table->string('referral_code', 32)->nullable()->unique();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent()->useCurrentOnUpdate();
            $table->json('rate_hits')->nullable();

            $table->foreign('invited_by')
                ->references('id')
                ->on('telegram_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
