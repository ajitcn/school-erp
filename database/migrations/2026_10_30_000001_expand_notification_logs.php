<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('notification_logs', 'payload')) {
            Schema::table('notification_logs', fn (Blueprint $table) => $table->json('payload')->nullable());
        }
        if (! Schema::hasColumn('notification_logs', 'read_at')) {
            Schema::table('notification_logs', fn (Blueprint $table) => $table->timestamp('read_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            if (Schema::hasColumn('notification_logs', 'read_at')) {
                $table->dropColumn('read_at');
            }
            if (Schema::hasColumn('notification_logs', 'payload')) {
                $table->dropColumn('payload');
            }
        });
    }
};
