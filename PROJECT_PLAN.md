# School ERP delivery status

Last reconciled: 2026-11-10

## Implemented application areas

- Tenant registration, approval, tenant switching, subscription trials and renewals, plan limits, platform tenant controls, tenant roles, and branch grants.
- User administration, tenant invitations, custom roles and permissions, student and guardian portal linking, and tenant-scoped notification broadcasts.
- Academic years and structures, admissions review, student enrollment and transfers, attendance, exams, results, and timetable.
- Fee plans, student fee assignments and installments, payment collection, receipts, adjustments, reconciliation, expenses, and approvals.
- Student/parent portals, published announcements with per-user read status, library, transport, hostel, scholarships, reports/exports, read-only API tokens, biometric sync, and PWA support.
- In-app notification inbox and broadcast delivery/read history; scheduled fee due reminders; tenant-configurable event templates; queued email/SMS/WhatsApp delivery.
- Platform-only provider configuration for SMTP, Twilio, and Razorpay with encrypted credentials and SMTP test email.
- Optional Razorpay subscription checkout with signature and captured amount/currency/order validation; manual payment references remain available for platform review.

## Remaining deployment and verification work

- Back up the production database, apply pending migrations, and verify `php artisan migrate:status` on the deployment host.
- Apply the platform settings migration before using **Platform settings**.
- Configure production `.env`, secure `APP_KEY`, `APP_DEBUG=false`, HTTPS, mail transport, queue worker, storage permissions, and database backups.
- Run Laravel's scheduler every minute. It runs subscription expiry at 00:05 and fee due reminders at 08:00.
- Add and verify Razorpay, Twilio, and biometric hardware credentials/configuration where those services are needed. Test with provider sandbox or test devices before production use.
- Build frontend assets in the deployment pipeline and verify backup/restore and provider delivery against their real production environments.
- Run the repository's automated feature suite and smoke-test tenant, branch, fee, attendance, message-read, and notification-delivery flows in a configured development database.

Direct user-to-user chat is not part of the current messaging design; Messages are institution announcements.
