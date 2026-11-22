# Database design

## Tenant boundary

`tenants` is the organization boundary. `subscription_plans`, `subscriptions`, `tenant_user`, `tenant_invitations`, and `tenant_settings` hold platform and membership state. Tenant-owned operational tables have an additive nullable `tenant_id` during migration/backfill and are populated from branch/student/payment relationships before the default tenant is assigned. New writes derive tenant_id from `TenantContext`; submitted tenant IDs are not trusted.

`ResolveTenant` selects an active membership, blocks suspended tenants from protected routes, and supports a session tenant switcher. `BelongsToTenant` adds a global scope and automatically stamps new records. Platform-level super-admin is an explicit user flag and must not be inferred from a tenant role.

The database is normalized around `branches`, `academic_years`, and historical `student_enrollments`. Students are never removed for normal lifecycle events; status and enrollment history preserve transfers, graduation, and withdrawal.

Operational ledgers use immutable identifiers and status transitions: `payments` → `payment_allocations` → `receipts`; `refunds` and receipt cancellation provide corrections without rewriting history. Attendance is unique by student/date/session. Sensitive actions are recorded in `audit_logs`.

The additive operational migration is `2026_10_28_180000_create_platform_operations_tables.php`.

Portal and finance extensions are in `2026_10_28_190000_create_portal_finance_extensions.php`, covering financial years, collection counters, student/guardian account links, timetables, results, notification templates, and opening balances.

Approval and reconciliation extensions are in `2026_10_28_200000_create_approval_reconciliation_tables.php`. They preserve source records and store pending/approved/rejected decisions for payment corrections, refunds, receipt cancellations, attendance/expense workflows, cashier closings, and payment reconciliation.
