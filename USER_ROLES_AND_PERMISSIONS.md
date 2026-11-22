# Roles and permissions

Tenant membership roles are owner, administrator, staff, teacher, fee_staff, student, and parent. Memberships are stored in `tenant_user`, allowing one user to belong to multiple organizations and switch active tenant context. Tenant administrators may only manage memberships in the active tenant. The existing `super_admin` role is retained for compatibility; platform access should additionally require the explicit `users.is_platform_super_admin` flag and audited platform routes.

Roles are stored in `roles`, permissions in `permissions`, and assignments in `user_role` / `role_permission`. `EnsurePermission` and `User::hasPermission()` enforce authorization on the server. Branch access is checked by `User::canAccessBranch()` and should be used by every branch-scoped controller.

Seed roles: Super Admin, School/College Administrator, Teacher, Fee Collection Staff, Student, Parent. The seeded development administrator receives all current permissions. Student and parent portal controllers must use ownership/guardian queries before exposing records.

The settings permission protects `/admin/users`, `/admin/roles`, `/setup`, academic CRUD, fee structure CRUD, and workflow decision routes. User forms assign one role, a primary branch, and many permitted branches; controllers enforce permission server-side.

Password reset uses Laravel's database token broker. `UserPolicy`, `StudentPolicy`, `PaymentPolicy`, and `ExpensePolicy` provide model-level authorization in addition to route permission middleware.
