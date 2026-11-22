<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

## Multi-tenant SaaS

The application provisions an isolated tenant per school or degree college. Registration creates the organization, owner membership, trial subscription, and settings. Tenant switching, platform tenant administration, expiring invitations, tenant-scoped custom roles, and branch access are implemented. Existing data is preserved by additive migrations.

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# School & Degree College CRM/ERP

Laravel 13 multi-branch school and degree-college foundation with secure, additive migrations for academics, admissions, attendance, fees, expenses, audit logs, imports, notifications, and future biometric integration.

## Local setup

1. Use PHP 8.5 (the locked Laravel dependencies currently require PHP >= 8.4.1), Composer, MySQL, Node.js and npm.
2. Copy `.env.example` to `.env`, configure `DB_*`, then run `php artisan key:generate`.
3. Run `php artisan migrate` and `php artisan db:seed`.
4. Run `npm install` and `npm.cmd run build`.
5. Start with `php artisan serve`.

Development login: `admin@school.test` / `ChangeMe!2026` — replace immediately in a real environment.

See `PROJECT_PLAN.md`, `DATABASE_DESIGN.md`, `USER_ROLES_AND_PERMISSIONS.md`, `API_DOCUMENTATION.md`, `DEPLOYMENT.md`, and `TESTING.md`.

The current working surface includes admissions review and private documents, student/parent account linking, CSV/XLSX import preview and safe rollback, academic, fee, attendance, expense, approval, reconciliation, portal, report, library, transport, hostel, scholarship, exam, biometric, API, and PWA flows. Notifications use a queue with retries. Razorpay online subscription checkout is configurable; provider credentials are required. Twilio SMS/WhatsApp delivery requires account credentials and approved senders. See `PROJECT_PLAN.md` and `DEPLOYMENT.md` for the status and deployment requirements.
