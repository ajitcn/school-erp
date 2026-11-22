<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique(['event']);
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
        $tenantId = DB::table('tenants')->orderBy('id')->value('id');
        if ($tenantId) {
            DB::table('notification_templates')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
        Schema::table('notification_templates', fn (Blueprint $table) => $table->unique(['tenant_id', 'event']));
    }

    public function down(): void
    {
        $duplicates = DB::table('notification_templates')->select('event')->groupBy('event')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot restore global notification event uniqueness while tenants have templates with the same event key.');
        }
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'event']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('event');
        });
    }
};
