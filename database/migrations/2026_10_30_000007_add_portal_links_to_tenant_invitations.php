<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tenant_invitations', function (Blueprint $table) {
            $table->json('portal_links')->nullable()->after('role');
            $table->json('branch_ids')->nullable()->after('portal_links');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_invitations', fn (Blueprint $table) => $table->dropColumn(['portal_links', 'branch_ids']));
    }
};
