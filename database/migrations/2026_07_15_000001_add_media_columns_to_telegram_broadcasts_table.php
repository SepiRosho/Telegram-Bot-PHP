<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return new class {
    public function up(): void
    {
        if (Capsule::schema()->hasColumn('telegram_broadcasts', 'media')) {
            return;
        }

        Capsule::schema()->table('telegram_broadcasts', function ($table) {
            $table->string('media')->nullable()->after('type');
            $table->unsignedBigInteger('notify_chat_id')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Capsule::schema()->table('telegram_broadcasts', function ($table) {
            $table->dropColumn(['media', 'notify_chat_id']);
        });
    }
};
