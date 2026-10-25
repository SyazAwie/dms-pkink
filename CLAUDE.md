# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Sistem Arkib Digital (DMS) PKINK: a Laravel 13 / PHP 8.3 document-management system for Perbadanan Kemajuan Iktisad Negeri Kelantan. The code, identifiers, comments, UI text and commit messages are in **Bahasa Melayu**, so keep new code in that style (e.g. `paparScan`, `janaNoRujukan`, `kodPeranan`). Route names are mostly Malay (`dokumen.scan.papar`, `imbas-arkib.analisis`, `kelulusan.sokong`), but some are English (`login`, `logout`, `dashboard`, `password.*`, `users.*`, and the standard resource suffixes such as `.index`). Leave existing route names as they are, because views and redirects reference them. `README.md` is the detailed design doc (full DB schema, RBAC matrix, module flows, known limitations); check it before making large changes.

## Peraturan Projek

1. **Skema DB diurus manual dalam HeidiSQL.** Jangan sesekali cipta Laravel migration (`php artisan make:migration` atau fail baharu dalam `database/migrations/`). Jika jadual perlu diubah, beri skrip SQL MySQL untuk pengguna jalankan sendiri dalam HeidiSQL.
2. **Rujuk `PROGRESS.md`** di root projek untuk status projek dan nombor isu (K-xx, S-xx, dan nombor item dalam bahagian 4). Fail ini hanya wujud secara tempatan (ia diabaikan melalui `.git/info/exclude`), jadi jika ia tiada, beritahu pengguna dan jangan cipta semula tanpa diminta.
3. **Sebelum ubah kod, tanya soalan dan tunjuk pelan dahulu.** Tunggu persetujuan pengguna sebelum menyunting mana-mana fail kod.
4. **UI dan mesej dalam Bahasa Melayu.** Ini termasuk teks view, mesej flash, mesej validasi dan mesej ralat yang dipaparkan kepada pengguna.
5. **Selepas setiap kerja, kemas kini `PROGRESS.md`.** Pindahkan item yang siap ke bahagian 2, kemas kini status dalam bahagian 3/4, dan tambah commit baharu dalam bahagian 5.

## Commands

- Serve: Laravel Herd (nginx) on Windows, or `php artisan serve`. MySQL comes from XAMPP (MySQL only).
- Tests: `composer test` (clears config, then `php artisan test`). Single test: `php artisan test --filter=NamaTest`
- Format: `vendor/bin/pint` (add `--dirty` to format only changed files)
- After editing `.env`/config: `php artisan config:clear`
- Frontend: no build step is needed. `layouts/app.blade.php` loads Bootstrap 5.3, Bootstrap Icons and Chart.js from the jsDelivr CDN. The Vite/Tailwind scaffold in `package.json` is unused leftover code.

## Database: no Laravel migrations

