<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add storage for keyed, short-lived portal password setup PINs. */
    public function up(): void
    {
        if (! Schema::hasTable('portal_password_setup_tokens')) {
            return;
        }

        Schema::table('portal_password_setup_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('portal_password_setup_tokens', 'pin_hash')) {
                $table->char('pin_hash', 64)->nullable()->index();
            }

            if (! Schema::hasColumn('portal_password_setup_tokens', 'pin_expires_at')) {
                $table->timestamp('pin_expires_at')->nullable()->index();
            }
        });
    }

    /** Remove the optional PIN fields. */
    public function down(): void
    {
        if (! Schema::hasTable('portal_password_setup_tokens')) {
            return;
        }

        Schema::table('portal_password_setup_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('portal_password_setup_tokens', 'pin_expires_at')) {
                $table->dropColumn('pin_expires_at');
            }

            if (Schema::hasColumn('portal_password_setup_tokens', 'pin_hash')) {
                $table->dropIndex(['pin_hash']);
                $table->dropColumn('pin_hash');
            }
        });
    }
};
