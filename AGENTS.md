# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

SmartBookers is a Hungarian-language appointment booking web application for service providers (beauty, massage, hairdressing, nail art, mental health). It runs on XAMPP (Apache + MariaDB + PHP 8.x) with no framework — plain PHP with mysqli/PDO, vanilla JS, and custom CSS.

## Environment Setup

- **Stack**: XAMPP on Windows — Apache serves from `C:\xampp\htdocs\Smartbookers`
- **Database**: MariaDB, database name `idopont_foglalas`
- **DB credentials**: root / no password on localhost (development only)
- **PHP version**: 8.1+ (uses PDO with named params, null coalescing, match expressions)
- **No build step, no package manager, no composer** — PHPMailer is vendored in `config/PHPMailer/`
- **Base URL path**: All internal links use `/Smartbookers/` prefix (hardcoded, not configurable)

### Running the App

1. Start XAMPP (Apache + MySQL)
2. Import `idopont_foglalas.sql` into phpMyAdmin, then run `migrate_roles_messages.sql` if upgrading from the original schema (see Schema Migration below)
3. Browse to `http://localhost/Smartbookers/public/index.php`

There are no automated tests, no linter, and no CI/CD pipeline.

## Architecture

### Role System

Three roles stored directly in `users.role` as an ENUM(`user`, `provider`, `admin`). Role value is stored in `$_SESSION['role']` after login. Each role has its own login page and dashboard area:

- **User** (customer): logs in via `public/login.php` → redirected to `user/profile.php`
- **Provider** (business): logs in via `business/provider_login.php` → redirected to `business/provider_place.php`
- **Admin**: logs in via `admin/adminlogin.php` → redirected to `admin/dashboard.php`

### Directory Layout by Role

- `public/` — guest-facing pages: index, login/register (user only), industry search, contact, pricing, logout
- `user/` — authenticated user pages: dashboard (view bookings + free slots), booking flow, cancellation, profile, messages
- `business/` — authenticated provider pages: dashboard/registration, provider_place (availability management), appointment list, profile, messages, cancellation acknowledgment
- `admin/` — admin panel: dashboard (stats), users, providers, bookings, services, industries management
- `api/` — JSON endpoints for the chat system (AJAX-polled, not WebSocket)
- `chat/` — standalone chat page (`chat.php`), now superseded by the header-embedded chat

### Shared Includes

- `includes/header.php` — starts session, resolves user avatar from DB, renders full `<head>` and navigation bar with role-aware links. **Every non-admin page includes this first.**
- `includes/footer.php` — closes the HTML body with footer markup
- `includes/mail_config.php` — returns SMTP config array for PHPMailer (used by `public/send_contact.php`)
- `config/db.php` — mysqli connection to `smartbookers` database (different DB name — see below)
- `admin/admin_sidebar.php` — admin auth guard + shared sidebar layout. **Every admin page includes this first** (instead of `includes/header.php`)
- `admin/admin_footer.php` — closes admin layout

### Database Connection Inconsistency

`config/db.php` creates a `mysqli` connection to database `smartbookers`, but all active page-level PHP files create their own inline `PDO` or `mysqli` connection to database `idopont_foglalas`. **Always use `idopont_foglalas` and an inline connection for new pages.** `config/db.php` is effectively dead code — only `user/foglalas.php` (a legacy stub) includes it.

### Schema Migration

`migrate_roles_messages.sql` documents a significant migration from the original schema:

- **Before**: `users.role_id` FK → separate `roles` table; `messages` had `sender_role ENUM`, `sender_user_id`, `sender_provider_id`, `booking_id`, `type ENUM` columns
- **After**: `users.role ENUM('user','provider','admin')` directly on the users table (no `roles` table); `messages.by_provider TINYINT(1)` replaces the old sender columns

All current code assumes the **post-migration schema**. `dbdiagramm.txt` reflects the pre-migration schema and is partially outdated.

### Key Database Tables

