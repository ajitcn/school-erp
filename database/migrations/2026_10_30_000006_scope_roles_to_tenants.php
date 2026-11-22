<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('roles_name_unique');
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('tenant_user', function (Blueprint $table) {
            $table->foreignId('primary_branch_id')->nullable()->after('role')->constrained('branches')->nullOnDelete();
        });
        Schema::table('user_branch_access', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('branch_id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('tenant_user as tu')->join('users as u', 'u.id', '=', 'tu.user_id')
            ->whereNotNull('u.branch_id')
            ->get(['tu.tenant_id', 'tu.user_id', 'u.branch_id']) as $membership) {
            if (DB::table('branches')->where('id', $membership->branch_id)->where('tenant_id', $membership->tenant_id)->exists()) {
                DB::table('tenant_user')->where('tenant_id', $membership
                    ->tenant_id)
                    ->where('user_id', $membership
                        ->user_id)
                    ->update(['primary_branch_id' => $membership
                        ->branch_id]);
            }
        }

        foreach (DB::table('user_branch_access')->get(['user_id', 'branch_id']) as $access) {
            $tenantId = DB::table('branches')->where('id', $access->branch_id)->value('tenant_id');
            if ($tenantId) {
                DB::table('user_branch_access')->where('user_id', $access->user_id)->where('branch_id', $access->branch_id)->update(['tenant_id' => $tenantId]);
            }
        }

        // Legacy role assignments were global. Move ordinary school roles to
        // each tenant membership before removing those shared assignments.
        $legacyAssignments = DB::table('user_role as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('tenant_user as tu', 'tu.user_id', '=', 'ur.user_id')
            ->where('r.name', '!=', 'super_admin')
            ->select('tu.tenant_id', 'tu.user_id', 'tu.role as membership_role', 'r.name as role_name')
            ->get();

        foreach ($legacyAssignments as $assignment) {
            if (in_array($assignment->membership_role, ['owner', 'administrator'], true)) {
                continue;
            }

            DB::table('tenant_user')
                ->where('tenant_id', $assignment->tenant_id)
                ->where('user_id', $assignment->user_id)
                ->update(['role' => $assignment->role_name]);
        }

        DB::table('user_role')
            ->whereIn('user_id', DB::table('tenant_user')->select('user_id'))
            ->whereNotIn('role_id', DB::table('roles')->where('name', 'super_admin')->select('id'))
            ->delete();

        DB::table('user_role')
            ->whereIn('user_id', DB::table('users')->where('is_platform_super_admin', false)->select('id'))
            ->whereIn('role_id', DB::table('roles')->where('name', 'super_admin')->select('id'))
            ->delete();
    }

    public function down(): void
    {
        if (DB::table('roles')->whereNotNull('tenant_id')->exists()) {
            throw new RuntimeException('Tenant-specific roles exist; remove or migrate them before rolling back this migration.');
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('name');
        });
        Schema::table('tenant_user', fn (Blueprint $table) => $table->dropConstrainedForeignId('primary_branch_id'));
        Schema::table('user_branch_access', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
    }
};
