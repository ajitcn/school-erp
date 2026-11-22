<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add per-user announcement reads and a durable record for each broadcast.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notice_reads')) {
            Schema::create('notice_reads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->timestamps();
                $table->unique(['tenant_id', 'notice_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('notification_broadcasts')) {
            Schema::create('notification_broadcasts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject', 180);
                $table->text('message');
                $table->unsignedInteger('recipient_count')->default(0);
                $table->timestamps();
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    /** Remove the tables introduced by this migration. */
    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notification_broadcasts');
    }
};
