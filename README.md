# Investment Platform (PHP) — Deployment Guide

Public site + investor portal + full admin panel. PHP 7.4+ (8.x recommended), SQLite or MySQL. No Composer needed.

## Deploy in 5 steps
1. Upload everything in this folder to your hosting `public_html` (or a sub-folder).
2. Make sure `storage/` and `uploads/` are writable (chmod 755/775) and the project folder can create `config.local.php`.
3. Open `https://yourdomain.com/install.php`, choose SQLite or MySQL, set admin name/email/password.
4. Log in at `/admin/` → **Settings**: add logo, colours, bank/UPI details, SMTP email, payment gateway.
5. Delete `install.php`, enable the HTTPS redirect in `.htaccess`, and set **Mail mode = SMTP**.

Nginx: deny access to `/inc`, `/storage`, `config.local.php`, and disable PHP execution in `/uploads`.

## Database options
* **Easiest:** run `install.php` — it creates the tables and starter content for you (SQLite or MySQL).
* **Manual MySQL (phpMyAdmin):** create an empty database (utf8mb4) → Import `database.sql` →
  copy `config.sample.php` to `config.local.php` and fill in the DB details and a random `app_key` →
  log in at `/admin/` with ID `admin` and password `admin`. You are forced to set a new password (and ID) immediately.
  Do not run `install.php` after a manual import. Tables: users, plans, projects, investments, payouts, videos,
  documents, pages, items, messages, settings, login_attempts, audit.

## What the admin can change (no code)
Branding, colours, logo, hero text/image, section on/off, plans (ROI, bonus, tenure, payout frequency),
projects, videos (YouTube/Vimeo/MP4), PDFs, FAQ, timeline, testimonials, stats, legal pages, contact details,
payment methods, email, custom CSS/head code, maintenance mode. Also: verify payments, approve KYC,
mark payouts paid, export CSV, make other admins.

## Payments
* **Direct UPI / bank transfer** (default, no fees): investor pays, submits UTR → admin verifies → certificate emailed.
* **Razorpay** (UPI/cards/netbanking): needs a Razorpay merchant account (open-source SDKs; this app calls the REST API directly).
  Enter Key ID/Secret in Settings → Payments. Optional webhook: `/payment_webhook.php`, event `payment.captured`.
  Test with `rzp_test_` keys first.
* A "Demo" mode exists for showing the flow to the client. **Keep it OFF in production.**

## Flow
Sign up → choose plan → pay → (admin confirms or gateway confirms) → PDF certificate + transaction ID emailed →
investor submits PAN/Aadhaar/bank in dashboard → admin approves KYC → payout schedule tracked, admin marks each payout paid.

## Security built in
CSRF tokens, prepared statements, hashed passwords, login throttling, Aadhaar encrypted (AES-256-GCM, last 4 shown),
KYC files stored outside public access and served only to owner/admin, upload type checks, audit log.
**Back up `config.local.php` — its key is needed to decrypt Aadhaar numbers.** Back up the database and `uploads/` regularly.

## Before going live (important — legal)
Pooling money from the public for a real-estate project with promised returns is regulated in India
(SEBI collective-investment rules, RERA, Companies Act deposit rules, RBI). The client's legal/CA team must confirm
the structure and approve the Terms, Privacy and Risk pages (templates are placeholders), the returns wording,
and storing Aadhaar (UIDAI rules). Only display partner/brand names (e.g. a corporate partner) with written permission.
