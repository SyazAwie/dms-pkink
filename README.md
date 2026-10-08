# Sistem Arkib Digital (DMS) PKINK

**Nama Penuh:** Sistem Pengurusan Dokumen Digital (Document Management System) Perbadanan Kemajuan Iktisad Negeri Kelantan
**Jenis Projek:** Aplikasi Web Latihan Industri (Sains Komputer)
**Status:** Dalam pembangunan aktif — modul teras berfungsi, digunakan rintis oleh Bahagian Digital

---

## Senarai Kandungan

1. [Pengenalan & Objektif Projek](#1-pengenalan--objektif-projek)
2. [Spesifikasi Teknikal & Keperluan Sistem](#2-spesifikasi-teknikal--keperluan-sistem)
3. [Senibina Sistem](#3-senibina-sistem)
4. [Struktur Pangkalan Data Penuh](#4-struktur-pangkalan-data-penuh)
5. [Modul-Modul Sistem (Terperinci)](#5-modul-modul-sistem-terperinci)
6. [Kawalan Akses Ikut Peranan (RBAC)](#6-kawalan-akses-ikut-peranan-rbac)
7. [Keselamatan Sistem](#7-keselamatan-sistem)
8. [Carta Alir Pengguna](#8-carta-alir-pengguna)
9. [Peta Fail → Lokasi Dalam Projek](#9-peta-fail--lokasi-dalam-projek)
10. [Panduan Pasang Sistem Dari Kosong](#10-panduan-pasang-sistem-dari-kosong)
11. [Isu Yang Telah Dibaiki Sepanjang Pembangunan](#11-isu-yang-telah-dibaiki-sepanjang-pembangunan)
12. [Had Sistem & Kerja Akan Datang](#12-had-sistem--kerja-akan-datang)
13. [Akaun Ujian](#13-akaun-ujian)
14. [Glosari Istilah](#14-glosari-istilah)

---

## 1. Pengenalan & Objektif Projek

Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK) menguruskan dokumen rasmi (invois, surat rasmi, borang permohonan, minit mesyuarat, dan sebagainya) secara fizikal merentas pelbagai bahagian. Proses ini membawa beberapa masalah:

- Dokumen fizikal mudah hilang, rosak, atau sukar dicari semula
- Tiada jejak audit yang jelas — sukar tahu siapa buat apa, bila
- Setiap bahagian guna format berbeza untuk jenis dokumen berbeza, menyukarkan penyeragaman
- Proses kelulusan dokumen (sokongan → kelulusan) tidak direkod secara digital

**Objektif Sistem Arkib Digital PKINK:**

1. Digitalkan penyimpanan dokumen dengan struktur yang **fleksibel** — setiap jenis dokumen boleh ada medan maklumat berbeza, ditentukan oleh pentadbir sendiri tanpa perlu kemahiran pengaturcaraan
2. Sediakan kawalan akses yang jelas ikut peranan kakitangan (siapa boleh buat apa)
3. Rekodkan setiap tindakan dalam sistem untuk tujuan audit dan akauntabiliti
4. Sediakan aliran kerja kelulusan digital (Penyokong → Pelulus)
5. Permudahkan proses digitalkan dokumen **lama** yang sedia ada melalui teknologi OCR (pengecaman teks) dan AI (pengekstrakan data automatik)

**Fasa Semasa:** Sistem sedang digunakan secara rintis oleh **Bahagian Digital PKINK** untuk mengimbas dan mengarkibkan dokumen fizikal sedia ada ke bentuk digital. Penggunaan menyeluruh merentas semua bahagian PKINK dirancang untuk fasa akan datang, dan beberapa modul (seperti aliran kelulusan penuh) sengaja dibina pada tahap asas buat masa ini — lihat [Bahagian 12](#12-had-sistem--kerja-akan-datang).

---

## 2. Spesifikasi Teknikal & Keperluan Sistem

### 2.1 Tech Stack Utama

| Komponen | Versi / Teknologi | Catatan |
|---|---|---|
| **Bahasa Pengaturcaraan** | PHP 8.3.33 | |
| **Rangka Kerja (Framework)** | Laravel 13.32.0 | Rangka kerja MVC |
| **Pengurus Pakej PHP** | Composer | Untuk urus kebergantungan (dependencies) Laravel |
| **Pangkalan Data** | MySQL / MariaDB 10.4.32 | Enjin storan InnoDB, aksara `utf8mb4` |
| **Pelayan Web (Local Dev)** | Laravel Herd (nginx + PHP-FPM) | Persekitaran pembangunan tempatan di Windows |
| **Pelayan Pangkalan Data (Local Dev)** | XAMPP — **MySQL sahaja** | Apache/PHP XAMPP TIDAK digunakan; Herd kendalikan PHP |
| **Pengurus Pangkalan Data** | HeidiSQL | Skema diuruskan manual (tiada jadual `migrations` Laravel) |
| **Frontend** | Blade Templating Engine | Templat terbina-dalam Laravel |
| **Rangka Kerja CSS** | Bootstrap 5.3.x | |
| **Ikon** | Bootstrap Icons 1.11.x | |
| **Carta/Graf** | Chart.js 4.4.4 | Dimuat melalui CDN (jsdelivr.net) |
| **Reka Bentuk UI** | Glassmorphism (custom) | Panel lut sinar, kecerunan warna korporat biru-hijau |
| **Model Data Dinamik** | Entity-Attribute-Value (EAV) | Lihat Bahagian 3.2 |

### 2.2 Keperluan Perisian Luaran (Dipasang Berasingan)

Selain Laravel/PHP, beberapa ciri sistem memerlukan perisian sistem tambahan dipasang **terus pada pelayan/komputer** (bukan melalui Composer):

| Perisian | Tujuan | Sumber Pemasangan |
|---|---|---|
| **Tesseract OCR** | Pengecaman teks (OCR) daripada imej/PDF imbasan | UB-Mannheim build (Windows) — pek bahasa Malay + English |
| **Poppler (pdftoppm)** | Tukar muka surat PDF kepada imej sebelum OCR | poppler-windows releases (GitHub) |
| **Ollama** | Jalankan model AI (LLM) secara tempatan untuk ekstrak data daripada teks OCR | ollama.com/download |
| **Model AI: SEA-LION** | Model bahasa (LLM) 8 bilion parameter, dilatih khusus untuk bahasa Asia Tenggara termasuk Bahasa Melayu | aisingapore/Llama-SEA-LION-v3.5-8B-R (melalui Ollama) |

### 2.3 Keperluan Perkakasan (Disyorkan)

| Komponen | Minimum | Disyorkan (untuk OCR + AI lancar) |
|---|---|---|
| RAM | 8GB | 16GB+ |
| Storan | 10GB ruang kosong | 20GB+ (model AI ~5GB, pangkalan data, fail imbasan) |
| GPU | Tidak wajib | Kad grafik NVIDIA (CUDA) — model AI jalan jauh lebih laju berbanding CPU sahaja |
| Sistem Operasi | Windows 10/11 (persekitaran pembangunan semasa) | — |

### 2.4 Kebergantungan PHP (Composer) Utama

- `laravel/framework` ^13.0
- Pakej teras Laravel: Eloquent ORM, Blade, Validation, Eloquent Soft Deletes
- Guna **Symfony Process** (terbina dalam Laravel) untuk panggil CLI Tesseract/Poppler — **tiada** pustaka OCR pihak ketiga tambahan diperlukan
- Guna **Laravel HTTP Client** (berasaskan Guzzle, terbina dalam Laravel) untuk panggil API tempatan Ollama — **tiada** SDK AI pihak ketiga diperlukan

Reka bentuk ini sengaja **meminimumkan kebergantungan pihak ketiga** — kedua-dua integrasi OCR dan AI dibina terus di atas keupayaan sedia ada Laravel (Process + HTTP Client), bukan pustaka luar yang menambah risiko keserasian versi.

### 2.5 Nota Persekitaran Pembangunan

Projek ini dibangunkan menggunakan **Laravel Herd** (bukan XAMPP Apache) untuk menjalankan PHP — ini penting diketahui kerana:

- Herd guna **nginx**, bukan Apache — arahan/konfigurasi khusus Apache (`.htaccess` tertentu, modul Apache) tidak relevan
- Herd ada `php.ini` tersendiri, berasingan daripada mana-mana `php.ini` XAMPP
- XAMPP dalam persekitaran ni **hanya** menjalankan servis MySQL (pangkalan data) — Apache dan PHP XAMPP dimatikan/tidak digunakan

---

## 3. Senibina Sistem

### 3.1 Corak Seni Bina: MVC (Model-View-Controller)

Mengikut piawaian Laravel:

- **Model** (`app/Models/`) — mewakili jadual pangkalan data, uruskan hubungan (relationships) antara data
- **View** (`resources/views/`) — fail Blade (`.blade.php`) untuk paparan HTML
- **Controller** (`app/Http/Controllers/`) — logik perniagaan, terima permintaan HTTP, kembalikan paparan/response

Tambahan kepada MVC asas, sistem ini turut guna:

- **Observer** (`app/Observers/`) — "dengar" peristiwa model (cipta/kemaskini/padam) untuk rekod log audit secara automatik, tanpa perlu tulis kod log berulang kali dalam setiap controller
- **Policy** (`app/Policies/`) — pusatkan logik kebenaran ("siapa boleh buat apa") berasingan daripada controller
- **Service** (`app/Services/`) — logik khusus yang kompleks (OCR, panggilan AI) diasingkan daripada controller supaya controller kekal ringkas dan fokus pada aliran kerja sahaja
- **Middleware** (`app/Http/Middleware/`) — semak kebenaran peranan sebelum permintaan sampai ke controller

### 3.2 Corak Data: Entity-Attribute-Value (EAV)

Ini **konsep teras** yang membezakan sistem ini daripada sistem pengurusan dokumen biasa.

**Masalah yang diselesaikan:** Setiap jenis dokumen PKINK (Invois, Surat Rasmi, Borang Permohonan ICT, Minit Mesyuarat) memerlukan medan maklumat yang **berbeza sama sekali**. Reka bentuk pangkalan data tradisional ada dua pilihan, kedua-duanya bermasalah:

1. **Satu jadual gergasi** dengan lajur untuk setiap kemungkinan medan merentas semua jenis dokumen — kebanyakan lajur akan `NULL` untuk mana-mana rekod (invois tiada "Nama Pengerusi Mesyuarat", minit mesyuarat tiada "Jumlah RM")
2. **Satu jadual berasingan bagi setiap jenis dokumen** — setiap kali pentadbir nak tambah jenis dokumen baharu, seorang pengaturcara **mesti** tulis migration dan kod baharu

**Penyelesaian EAV:** Struktur medan disimpan sebagai **data**, bukan sebagai lajur pangkalan data yang tegar.

```
jenis_dokumen (Entity/Kategori)
      |
      | 1-ke-banyak
      v
dokumen_field (Attribute/Templat Medan)
      |
      | setiap medan merujuk...
      v
dokumen_data (Value/Nilai Sebenar) <-- merujuk kepada dokumen (rekod sebenar)
```

Contoh konkrit: `jenis_dokumen` "Invois Perjalanan" ada beberapa `dokumen_field` (No. Invois – Teks, Jumlah RM – Nombor, Status Bayaran – Dropdown). Bila satu invois sebenar dimuat naik, satu rekod `dokumen` dicipta, dan setiap jawapan disimpan sebagai baris berasingan dalam `dokumen_data` — setiap baris memetakan `dokumen_id` + `dokumen_field_id` + `nilai_data`.

**Kebaikan:** Pentadbir boleh cipta jenis dokumen baharu dengan medan tersendiri **terus dari UI**, tanpa sebarang kod baharu ditulis.

**Had yang perlu difahami (trade-off jujur):** Carian/penapis berstruktur ke atas nilai medan (cth. "cari semua invois dengan Jumlah RM > 1000") tidak boleh guna `WHERE` terus ke atas lajur, kerana nilai-nilai itu adalah **baris**, bukan **lajur**. Memerlukan JOIN yang lebih kompleks. Carian kata kunci ringkas (LIKE) sudah disokong merentasi semua nilai medan, tetapi penapis julat nombor/tarikh berstruktur pada medan EAV belum dibina (lihat Bahagian 12).

### 3.3 Struktur Folder Utama

```
app/
├── Http/
│   ├── Controllers/     <- Logik setiap modul (Dokumen, JenisDokumen, User, dll.)
│   └── Middleware/      <- SemakPeranan.php (kawalan akses RBAC)
├── Models/               <- Satu model bagi setiap jadual pangkalan data
├── Observers/            <- Auto-log audit bila model dicipta/dikemaskini/dipadam
├── Policies/             <- DokumenPolicy.php (kebenaran ikut peranan & skop)
├── Providers/            <- AppServiceProvider.php (daftar Observer & Policy)
└── Services/             <- OcrService.php, OllamaExtractionService.php

resources/views/          <- Fail Blade (.blade.php), satu folder bagi setiap modul
routes/web.php            <- Semua laluan (routes) aplikasi
config/services.php       <- Konfigurasi OCR & Ollama
```

---

## 4. Struktur Pangkalan Data Penuh

Pangkalan data (`dms_pkink_db`) mengandungi **14 jadual**, dibahagikan kepada 6 kumpulan fungsi.

### 4.1 Kumpulan: Organisasi & Kawalan Akses

**`bahagian`** — Senarai bahagian/jabatan dalam PKINK

| Lajur | Jenis | Catatan |
|---|---|---|
| bahagian_id | BIGINT (PK) | |
| kod_bahagian | VARCHAR | Unik (selamat soft delete) |
| nama_bahagian | VARCHAR | |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`users`** — Akaun kakitangan

| Lajur | Jenis | Catatan |
|---|---|---|
| user_id | BIGINT (PK) | |
| ic_pekerja | VARCHAR | Unik (selamat soft delete) |
| nama_staff | VARCHAR | |
| email | VARCHAR | Unik (selamat soft delete), guna untuk log masuk |
| password | VARCHAR | Hash (bcrypt) |
| bahagian_id | BIGINT (FK) | -> bahagian |
| remember_token | VARCHAR | Token "ingat saya" |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`roles`** — Senarai peranan sistem (8 peranan — lihat Bahagian 6)

| Lajur | Jenis | Catatan |
|---|---|---|
| roles_id | BIGINT (PK) | |
| kod_roles | VARCHAR | Cth: SUPERADMIN, STAFF |
| nama_roles | VARCHAR | Nama penuh/paparan |

**`user_roles`** — Jadual pivot (hubungan banyak-ke-banyak antara users & roles)

| Lajur | Jenis | Catatan |
|---|---|---|
| user_roles_id | BIGINT (PK) | |
| user_id | BIGINT (FK) | -> users |
| role_id | BIGINT (FK) | -> roles |

### 4.2 Kumpulan: Templat Dokumen (EAV)

**`jenis_dokumen`** — Kategori/jenis dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| jenis_dokumen_id | BIGINT (PK) | |
| kod_dokumen | VARCHAR | Unik (selamat soft delete) |
| nama_dokumen | VARCHAR | |
| kategori | VARCHAR | Kumpulan umum (pilihan, utk carian) |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`dokumen_field`** — Definisi medan EAV bagi setiap jenis dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_field_id | BIGINT (PK) | |
| jenis_dokumen_id | BIGINT (FK) | -> jenis_dokumen |
| nama_field | VARCHAR | Label dipaparkan |
| kod_field | VARCHAR | Pengecam unik dalam kategori (selamat soft delete) |
| jenis_data | VARCHAR | teks / nombor / tarikh / dropdown / textarea |
| pilihan | JSON | Senarai pilihan (hanya untuk dropdown) |
| is_required | BOOLEAN | Wajib diisi atau tidak |
| susunan | INT | Urutan paparan medan |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

### 4.3 Kumpulan: Dokumen Sebenar

**`dokumen`** — Rekod dokumen sebenar yang dimuat naik

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_id | BIGINT (PK) | |
| jenis_dokumen_id | BIGINT (FK) | -> jenis_dokumen |
| bahagian_id | BIGINT (FK) | -> bahagian (pemilik dokumen) |
| created_by | BIGINT (FK) | -> users (pemuat naik) |
| no_rujukan | VARCHAR | Unik, dijana automatik: `{KOD}/{TAHUN}/{TURUTAN 4-digit}` |
| tarikh_dokumen | DATE | |
| perkara | VARCHAR | Ringkasan (pilihan) |
| status | VARCHAR | Default "Archived" |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`dokumen_data`** — Nilai sebenar setiap medan EAV bagi setiap dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_data_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | -> dokumen |
| dokumen_field_id | BIGINT (FK) | -> dokumen_field |
| nilai_data | LONGTEXT | Nilai sebenar (semua jenis data disimpan sebagai teks) |
| — | UNIQUE(dokumen_id, dokumen_field_id) | Elak nilai pendua bagi medan sama |

### 4.4 Kumpulan: Imbasan & OCR

**`dokumen_scan`** — Fail imbasan fizikal (PDF/JPG/PNG) bagi setiap dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_scan_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | -> dokumen |
| nama_fail | VARCHAR | Nama asal fail dimuat naik |
| lokasi_fail | VARCHAR | Laluan dalam storan **peribadi** (disk `local`) |
| tarikh_scan | TIMESTAMP | |
| status_ocr | VARCHAR | Pending / Selesai / Ralat / Tiada Teks Dikesan |

**`dokumen_ocr`** — Hasil pengecaman teks bagi setiap fail imbasan

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_ocr_id | BIGINT (PK) | |
| dokumen_scan_id | BIGINT (FK) | -> dokumen_scan |
| teks_dibaca | LONGTEXT | Teks penuh hasil OCR |
| skor_padanan | DECIMAL | Skor keyakinan purata (0-100%) daripada Tesseract |
| status_semakan | VARCHAR | Belum Disemak (semakan manual belum ada UI) |

### 4.5 Kumpulan: Aliran Kelulusan

**`borang`** — Status permohonan kelulusan bagi setiap dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| borang_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | -> dokumen |
| pemohon_id | BIGINT (FK) | -> users |
| pegawai_penyokong_id | BIGINT (FK, nullable) | -> users |
| pegawai_pelulus_id | BIGINT (FK, nullable) | -> users |
| status_permohonan | VARCHAR | Dalam Proses / Disokong / Diluluskan / Ditolak |
| tarikh_permohonan, tarikh_sokongan, tarikh_kelulusan | TIMESTAMP | |
| ulasan_penyokong, ulasan_pelulus | TEXT | |

### 4.6 Kumpulan: Audit & Infra Laravel

**`log_audit`** — Jejak setiap tindakan dalam sistem

| Lajur | Jenis | Catatan |
|---|---|---|
| log_audit_id | BIGINT (PK) | |
| user_id | BIGINT (FK) | Pelaku tindakan |
| tindakan | VARCHAR | Cipta / Kemaskini / Padam / Tetapan Peranan / dll. |
| nama_jadual | VARCHAR | Jadual yang terlibat |
| rekod_id | BIGINT | ID rekod yang terlibat |
| data_lama, data_baharu | JSON | Nilai sebelum & selepas (lajur sensitif ditapis) |
| alamat_ip, peranti_pengguna | VARCHAR | |
| created_at | TIMESTAMP | (Tiada `updated_at` — log tidak boleh diubah) |

**`password_reset_tokens`**, **`sessions`** — Jadual infra bawaan Laravel (reset kata laluan, pengurusan sesi log masuk)

---

## 5. Modul-Modul Sistem (Terperinci)

### 5.1 Pengesahan (Authentication)
Log masuk guna emel + kata laluan. Tetapan semula kata laluan melalui token emel. Sesi diuruskan oleh jadual `sessions` Laravel.

### 5.2 Papan Pemuka (Dashboard)
Kandungan **berbeza ikut peranan** pengguna yang log masuk — semua angka ditarik **secara langsung** daripada pangkalan data (tiada data statik/reka):

- **Semua peranan berasaskan dokumen:** Jumlah Dokumen (skop ikut kebenaran), Fail Menunggu OCR
- **PENGURUS:** Carta pecahan kategori dokumen bahagian sendiri
- **STAFF:** Status permohonan kelulusan dokumen sendiri
- **PENYOKONG/PELULUS:** Bilangan menunggu tindakan, jadual ringkas permohonan
- **AUDIT:** Ringkasan log audit, carta pecahan jenis tindakan
- **ADMIN/SUPERADMIN:** Semua di atas + Jumlah Kakitangan, Jumlah Kategori, **Purata Masa Kelulusan** (dikira sebenar daripada cap masa `tarikh_permohonan` -> `tarikh_kelulusan`), carta trend muat naik 6 bulan

### 5.3 Pengurusan Kategori Dokumen (`jenis_dokumen`)
CRUD penuh dengan soft delete. Ciri utama: **pembina medan EAV dinamik** — admin klik "Tambah Medan" berulang kali dalam borang Cipta/Edit Kategori untuk tentukan medan (Teks/Nombor/Tarikh/Dropdown/Textarea), wajib/tidak, dan pilihan (untuk dropdown) — semua guna JavaScript sebelah klien, disahkan semula di pelayan semasa simpan. Kod medan disahkan unik dalam satu kategori, selamat dengan soft delete menggunakan lajur janaan (generated column) pangkalan data.

### 5.4 Pengurusan Dokumen — Modul Teras
**Muat Naik:** Pilih Jenis Dokumen -> borang berubah bentuk **secara automatik** (AJAX, tanpa muat semula halaman) memaparkan medan yang relevan sahaja -> isi medan -> muat naik berbilang fail imbasan (PDF/JPG/PNG, maksimum 10MB setiap fail) -> No. Rujukan dijana automatik format `{KOD}/{TAHUN}/{TURUTAN}` (menggunakan nombor tertinggi sedia ada, bukan `COUNT()`, supaya kebal daripada isu dokumen dipadam menyebabkan nombor bertembung).

**Senarai:** Carian kata kunci merentasi No. Rujukan, Perkara, nama Jenis Dokumen, **nilai medan EAV**, dan nama fail imbasan sekali gus. Penapis ikut Jenis Dokumen, Bahagian, julat Tarikh. Panel carian/penapis boleh dilipat (collapse) dengan paparan "chip" bagi penapis aktif, supaya antara muka kekal kemas semasa tiada penapis digunakan.

**Butiran:** Papar semua nilai medan EAV, senarai fail imbasan (boleh dibuka/muat turun melalui laluan terlindung — lihat Bahagian 7), status kelulusan terkini, dan teks OCR (boleh dibuka/tutup) bagi setiap fail.

**Kemaskini:** Ubah tarikh/perkara/nilai medan, buang fail lama atau tambah fail baharu (dokumen mesti kekal ada sekurang-kurangnya satu fail imbasan).

**Padam:** Soft delete — rekod, nilai medan, dan fail fizikal **dikekalkan** untuk tujuan arkib; No. Rujukan tidak digunakan semula.

### 5.5 Pengurusan Bahagian & Kakitangan
CRUD penuh. Pendaftaran/kemaskini kakitangan termasuk **penetapan peranan** (`roles[]`) — hanya SUPERADMIN boleh menetapkan peranan; jika ADMIN yang mendaftar, kakitangan baharu automatik diberi peranan STAFF.

### 5.6 Kawalan Akses Ikut Peranan (RBAC)
Lihat Bahagian 6 (bahagian berasingan kerana kepentingannya).

### 5.7 Log Audit
Setiap Cipta/Kemaskini/Padam pada jadual `bahagian`, `jenis_dokumen`, `users`, `dokumen`, `borang` direkod automatik melalui corak Observer — tiada kod log manual diperlukan dalam controller. Halaman Log Audit (hanya SUPERADMIN & AUDIT) ada penapis ikut pengguna/jadual/tindakan/julat tarikh, dan setiap baris boleh dibuka untuk lihat data JSON sebelum/selepas perubahan. Lajur sensitif (`password`, `remember_token`) **sentiasa** ditapis daripada log.

### 5.8 Aliran Kelulusan (`borang`)
Alur: **Hantar** (oleh pemuat naik/pengedit dokumen) -> **Sokong** (PENYOKONG) -> **Luluskan** (PELULUS), atau **Tolak** pada mana-mana peringkat (ulasan wajib diisi bila menolak). Status dipaparkan dan tindakan dilakukan terus dalam halaman Butiran Dokumen. Halaman senarai `/kelulusan` memaparkan semua permohonan menunggu tindakan bagi Penyokong/Pelulus/Admin.

### 5.9 OCR (Pengecaman Teks)
Dicetuskan **secara manual** oleh staf (butang "Jalankan OCR" pada setiap fail dalam halaman Butiran Dokumen) — bukan automatik, supaya borang muat naik kekal responsif. Alur teknikal:

1. Jika fail PDF -> `pdftoppm` (Poppler) tukar setiap muka surat kepada imej PNG 300dpi
2. Setiap imej dihantar ke `tesseract` (Bahasa Melayu + Inggeris), output format TSV
3. Teks dan **skor keyakinan purata** (daripada lajur `conf` TSV Tesseract) diekstrak serentak
4. Disimpan ke `dokumen_ocr`; status fail dikemaskini (`Selesai` / `Ralat` / `Tiada Teks Dikesan`)

Kegagalan OCR **tidak** menjejaskan rekod dokumen induk — ditanda `Ralat`, dicatat dalam log pelayan, staf boleh cuba semula bila-bila masa.

### 5.10 Imbas & Arkib — Ciri Unggulan (AI-Berkuasa)
Modul **BAHARU dan berasingan** daripada "Muat Naik Dokumen", khusus untuk digitalkan **dokumen fizikal yang sedia lulus** (fail lama PKINK) — matlamat: staf **tidak perlu** cipta dokumen dan isi borang secara manual; cukup imbas, sistem cuba isi sendiri.

**Alur kerja 2-langkah:**

**Langkah 1 — Analisis Automatik:**
1. Staf pilih Jenis Dokumen + muat naik **satu** fail imbasan
2. Sistem jalankan OCR (`OcrService`) untuk dapatkan teks mentah
3. Teks mentah + **senarai medan EAV** bagi Jenis Dokumen tersebut dihantar ke `OllamaExtractionService`, yang berkomunikasi dengan model AI **SEA-LION** dijalankan secara **tempatan** melalui Ollama (bukan API awan berbayar — lihat Bahagian 2.2)
4. Model AI pulangkan cadangan nilai bagi setiap medan dalam format JSON tersusun

**Langkah 2 — Semakan & Pengesahan (Wajib):**
5. Borang semakan dipaparkan — setiap medan yang berjaya diisi AI ditanda label "Diisi AI"; teks OCR penuh dipaparkan bersebelahan untuk staf bandingkan
6. Staf **mesti** semak dan betulkan sebarang kesilapan sebelum tekan "Sahkan & Simpan" — **tiada** data disimpan secara automatik tanpa pengesahan manusia
7. Selepas disahkan, rekod `dokumen` + `dokumen_data` + `dokumen_scan` + `dokumen_ocr` dicipta serentak, dan `borang` terus dicipta berstatus **"Diluluskan"** (bukan "Dalam Proses") — kerana ini mengarkibkan dokumen yang **sudah pun** lulus secara fizikal sebelum sistem ini wujud, bukan permohonan kelulusan baharu

**Kenapa AI, bukan peraturan tetap (regex) dikodkan keras?** Kerana corak EAV membenarkan pentadbir cipta Jenis Dokumen baharu dengan medan berbeza bila-bila masa. `OllamaExtractionService` hantar senarai medan sebagai sebahagian arahan (prompt) kepada AI — bukan dikodkan secara tetap — jadi ia **automatik serasi** dengan jenis dokumen apa sahaja yang dicipta kemudian, tanpa sebarang kod tambahan ditulis.

**Kos & Privasi:** Kerana model AI dijalankan **sepenuhnya tempatan** (bukan hantar data ke syarikat luar), tiada bayaran berterusan dan tiada dokumen rasmi PKINK meninggalkan rangkaian/komputer tempatan.

---

## 6. Kawalan Akses Ikut Peranan (RBAC)

Sistem ada **8 peranan**, dikuatkuasakan melalui tiga lapisan: `DokumenPolicy` (kebenaran per-tindakan), middleware `SemakPeranan` (sekat seluruh laluan), dan `Dokumen::scopeBolehDilihat()` (skop data yang boleh dilihat).

| Peranan | Skop Lihat Dokumen | Muat Naik | Edit | Padam | Modul Pentadbiran | Lain-lain |
|---|---|---|---|---|---|---|
| **SUPERADMIN** | Semua | Ya | Ya | Ya | Ya | Satu-satunya boleh urus akaun SUPERADMIN lain |
| **ADMIN** | Semua | Ya | Ya | Ya | Ya | |
| **KERANI** | Semua | Ya | Ya | Ya | Tidak | |
| **PENGURUS** | Bahagian sendiri sahaja | Ya | Bahagian sendiri | Tidak | Tidak | |
| **STAFF** | Dokumen milik sendiri sahaja | Ya | Milik sendiri | Tidak | Tidak | Boleh hantar dokumen untuk kelulusan |
| **PENYOKONG** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Sokong/tolak permohonan peringkat 1 |
| **PELULUS** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Luluskan/tolak permohonan peringkat 2 |
| **AUDIT** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Akses penuh halaman Log Audit |

Menu sidebar dan butang tindakan (Edit/Padam/Muat Naik) **disembunyikan secara automatik** mengikut peranan pengguna yang log masuk — bukan sekadar disekat di pelayan, tetapi tidak dipaparkan langsung pada antara muka. Percubaan akses terus melalui URL (tanpa kebenaran) akan dipaparkan halaman ralat **403 Akses Ditolak** khas.

---

## 7. Keselamatan Sistem

- **Fail imbasan disimpan secara PERIBADI** (disk `local`, bukan `public`) — satu-satunya cara capai fail ialah melalui laluan `/dokumen/scan/{id}/papar` yang **menyemak `DokumenPolicy`** terlebih dahulu sebelum stream fail. Tiada URL awam terus seperti `storage/...` yang boleh diteka
- **Perlindungan privilege escalation** — ADMIN tidak boleh mengedit/memadam/menukar kata laluan akaun SUPERADMIN, mengelakkan ADMIN "menaikkan" kuasa sendiri melalui akaun lain
- **Perlindungan SUPERADMIN terakhir** — peranan SUPERADMIN tidak boleh dibuang daripada satu-satunya pengguna SUPERADMIN yang tinggal dalam sistem
- **Kata laluan di-hash** menggunakan bcrypt (12 pusingan), tidak pernah disimpan dalam teks biasa
- **Lajur sensitif ditapis daripada Log Audit** — `password` dan `remember_token` tidak pernah direkod dalam `log_audit`, walaupun semasa kemaskini profil
- **Validasi keunikan selamat soft delete** — menggunakan lajur janaan (generated column) pangkalan data supaya emel/No. KP/kod yang telah "dipadam" boleh digunakan semula tanpa konflik, tetapi rekod aktif kekal unik
- **Perlindungan CSRF** — setiap borang POST/PUT/DELETE guna token CSRF terbina dalam Laravel
- **Kegagalan proses luaran (OCR/AI) tidak menjejaskan integriti data** — dibungkus dalam try/catch, direkod sebagai status ralat, tidak pernah membatalkan simpanan dokumen induk

---

## 8. Carta Alir Pengguna

### 8.1 Alur Muat Naik Dokumen Biasa (STAFF)
```
Log Masuk -> Pilih "Muat Naik Dokumen" -> Pilih Jenis Dokumen
    -> Borang EAV dipaparkan automatik -> Isi medan + muat naik fail
    -> No. Rujukan dijana automatik -> Dokumen tersimpan (status: Archived)
    -> (Pilihan) Hantar untuk Kelulusan -> Status: Dalam Proses
```

### 8.2 Alur Kelulusan (PENYOKONG -> PELULUS)
```
STAFF hantar dokumen -> Status: Dalam Proses
    -> PENYOKONG semak & sokong -> Status: Disokong
    -> PELULUS semak & luluskan -> Status: Diluluskan
    (Pada mana-mana peringkat, boleh Tolak dengan ulasan wajib -> Status: Ditolak)
```

### 8.3 Alur Imbas & Arkib (Dokumen Lama Sedia Lulus)
```
Staf Bahagian Digital pilih "Imbas & Arkib" -> Pilih Jenis Dokumen + 1 fail
    -> OCR baca teks -> AI (SEA-LION) cadangkan nilai setiap medan
    -> Skrin Semakan dipaparkan (nilai + teks OCR asal bersebelahan)
    -> Staf semak & betulkan -> Sahkan & Simpan
    -> Dokumen tercipta terus berstatus: DILULUSKAN
```

---

## 9. Peta Fail -> Lokasi Dalam Projek

> Fail Blade yang dimuat turun dinamakan dengan `_blade.php` (bukan `.blade.php`) atas had platform muat turun — **namakan semula** ke `.blade.php` selepas disalin ke projek.

### Model (`app/Models/`)
`Dokumen.php`, `DokumenData.php`, `DokumenField.php`, `DokumenScan.php`, `DokumenOcr.php`, `JenisDokumen.php`, `User.php`, `Role.php`, `LogAudit.php`, `Borang.php`

### Observer (`app/Observers/`)
`DokumenObserver.php`, `JenisDokumenObserver.php`, `UserObserver.php`
*(`BahagianObserver.php` turut wujud dalam projek asal, tidak diubah dalam sesi pembangunan ini)*

### Policy (`app/Policies/` — cipta folder)
`DokumenPolicy.php`

### Middleware (`app/Http/Middleware/`)
`SemakPeranan.php`

### Service (`app/Services/` — cipta folder)
`OcrService.php`, `OllamaExtractionService.php`

### Controller (`app/Http/Controllers/`)
`DokumenController.php`, `JenisDokumenController.php`, `UserController.php`, `LogAuditController.php`, `BorangController.php`, `DashboardController.php`, `ImbasArkibController.php` (mewarisi `DokumenController`)

### Provider & Bootstrap

| Fail | Lokasi |
|---|---|
| `AppServiceProvider.php` | `app/Providers/` |
| `app.php` | `bootstrap/app.php` |

### Routes & Konfigurasi

| Fail | Lokasi |
|---|---|
| `web.php` | `routes/web.php` |
| `config_services_tambahan.txt` | Arahan tambah ke `config/services.php` + `.env` (bukan fail kod) |

### Views

| Fail | Lokasi |
|---|---|
| `app_blade.php` | `resources/views/layouts/app.blade.php` |
| `create_blade.php`, `edit_blade.php`, `medan-script_blade.php` | `resources/views/jenis_dokumen/` (`medan-script` -> subfolder `partials/`) |
| `dokumen_create_blade.php`, `dokumen_index_blade.php`, `dokumen_show_blade.php`, `dokumen_edit_blade.php` | `resources/views/dokumen/` sebagai `create/index/show/edit.blade.php` |
| `users_create_blade.php`, `users_edit_blade.php` | `resources/views/users/` sebagai `create/edit.blade.php` |
| `log_audit_index_blade.php` | `resources/views/log_audit/index.blade.php` (cipta folder) |
| `kelulusan_index_blade.php` | `resources/views/kelulusan/index.blade.php` (cipta folder) |
| `errors_403_blade.php` | `resources/views/errors/403.blade.php` (cipta folder) |
| `dashboard_index_blade.php` | `resources/views/dashboard/index.blade.php` |
| `imbas_arkib_create_blade.php`, `imbas_arkib_semak_blade.php` | `resources/views/imbas_arkib/` sebagai `create/semak.blade.php` (cipta folder) |

### Fail Sekali-Jalan (Utiliti)
`pindah_fail_scan_ke_private.php` — skrip migrasi fail daripada storan awam ke peribadi (jalan sekali, kemudian padam)

---

## 10. Panduan Pasang Sistem Dari Kosong

1. Pastikan PHP 8.3+, Composer, dan MySQL/MariaDB 10.4+ dipasang (disyorkan: Laravel Herd untuk Windows)
2. Clone/salin kod projek, jalankan `composer install`
3. Salin `.env.example` ke `.env`, isi `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
4. Jana kunci aplikasi: `php artisan key:generate`
5. Import skema pangkalan data asal (`dms_pkink_db.sql`) melalui HeidiSQL/phpMyAdmin
6. Jalankan skrip migration tambahan **mengikut turutan nombor** (01 hingga 06) dalam HeidiSQL
7. Jalankan `php artisan storage:link`
8. Pasang Tesseract OCR + Poppler, tambah ke System PATH (atau tetapkan laluan penuh dalam `.env` — lihat `config_services_tambahan.txt`)
9. Pasang Ollama, tarik (`pull`) model SEA-LION
10. Tambah konfigurasi `OCR_*` dan `OLLAMA_*` dalam `.env`, jalankan `php artisan config:clear`
11. Jalankan `php artisan serve` (atau akses terus melalui Herd)

---

## 11. Isu Yang Telah Dibaiki Sepanjang Pembangunan

| Isu | Punca | Pembetulan |
|---|---|---|
| Kebocoran `remember_token` dalam Log Audit (plaintext) | `UserObserver` log semua perubahan termasuk putaran token "ingat saya" | Lajur sensitif dikecualikan secara eksplisit |
| Log audit berganda (rekod dicipta dua kali) | Observer didaftar DUA kali: `#[ObservedBy]` attribute PADA model + pendaftaran manual dalam `AppServiceProvider` | Buang attribute, kekalkan satu pendaftaran pusat |
| `user_roles` sentiasa kosong walaupun peranan ditetapkan | Kod guna lajur `roles_id`, sedangkan lajur pivot sebenar dalam skema ialah `role_id` | Betulkan rujukan lajur dalam model `User`/`Role` |
| ADMIN boleh tukar kata laluan akaun SUPERADMIN (privilege escalation) | Tiada semakan kebenaran tambahan untuk urus akaun berperanan tinggi | `pastikanBolehUrus()` sekat tindakan ini |
| UNIQUE index hilang pada email/IC/kod (SOP buang kerana konflik soft delete) | Pendekatan asal buang terus semua constraint unik | Dipulihkan guna lajur janaan (generated column) yang selamat dengan soft delete |
| Fail imbasan boleh diakses sesiapa melalui URL awam | Disimpan pada disk `public` tanpa semakan kebenaran | Dipindah ke disk `local` (peribadi) + laluan khas menyemak `DokumenPolicy` |
| OCR gagal dengan ralat "tesseract not recognized" | Proses Herd tidak nampak kemaskini System PATH Windows | Tukar kepada laluan penuh (`.exe`) ditetapkan dalam `.env`, bukan bergantung PATH |

---

## 12. Had Sistem & Kerja Akan Datang

### Aliran Kelulusan — Versi Asas Sahaja (Disengajakan)
Dibina ringkas kerana sistem baru digunakan oleh Bahagian Digital untuk arkibkan dokumen sedia lulus; aliran kelulusan penuh belum diperlukan di peringkat ini:
- Satu dokumen = satu rekod `borang` sahaja — hantar semula **menimpa** ulasan/tarikh sebelumnya, tiada sejarah berbilang penghantaran
- Penyokong & Pelulus tidak diskop ikut bahagian — sesiapa berperanan itu boleh bertindak ke atas borang bahagian mana-mana
- Tiada notifikasi (emel/loceng) apabila status berubah

### OCR — Segerak (Synchronous)
Diproses dalam permintaan HTTP yang sama (bukan queue job latar belakang) — butang jalankan OCR akan "sekat" sehingga proses selesai. Sesuai untuk penggunaan semasa (volum rendah), tetapi perlu dipindah ke `php artisan queue:work` jika penggunaan meningkat.

### Kemas Kini Kecil Belum Dibuat
- Senarai Kakitangan belum papar lajur peranan pada paparan (data sudah dimuat, tinggal tambah lajur UI)
- KERANI sepatutnya "lihat sahaja" bagi Kategori Dokumen & Bahagian, tetapi kini disekat sepenuhnya kepada SUPERADMIN/ADMIN
- Tiada skrin "Tong Sampah" untuk pulihkan (restore) rekod yang disoft-delete
- Penapis julat nombor/tarikh berstruktur ke atas nilai medan EAV belum dibina (carian kata kunci sahaja sedia ada)
- `dokumen_scan` tiada metadata saiz fail, jenis MIME, atau hash (checksum) untuk sahkan integriti arkib jangka panjang
- Fail sementara daripada "Imbas & Arkib" yang dibatalkan (`storage/app/private/ocr_sementara/`) tiada pembersihan automatik

---

## 13. Akaun Ujian (Untuk Pembangunan/Demo Sahaja)

Kata laluan seragam: `123456789`

| Peranan | Emel |
|---|---|
| ADMIN | admin.ujian@pkink.com |
| KERANI | kerani.ujian@pkink.com |
| PENGURUS | pengurus.ujian@pkink.com |
| STAFF (Bahagian Digital) | staff1.ujian@pkink.com |
| STAFF (Bahagian Kewangan) | staff2.ujian@pkink.com |
| AUDIT | audit.ujian@pkink.com |
| PENYOKONG | penyokong.ujian@pkink.com |
| PELULUS | pelulus.ujian@pkink.com |

**Nota:** Akaun ujian ini perlu dipadam (soft delete melalui UI) sebelum sistem digunakan dalam persekitaran sebenar/produksi.

---

## 14. Glosari Istilah

| Istilah | Maksud |
|---|---|
| **EAV** | Entity-Attribute-Value — corak reka bentuk pangkalan data yang membenarkan medan dinamik tanpa ubah struktur jadual |
| **RBAC** | Role-Based Access Control — kawalan akses berdasarkan peranan pengguna |
| **OCR** | Optical Character Recognition — teknologi pengecaman teks daripada imej |
| **LLM** | Large Language Model — model AI bahasa (cth. SEA-LION dalam sistem ini) |
| **Soft Delete** | Teknik "padam" rekod tanpa benar-benar buang daripada pangkalan data (tandakan `deleted_at`) — membolehkan pemulihan & arkib |
| **Observer (Laravel)** | Corak reka bentuk yang "dengar" peristiwa model untuk jalankan kod tambahan automatik |
| **Policy (Laravel)** | Kelas yang pusatkan logik kebenaran bagi satu model |
| **Migration** | Fail/skrip untuk ubah struktur pangkalan data secara terkawal dan boleh dijejak |
| **Generated Column** | Lajur pangkalan data yang nilainya dikira automatik daripada lajur lain (digunakan untuk UNIQUE selamat soft delete) |
| **Gate / Authorize** | Mekanisme Laravel untuk semak kebenaran sebelum tindakan dibenarkan |
| **Collection (Eloquent)** | Struktur data Laravel untuk uruskan set rekod dengan kaedah tambahan (map, filter, dll.) |

---

*Dokumen ini dikemaskini sepanjang pembangunan projek. Untuk sebarang persoalan teknikal lanjut semasa pembentangan, rujuk nombor bahagian berkaitan di atas.*