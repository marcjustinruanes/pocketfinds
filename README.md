# PocketFinds — Team Setup

Everyone on the team shares the **same remote Supabase database and the same
Supabase Storage buckets** — there is no local database or local file
storage. That means once your `.env` is set up correctly, every image
anyone uploads (product photos, chat attachments, profile pictures) is
immediately visible to everyone else, on any machine, with no extra steps.

## Getting set up

1. **Clone the repo, then copy the env file:**
   ```bash
   cp .env.example .env
   ```
   `.env.example` already contains the real shared credentials (DB, Supabase
   Storage, mail, Google OAuth) for this project — do **not** regenerate
   `APP_KEY` or edit the DB/AWS/SUPABASE values, or your machine will be
   using a different database/storage than everyone else's.

2. **Install dependencies:**
   ```bash
   composer install
   npm install   # if you touch any frontend build step
   ```

3. **Windows only — fix the "SSL certificate" / cURL error 60 upload crash.**
   Windows PHP builds don't ship a CA certificate bundle, so any outbound
   HTTPS call from PHP (including every image upload to Supabase Storage)
   fails with:
   ```
   cURL error 60: SSL certificate ... unable to get local issuer certificate
   ```
   Fix it once, on your own machine (this is a PHP install setting, not a
   project file, so it can't be fixed by pulling from git):
   - Download https://curl.se/ca/cacert.pem and save it somewhere permanent,
     e.g. next to your `php.exe` (find it with `where php`).
   - Open your `php.ini` (`php --ini` shows its path) and add:
     ```ini
     [curl]
     curl.cainfo = "C:\path\to\cacert.pem"

     [openssl]
     openssl.cafile = "C:\path\to\cacert.pem"
     ```
   - **Restart `php artisan serve`** (or your web server) — `php.ini` is only
     read when the PHP process starts, so an already-running server won't
     pick up the change until you stop and start it again.

4. **Run the app:**
   ```bash
   php artisan serve
   ```

If a teammate's uploaded image ever doesn't show up for you, it's almost
always one of: (a) they're still on step 3 above and the upload silently
went to their own machine instead of Supabase, or (b) their `.env` has
drifted from `.env.example` (compare `FILESYSTEM_DISK`, `AWS_*`, and
`SUPABASE_*` — they must match exactly, since those are what point every
machine at the same storage).

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

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
