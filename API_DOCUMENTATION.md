# API and service boundaries

The current delivery is Blade-first. Transactional HTTP endpoints include `POST /attendance`, `POST /payments`, `GET /payments/{payment}/receipt`, `POST /expenses`, and student admission routes. All write requests use Form Requests, database transactions, permission middleware, and session CSRF protection.

## Authenticated REST API

Create a read-only token from **Administration → Mobile & API access**. Tokens are shown once, stored as hashes, expire, and can be revoked. Send `Authorization: Bearer <token>`.

- `GET /api/v1/me`
- `GET /api/v1/students?per_page=25`
- `GET /api/v1/students/{id}`
- `GET /api/v1/students/{id}/attendance`
- `GET /api/v1/students/{id}/fees`
- `GET /api/v1/students/{id}/results`
- `GET /api/v1/students/{id}/timetable`
- `GET /api/v1/notices`

Responses are JSON, tenant-scoped, branch-scoped, read-only, and also require the token owner's relevant application permission.

## Biometric device push

Register a device from **Administration → Biometric devices**, map its person IDs to students or staff, and copy the one-time device token. Devices POST batches to `/api/v1/biometric/events` with `X-Device-Token` and an `events` array containing unique `event_id`, mapped `person_id`, and ISO `occurred_at`. Duplicate event IDs are idempotent. Student scans create present attendance; staff scans create staff attendance; unmapped and failed events remain in the sync log. Devices can be disabled from administration.

## Notifications and messages

Use **Administration → Send notification** for an in-app broadcast to all accessible tenant users or selected users. Its broadcast history shows recipient and read counts. Use **Administration → Announcements** for messages; published announcements appear in the Messages inbox and linked student/parent portals, and users can mark each message as read.

Configure event templates in **Administration → Notification templates**. `attendance.absent`, `payment.received`, and scheduled `fee.due_reminder` events can use in-app, email, SMS, or WhatsApp. Fee reminders run daily for unpaid items due within seven days and are sent at most once per fee item to each linked student or guardian account. Twilio settings use `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_SMS_FROM`, and `TWILIO_WHATSAPP_FROM`. Store recipient phone numbers in E.164 format. Provider credentials and an approved sender are required before SMS/WhatsApp will deliver.

## Android installation

The authenticated web app is installable as an Android PWA. It does not cache private pages or API data offline. Native Android source code is not part of this Laravel repository; mobile clients can use the read-only API above.
