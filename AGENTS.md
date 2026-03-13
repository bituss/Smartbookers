# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

SmartBookers is a Hungarian-language appointment booking web application for service providers (beauty, massage, hairdressing, nail art, mental health). It runs on XAMPP (Apache + MariaDB + PHP 8.x) with no framework — plain PHP with mysqli/PDO, vanilla JS, and custom CSS.

## Environment Setup

- **Stack**: XAMPP on Windows — Apache serves from `C:\xampp\htdocs\Smartbookers`
- **Database**: MariaDB, database name `idopont_foglalas` (not `smartbookers` — `config/db.php` says `smartbookers` but all page-level code connects to `idopont_foglalas`)
- **DB credentials**: root / no password on localhost (development only)
- **PHP version**: 8.1+ (uses PDO with named params, null coalescing, match expressions)
- **No build step, no package manager, no composer** — PHPMailer is vendored in `includes/PHPMailer/`
- **Base URL path**: All internal links use `/Smartbookers/` prefix (hardcoded, not configurable)

### Running the App

1. Start XAMPP (Apache + MySQL)
2. Import `idopont_foglalas.sql` (or the newer `uj.txt` schema) into phpMyAdmin
3. Browse to `http://localhost/Smartbookers/public/index.php`

There are no automated tests, no linter, and no CI/CD pipeline.

## Architecture

### Role System

Three roles stored in the `roles` table: **user**, **provider**, **admin**. Role name is stored in `$_SESSION['role']` after login. Each role has its own login page and dashboard area:

- **User** (customer): logs in via `public/login.php` → redirected to `user/profile.php`
- **Provider** (business): logs in via `business/provider_login.php` → redirected to `business/provider_place.php`
- **Admin**: `admin/` directory (mostly scaffolded, `adminlogin.php` is empty)

### Directory Layout by Role

- `public/` — guest-facing pages: index, login/register (user only), industry search, contact, pricing, logout
- `user/` — authenticated user pages: dashboard (view bookings + free slots), booking flow, cancellation, profile, messages
- `business/` — authenticated provider pages: dashboard/registration, provider_place (availability management), appointment list, profile, messages, cancellation acknowledgment
- `admin/` — admin pages (bookings, users, dashboard — largely incomplete)
- `api/` — JSON endpoints for the real-time chat system (AJAX-polled, not WebSocket)
- `chat/` — chat UI page (`chat.php`)

### Shared Includes

- `includes/header.php` — starts session, resolves user avatar from DB, renders full `<head>` and navigation bar with role-aware links. **Every page includes this first.**
- `includes/footer.php` — closes the HTML body with footer markup
- `includes/mail_config.php` — returns SMTP config array for PHPMailer (used by `public/send_contact.php`)
- `config/db.php` — mysqli connection (note: some pages use their own PDO connection instead of this include)

### Database Connection Inconsistency

**Important**: `config/db.php` creates a `mysqli` connection to database `smartbookers`, but nearly all page-level PHP files create their own `PDO` connection to database `idopont_foglalas`. These are two different connection patterns. When adding new pages, follow the PDO pattern used in existing pages (e.g., `user/book.php`, `business/provider_place.php`).

### Key Database Tables

- `users` — all accounts (users, providers, admins) with `role_id` FK to `roles`
- `providers` — business profile linked to a user via `user_id`, references `services`, `sub_services`, `telepulesek` (towns), `industries`
- `services` / `sub_services` — two-level service taxonomy (e.g., "Kozmetika" → specific treatments)
- `industries` — slug-based categories used for the public search/browse pages
- `provider_availability` — time slots a provider publishes (date, start/end time, slot duration, sub-service)
- `bookings` — links a user to a provider availability slot; tracks cancellation via `cancelled_at`
- `conversations` / `messages` — 1:1 chat between user and provider; unique constraint on (user_id, provider_id)
- `telepulesek` — Hungarian towns with zip code

Full schema is documented in `dbdiagramm.txt` (dbdiagram.io format).

### Booking Flow

1. User browses `public/industry.php?slug=<industry>` to see providers with available slots
2. User clicks "Foglalás" → `user/book.php?availability_id=<id>`
3. `book.php` validates the slot (active, future, not already booked), inserts into `bookings`, upserts a `conversations` row, posts an automated "booking_auto" message, all in a transaction
4. Redirects to `user/dashboard.php` on success

### Chat System

AJAX-based polling (not WebSocket). Chat UI in `chat/chat.php`, JS logic in `js/chat.js`.
- `api/chat_threads.php` — lists conversations for the logged-in user/provider
- `api/chat_messages.php` — fetches messages for a conversation + marks them as seen
- `api/chat_send.php` — sends a new message
- Messages have `sender_role` (user/provider/system) and `type` (text/booking_auto)

### Static Assets

- `public/css/` — per-page CSS files (no preprocessor, no shared utility framework)
- `public/images/` and `public/img/` — avatars and provider images
- `js/` — `index.js` (homepage), `header.js` (nav), `chat.js` (chat polling)
- Default avatar: `/Smartbookers/public/images/avatars/a1.png`

## Conventions

- **Language**: All user-facing strings are in Hungarian. Keep this consistent.
- **URLs**: All internal links are hardcoded with `/Smartbookers/` prefix.
- **Auth guards**: Each protected page manually checks `$_SESSION['user_id']` and/or `$_SESSION['role']` at the top. There is no middleware or centralized auth.
- **DB access**: Pages create their own PDO connections inline. There is no ORM or query builder.
- **No CSRF protection** is implemented on forms.
- **Password hashing**: Uses `password_hash()` / `password_verify()` (bcrypt).
- **Provider registration** enforces password complexity: min 6 chars, 1 uppercase, 1 number, 1 special character. User registration only requires min 6 chars.
