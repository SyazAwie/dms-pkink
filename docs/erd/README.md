# ERD Sistem Arkib Digital (DMS) PKINK

Dijana terus daripada skema sebenar `dms_pkink_db` (INFORMATION_SCHEMA) pada 2026-11-02: **22 jadual domain, 223 lajur, 35 kunci asing** + 4 hubungan logik tanpa FK. Jadual infrastruktur Laravel tanpa hubungan (`cache`, `cache_locks`, `jobs`, `failed_jobs`, `password_reset_tokens`) tidak dilukis.

| Fail | Guna |
|---|---|
| `ERD_DMS_PKINK.dbml` | **Disyorkan.** Tampal ke https://dbdiagram.io/d → semua jadual, PK/FK dan garis dilukis automatik → Export PNG/PDF. Disahkan dengan pengurai rasmi `@dbml/core`. |
| `ERD_DMS_PKINK.drawio` | Buka dalam draw.io (File → Open from → Device). Lengkap dengan garis crow's foot pada lajur, 7 kumpulan berwarna dan legenda; garis bersilang perlu dikemaskan. |
| `ERD_DMS_PKINK_drawio.sql` | DDL ringkas untuk draw.io Arrange → Insert → Advanced → SQL (kotak jadual sahaja, garis dilukis sendiri ikut `HUBUNGAN_35.txt`). |
| `HUBUNGAN_35.txt` | Senarai semak 35 hubungan FK + kardinaliti + hubungan logik. |
| `PROMPT_ERD_GEMINI.md` | Prompt lengkap (skema dibenamkan) untuk menjana ERD dengan Gemini (Mermaid + DBML). |

## Jana semula selepas skema berubah
Skrip membaca DB sebenar sahaja (BACA, tiada perubahan). Jalankan dari akar projek:

```
php docs/erd/skrip/jana_dbml.php . docs/erd/ERD_DMS_PKINK.dbml
php docs/erd/skrip/jana_drawio.php . docs/erd/ERD_DMS_PKINK.drawio
php docs/erd/skrip/jana_sql_drawio.php . docs/erd/ERD_DMS_PKINK_drawio.sql
```
(Laluan output diberi sebagai argumen, bukan `>`: dalam PowerShell 5.1, `>` menulis UTF-16.)

Jika jadual domain baharu ditambah, kemas kini senarai kumpulan/susunan dalam setiap skrip (skrip `jana_sql_drawio.php` akan berhenti dengan ralat jika senarai tidak sepadan).

Nota reka bentuk penting: model EAV (`jenis_dokumen` → `dokumen_field` → `dokumen_data`), `user_roles.role_id` → `roles.roles_id`, satu `borang` bagi setiap `dokumen` (dikuatkuasa aplikasi), `log_audit (nama_jadual, rekod_id)` polimorfik.