- `users` — all accounts (users, providers, admins) with `role ENUM` column
- `providers` — business profile linked to a user via `user_id`, references `services`, `sub_services`, `telepulesek` (towns), `industries`
- `services` / `sub_services` — two-level service taxonomy (e.g., "Kozmetika" → specific treatments)
- `industries` — slug-based categories used for the public search/browse pages
- `provider_availability` — time slots a provider publishes (date, start/end time, slot duration, sub-service)
- `bookings` — links a user to a provider availability slot; tracks cancellation via `cancelled_at`; `provider_seen` flag for unread cancellation notifications
- `conversations` — 1:1 chat between user and provider; unique constraint on (user_id, provider_id)
- `messages` — chat messages with `by_provider TINYINT`, `seen_by_user`, `seen_by_provider` columns
- `telepulesek` — Hungarian towns with zip code

### Booking Flows

**Industry-search flow** (primary):
1. User browses `public/industry.php?slug=<industry>` to see providers with available slots
2. User clicks "Foglalás" → `user/book.php?availability_id=<id>`
3. `book.php` validates the slot (active, future, not already booked), inserts into `bookings`, upserts a `conversations` row, posts an automated message, all in a transaction
4. Redirects to `user/dashboard.php` on success

**QR-code flow** (in development):
- `qr_provider.php?provider=<id>` generates a QR code (via `libs/phpqrcode/qrlib.php`) pointing to `user/book_provider.php?provider_id=<id>`
- `user/book_provider.php` handles unauthenticated users by saving `$_SESSION['book_provider']` and redirecting to login, then lists available slots for the provider
- This page still contains debug `print_r()` output and a hardcoded `$providerId = 2` — it is not yet production-ready

### Chat System

The primary chat is the **header-embedded panel**, built into `includes/header.php` and driven by `js/header.js`. It polls three JSON API endpoints:
- `api/chat_threads.php` — lists conversations for the logged-in user/provider with unread counts
- `api/chat_messages.php` — fetches all messages for a conversation + marks them as seen (includes avatar URLs)
- `api/chat_send.php` — sends a new message; uses `by_provider` field

A secondary set (`api/get_messages.php`, `api/send_message.php`) does the same with a slightly different response shape; these are not called by any current UI and appear to be an older version.

`js/chat.js` and `chat/chat.php` are a legacy standalone chat page; `chat.js` calls `chat/send_messages.php` and `chat/get_messages.php` which do not exist — this flow is broken and superseded by the header panel.

`user/uzenetek.php` and `business/uzenetek.php` are placeholder stubs (only `include '../includes/header.php'`).

### Admin System

Admin pages use `admin/admin_sidebar.php` as the entry point (auth guard + layout wrapper). It checks `$_SESSION['role'] === 'admin'` and redirects to `admin/adminlogin.php` if not authenticated. All admin pages use an inline PDO connection to `idopont_foglalas`. To create an initial admin account, run `admin/seed_admin.sql` (creates `admin1@admin.hu` / `admin123`).

### Static Assets

- `public/css/` — per-page CSS files, plus `admin.css` for the admin panel (no preprocessor, no shared utility framework)
- `public/images/` and `public/img/` — avatars and provider images
- `js/` — `index.js` (homepage animations), `header.js` (nav + embedded chat), `chat.js` (legacy, broken)
- Default avatar: `/Smartbookers/public/images/avatars/a1.png`

## Conventions

- **Language**: All user-facing strings are in Hungarian. Keep this consistent.
- **URLs**: All internal links are hardcoded with `/Smartbookers/` prefix.
- **Auth guards**: Each protected page manually checks `$_SESSION['user_id']` and `$_SESSION['role']` at the top. There is no middleware or centralized auth (except `admin_sidebar.php` for the admin area).
- **DB access**: Pages create their own `mysqli` or `PDO` connections inline to `idopont_foglalas`. There is no ORM or query builder.
- **No CSRF protection** is implemented on forms.
- **Password hashing**: Uses `password_hash()` / `password_verify()` (bcrypt).
- **Provider registration** enforces password complexity: min 6 chars, 1 uppercase, 1 number, 1 special character. User registration only requires min 6 chars.
- `seen_cancellations.php` is an action-only endpoint (no HTML output) — it marks bookings as `provider_seen=1` and redirects back to `provider_place.php`.
