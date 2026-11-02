# Sistem Arkib Digital (DMS) PKINK

**Sistem Pengurusan Dokumen Digital (Document Management System)**
Perbadanan Kemajuan Iktisad Negeri Kelantan

Sistem berasaskan web untuk mendigitalkan, mengarkib dan meluluskan dokumen serta borang PKINK. Sistem menyokong pengisian borang secara digital, pengimbasan borang kertas dengan pengecaman teks (OCR) dan pengisian medan secara automatik oleh kecerdasan buatan (AI) tempatan, aliran sokongan dan kelulusan, serta jejak audit penuh.

---

## Ciri utama

- **Kategori dokumen dinamik:** pentadbir mencipta kategori dan medan borang sendiri tanpa perubahan kod.
- **Isi Borang:** pemohon mengisi borang secara digital, atau mengimbas borang kertas yang telah diisi (Imbas & Hantar).
- **Imbas & Arkib:** dokumen fizikal sedia ada diimbas; OCR dan AI mengisi medan secara automatik untuk disemak oleh kakitangan.
- **Aliran kelulusan:** Hantar → Sokong → Lulus (atau Tolak), dengan delegasi semasa cuti, slip kelulusan berkod QR dan borang siap untuk dimuat turun.
- **Kawalan akses ikut peranan:** 8 peranan (SUPERADMIN, ADMIN, KERANI, PENGURUS, STAFF, PENYOKONG, PELULUS, AUDIT).
- **Keselamatan:** pengesahan dua faktor, imbasan antivirus setiap muat naik, sekatan IP automatik, butang kecemasan, sandaran disulitkan dan log audit.
- **AI tempatan:** semua pemprosesan OCR dan AI berlaku dalam pelayan PKINK; tiada data dihantar ke perkhidmatan luar.

## Teknologi

PHP 8.3 · Laravel 13 · MySQL 8 / MariaDB 10.4+ · Tesseract OCR · Poppler · Ollama (SEA-LION, GLM-OCR) · Bootstrap 5

## Dokumen

| Dokumen | Untuk |
|---|---|
| `docs/PANDUAN_PEMASANGAN.pdf` | Pentadbir pelayan: keperluan, pemasangan (Windows & Linux), sandaran, keselamatan, penyelesaian masalah |
| `docs/MANUAL_PENGGUNA_DMS_PKINK.pdf` | Semua pengguna: cara menggunakan sistem mengikut peranan |
| `docs/MANUAL_TEMPLAT_BORANG.pdf` | Pentadbir sistem: menyediakan templat borang kosong |
| `docs/PELAN_TINDAK_BALAS_INSIDEN.pdf` | Pengurusan & pentadbir: langkah ketika insiden keselamatan |
| `docs/DOKUMENTASI_TEKNIKAL.md` | Pembangun & pentadbir sistem: seni bina, pangkalan data, modul |
| `docs/erd/` | Rajah hubungan entiti (ERD) pangkalan data |

## Pemasangan ringkas

1. Sediakan pelayan mengikut keperluan dalam `docs/PANDUAN_PEMASANGAN.pdf` (bahagian 2).
2. Import `database/dms_pkink.sql` ke pangkalan data baharu.
3. Salin `.env.example` kepada `.env` dan lengkapkan tetapan; jalankan `php artisan key:generate` dan `php artisan webpush:vapid`.
4. Tetapkan dokumen root pelayan web kepada folder `public/`.
5. Pasang Tesseract, Poppler, Ollama dan model AI; daftarkan pekerja latar dan penjadual (`skrip/`).
6. Log masuk dengan akaun UAT (dokumen `AKAUN_UAT.pdf`, diserahkan berasingan) dan semak **Status Sistem**.

> Sistem ini **tidak** menggunakan *migration* Laravel. Arahan `php artisan migrate`, `migrate:fresh`, `db:wipe`, `db:seed` dan `storage:link` **tidak boleh dijalankan**.

## Hak milik

Dibangunkan untuk Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK). Penggunaan, pengubahsuaian dan pengedaran tertakluk kepada kebenaran PKINK.
