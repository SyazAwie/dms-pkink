# Rajah Hubungan Entiti (ERD): Sistem Arkib Digital (DMS) PKINK

Rajah ini dijana terus daripada skema pangkalan data sistem (INFORMATION_SCHEMA): **22 jadual domain, 222 lajur dan 35 kunci asing**, serta 4 hubungan logik tanpa kunci asing. Jadual infrastruktur Laravel yang tidak mempunyai hubungan (`cache`, `cache_locks`, `jobs`, `failed_jobs`, `password_reset_tokens`) tidak dilukis.

| Fail | Kegunaan |
|---|---|
| `ERD_DMS_PKINK.dbml` | **Disyorkan.** Tampal kandungan fail ke https://dbdiagram.io/d. Semua jadual, kunci primer, kunci asing dan garis hubungan dilukis secara automatik, dan rajah boleh dieksport sebagai PNG atau PDF. |
| `ERD_DMS_PKINK.drawio` | Buka dalam draw.io (*File → Open from → Device*). Lengkap dengan garis *crow's foot* pada lajur, 7 kumpulan berwarna dan petunjuk. Garis yang bersilang boleh dikemaskan secara manual. |
| `ERD_DMS_PKINK_drawio.sql` | DDL ringkas untuk pengimport SQL draw.io (*Arrange → Insert → Advanced → SQL*): kotak jadual sahaja; garis hubungan dilukis berpandukan `HUBUNGAN_35.txt`. |
| `HUBUNGAN_35.txt` | Senarai 35 hubungan kunci asing beserta kardinaliti dan hubungan logik. |

## Nota reka bentuk

- **Model EAV:** `jenis_dokumen` (kategori) → `dokumen_field` (definisi medan) → `dokumen_data` (satu baris bagi setiap dokumen dan medan). Kategori dan medan baharu tidak memerlukan perubahan skema.
- **Peranan:** jadual perantara `user_roles` menggunakan `user_id` dan `role_id`, manakala kunci primer jadual `roles` ialah `roles_id`.
- **Kelulusan:** satu rekod `borang` bagi setiap `dokumen` (dikuatkuasakan oleh aplikasi). Status sebenar dokumen ialah `borang.status_permohonan`.
- **Log audit:** `log_audit (nama_jadual, rekod_id)` ialah rujukan polimorfik kepada rekod dalam mana-mana jadual.
- **Keunikan selepas padam lembut:** keunikan emel, No. KP dan kod dikuatkuasakan melalui lajur terjana (*generated columns*).
