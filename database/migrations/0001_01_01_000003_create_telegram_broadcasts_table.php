<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return new class {
    public function up(): void
    {
        if (Capsule::schema()->hasTable('telegram_broadcasts')) {
            return;
        }

        Capsule::schema()->create('telegram_broadcasts', function ($table) {
            $table->id();
            $table->text('message');
            $table->string('type', 50)->default('text');
            $table->json('options')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('telegram_broadcasts');
    }
};