The schema (`dms_pkink_db`, MySQL) is maintained **by hand in HeidiSQL** from an SQL dump plus numbered SQL scripts. `database/migrations/` contains only the stock Laravel files, and there is no `migrations` table. As a result:
- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe` or `db:seed` against the real database. All of them delete data. `dms_pkink_db` holds real records.
- Schema changes are SQL you give the user to run. Don't run `php artisan migrate` against the real DB.
- PHPUnit runs on in-memory SQLite (`phpunit.xml`), which has none of the domain tables. Only the example tests exist today, so a feature test that touches domain models needs its own schema setup first.
- Every table uses a non-standard PK (`user_id`, `dokumen_id`, `roles_id`, …). Every model sets `$primaryKey`, and every relationship must pass explicit foreign/owner keys.
- The `user_roles` pivot uses `user_id` + **`role_id`**, while the `roles` PK is `roles_id`. Mixing these up has caused bugs before.
- Uniqueness that survives soft deletes (email, ic_pekerja, kod_*) is enforced by DB generated columns. Keep validation rules consistent with that.

## Architecture

**EAV document model (the core concept).** `jenis_dokumen` (category) → `dokumen_field` (field definitions: `jenis_data` is teks/nombor/tarikh/dropdown/textarea, plus `pilihan` JSON, `is_required`, `susunan`) → `dokumen_data` (one row per `dokumen_id` + `dokumen_field_id`, value in `nilai_data` as text). Admins build categories in the UI (`jenis_dokumen/partials` holds the JS field builder). The upload form fetches the fields over AJAX from `jenis-dokumen.medan`. `DokumenController::peraturanMedan()` builds validation rules from the field definitions. Never add per-document-type columns or tables.

**RBAC: three layers that must stay in sync.** There are 8 roles (`kod_roles`): SUPERADMIN, ADMIN, KERANI, PENGURUS, STAFF, PENYOKONG, PELULUS, AUDIT. A user can hold several.
1. `peranan:` route middleware alias (`SemakPeranan`, registered in `bootstrap/app.php`) gates whole route groups.
2. `DokumenPolicy` (registered in `AppServiceProvider`) is called via `Gate::authorize()` inside the controllers. The `dokumen` resource routes have no role middleware.
3. `Dokumen::scopeBolehDilihat($user)` filters list queries. Its scope must match `DokumenPolicy::view` (PENGURUS sees their own bahagian, STAFF sees their own uploads, roles in `Dokumen::PERANAN_AKSES_SEMUA` see everything).
Role checks use `$user->hasRole()` / `hasAnyRole()`. Views also hide menu items and buttons by role. `UserController` blocks ADMIN from managing SUPERADMIN accounts and protects the last SUPERADMIN.

**Audit logging.** Observers for `JenisDokumen`, `Bahagian`, `User` and `Dokumen` are registered **only** in `AppServiceProvider::boot()`. Do not also add `#[ObservedBy]` on models, because double registration previously duplicated log rows. Changes that observers can't see (EAV values, scan files, `borang` approval steps, role assignment) are written with `LogAudit::create([...])` directly in the controllers. Follow that pattern for new non-model changes. `password`/`remember_token` must never reach `log_audit`. `log_audit` has no `updated_at`.

**Files and OCR/AI.** Scans are stored on the private `local` disk and served only through `dokumen.scan.papar`, which checks the policy first. Never use the `public` disk or `storage:link` URLs for scans.
- `OcrService` shells out with Symfony Process: `pdftoppm` (PDF→PNG at 300dpi), then `tesseract` with TSV output (text plus average confidence). Binary paths come from `config('services.ocr.*')` / `.env` `OCR_TESSERACT_BIN`, `OCR_PDFTOPPM_BIN`, set as full `.exe` paths because Herd doesn't see the Windows PATH.
- `OllamaExtractionService` sends the OCR text plus the category's EAV field list to a local Ollama (`OLLAMA_URL`, `OLLAMA_MODEL`, a SEA-LION model) and gets back JSON field suggestions. Prompts are built from the field definitions, so new categories work without code changes.
- When OCR runs: a normal upload (`DokumenController::store`/`update`) never runs OCR. Staff start it manually for each file with the "Jalankan OCR" button on the document page (`dokumen.scan.ocr` → `jalankanOcr`). Only Imbas & Arkib runs OCR automatically, inside `ImbasArkibController::analisis`. Don't add automatic OCR to the normal upload, because it would block the form.
- OCR runs synchronously in the request (no queue). OCR/AI failures are caught and recorded as a status (`Ralat`, …), and must never roll back the parent document.

**Main flows.**
- `DokumenController`: CRUD with multi-file upload, keyword search across EAV values and file names, and soft delete (files kept). `janaNoRujukan()` generates `{KOD}/{TAHUN}/{NNNN}` from the highest existing number, including trashed rows, with `lockForUpdate`, so references are never reused.
- `ImbasArkibController` **extends `DokumenController`** to reuse its helpers. Flow: upload one scan → OCR + AI (`analisis`; the temp file goes to `storage/app/private/ocr_sementara/`) → mandatory human review (`semak` view) → `simpan` creates the dokumen, data, scan, OCR rows and a `borang` already set to `Diluluskan`.
- `BorangController`: basic approval flow hantar → sokong (PENYOKONG) → lulus (PELULUS), or tolak at any stage. There is one `borang` per document, and resubmitting overwrites it.
- Relations to deleted parents (`jenisDokumen`, `bahagian`, `pemuatNaik`) use `->withTrashed()` so archived documents still show names.

## Laravel Boost

Laravel Boost TIDAK dipasang dan tidak perlu dipasang. Jangan jalankan `composer require laravel/boost` atau `php artisan boost:install`.
