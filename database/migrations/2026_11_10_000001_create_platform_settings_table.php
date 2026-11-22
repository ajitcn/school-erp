<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Store platform-wide provider settings separately from tenant settings. */
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /** Remove platform provider settings. */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
