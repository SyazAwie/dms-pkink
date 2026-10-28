# Sistem Arkib Digital (DMS) PKINK

**Nama Penuh:** Sistem Pengurusan Dokumen Digital (Document Management System) Perbadanan Kemajuan Iktisad Negeri Kelantan
**Jenis Projek:** Aplikasi Web Latihan Industri (Sains Komputer)
**Status (27 Oktober 2026):** Modul teras berfungsi dan digunakan secara rintis oleh Bahagian Digital. 293 ujian automatik lulus.

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
13. [Akaun Ujian](#13-akaun-ujian-untuk-pembangunandemo-sahaja)
14. [Glosari Istilah](#14-glosari-istilah)

---

## 1. Pengenalan & Objektif Projek

Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK) menguruskan dokumen rasmi (invois, surat rasmi, borang permohonan, minit mesyuarat dan sebagainya) secara fizikal merentas pelbagai bahagian. Proses ini membawa beberapa masalah:

- Dokumen fizikal mudah hilang, rosak atau sukar dicari semula
- Tiada jejak audit yang jelas, jadi sukar tahu siapa buat apa dan bila
- Setiap bahagian guna format berbeza untuk jenis dokumen berbeza, jadi sukar diseragamkan
- Proses kelulusan dokumen (sokongan → kelulusan) tidak direkod secara digital

**Objektif Sistem Arkib Digital PKINK:**

1. Digitalkan penyimpanan dokumen dengan struktur yang **fleksibel**: setiap jenis dokumen boleh ada medan maklumat berbeza, ditentukan sendiri oleh pentadbir tanpa kemahiran pengaturcaraan
2. Sediakan kawalan akses yang jelas ikut peranan kakitangan (siapa boleh buat apa)
3. Rekodkan setiap tindakan dalam sistem untuk tujuan audit dan akauntabiliti
4. Sediakan aliran kerja kelulusan digital (Penyokong → Pelulus)
5. Permudahkan pendigitalan dokumen **lama** dan borang yang diisi dengan tangan melalui OCR (pengecaman teks) dan AI tempatan (pengekstrakan data automatik)

**Tiga situasi penggunaan borang** yang disokong:

| Situasi | Cara dalam sistem |
|---|---|
| Borang belum diisi | **Isi Digital**: isi borang terus dalam sistem; maklumat profil (nama, No. KP, emel, bahagian, jawatan, telefon) diisi automatik |
| Borang kertas sudah diisi oleh pemohon | **Imbas & Hantar**: imbas borang, OCR + AI isi borang digital, pemohon sahkan, kemudian hantar untuk kelulusan |
| Dokumen fizikal lama yang sudah lulus | **Imbas & Arkib** (KERANI/pentadbir): OCR + AI isi borang, staf sahkan, dokumen terus berstatus Diluluskan |

**Fasa semasa:** Sistem digunakan secara rintis oleh **Bahagian Digital PKINK** untuk mengimbas dan mengarkibkan dokumen fizikal sedia ada. Penggunaan menyeluruh merentas semua bahagian PKINK dirancang untuk fasa akan datang. Beberapa modul (seperti aliran kelulusan penuh) sengaja dibina pada tahap asas buat masa ini; lihat [Bahagian 12](#12-had-sistem--kerja-akan-datang).

---

## 2. Spesifikasi Teknikal & Keperluan Sistem

### 2.1 Tech Stack Utama

| Komponen | Versi / Teknologi | Catatan |
|---|---|---|
| **Bahasa Pengaturcaraan** | PHP 8.3 | |
| **Rangka Kerja (Framework)** | Laravel 13 | Rangka kerja MVC |
| **Pengurus Pakej PHP** | Composer | |
| **Pangkalan Data** | MySQL / MariaDB 10.4 | Enjin InnoDB, aksara `utf8mb4` |
| **Pelayan Web (Local Dev)** | Laravel Herd (nginx + PHP-FPM) atau `php artisan serve` | Persekitaran pembangunan di Windows |
| **Pelayan Pangkalan Data (Local Dev)** | XAMPP, **MySQL sahaja** | Apache/PHP XAMPP tidak digunakan |
| **Pengurus Pangkalan Data** | HeidiSQL | Skema diurus manual (tiada migration Laravel, lihat 4.0) |
| **Frontend** | Blade | Tiada langkah build; scaffold Vite/Tailwind dalam `package.json` tidak digunakan |
| **Rangka Kerja CSS** | Bootstrap 5.3.3 | Dimuat dari CDN jsDelivr dengan SRI (`integrity` + `crossorigin`) |
| **Ikon** | Bootstrap Icons 1.11.3 | CDN jsDelivr dengan SRI |
| **Carta/Graf** | Bar HTML/CSS | Chart.js tidak lagi digunakan |
| **Reka Bentuk UI** | Glassmorphism (custom), responsif | Panel lut sinar, kecerunan warna korporat biru-hijau |
| **Bahasa Antara Muka** | Bahasa Melayu (`APP_LOCALE=ms`) | Mesej validasi, tarikh, paginasi dan halaman ralat dalam BM |
| **Zon Masa** | `Asia/Kuala_Lumpur` | Selari dengan MySQL/HeidiSQL |
| **Model Data Dinamik** | Entity-Attribute-Value (EAV) | Lihat Bahagian 3.2 |

### 2.2 Keperluan Perisian Luaran (Dipasang Berasingan)

Beberapa ciri memerlukan perisian sistem tambahan dipasang **terus pada pelayan/komputer** (bukan melalui Composer):

| Perisian | Tujuan | Sumber Pemasangan |
|---|---|---|
| **Tesseract OCR** | Pengecaman teks (OCR) daripada imej/PDF imbasan | UB-Mannheim build (Windows), dengan pek bahasa Malay (`msa`) + English (`eng`) |
| **Poppler (`pdftoppm`)** | Tukar muka surat PDF kepada imej sebelum OCR | poppler-windows releases (GitHub) |
| **Ollama** | Jalankan model AI secara tempatan | ollama.com/download |
| **Model AI: SEA-LION** | LLM 8 bilion parameter untuk bahasa Asia Tenggara termasuk BM; mengekstrak nilai medan daripada teks OCR | aisingapore/Llama-SEA-LION-v3.5-8B-R, didaftarkan dalam Ollama sebagai `sea-lion-local` |
| **Model penglihatan: GLM-OCR** | Membaca tulisan tangan apabila keyakinan Tesseract rendah | `ollama pull glm-ocr:q8_0` (0.9B, ~1.8GB, lesen MIT) |

### 2.3 Keperluan Perkakasan (Disyorkan)

| Komponen | Minimum | Disyorkan (untuk OCR + AI lancar) |
|---|---|---|
| RAM | 8GB | 16GB+ (pembangunan: 20GB) |
| Storan | 10GB ruang kosong | 20GB+ (model AI ~7GB, pangkalan data, fail imbasan) |
| GPU | Tidak wajib | NVIDIA (CUDA) 6GB+ (pembangunan: RTX 3050 6GB). Model SEA-LION dimuat penuh dalam GPU |
| Sistem Operasi | Windows 10/11 (persekitaran pembangunan semasa) | |

### 2.4 Kebergantungan PHP (Composer) Utama

- `laravel/framework` ^13.0
- Pakej teras Laravel: Eloquent ORM, Blade, Validation, Soft Deletes, Notifications (emel), Rate Limiter
- **Symfony Process** (terbina dalam Laravel) untuk memanggil CLI Tesseract/Poppler; **tiada** pustaka OCR pihak ketiga
- **Laravel HTTP Client** (berasaskan Guzzle) untuk memanggil API tempatan Ollama; **tiada** SDK AI pihak ketiga
- **GD** (sambungan PHP) untuk membuang garisan kotak/jadual daripada imej sebelum OCR, dan tera air salinan muat turun
- Pakej kecil Fasa P: `laravel-notification-channels/webpush` (notifikasi peranti), `setasign/fpdf` (slip kelulusan & salinan bertera air), `bacon/bacon-qr-code` (kod QR slip & 2FA), `pragmarx/google2fa` (TOTP 2FA)

Reka bentuk ini sengaja **meminimumkan kebergantungan pihak ketiga**: integrasi OCR dan AI dibina terus di atas keupayaan sedia ada Laravel (Process + HTTP Client), bukan pustaka luar yang menambah risiko keserasian versi.

### 2.5 Nota Persekitaran Pembangunan

- **Laravel Herd** guna nginx (bukan Apache), dan ada `php.ini` sendiri yang berasingan daripada XAMPP
- XAMPP **hanya** menjalankan servis MySQL; Apache dan PHP XAMPP tidak digunakan
- Herd **tidak nampak** kemas kini System PATH Windows, jadi laluan Tesseract/Poppler ditetapkan sebagai laluan penuh `.exe` dalam `.env` (`OCR_TESSERACT_BIN`, `OCR_PDFTOPPM_BIN`)
- Selepas mengubah `.env` atau fail `config/`, jalankan `php artisan config:clear`

---

## 3. Senibina Sistem

### 3.1 Corak Seni Bina: MVC (Model-View-Controller)

Mengikut piawaian Laravel:

- **Model** (`app/Models/`): mewakili jadual pangkalan data dan hubungan antara data. Setiap jadual guna kunci primer bukan standard (`user_id`, `dokumen_id`, `roles_id`, …), jadi setiap model menetapkan `$primaryKey` dan setiap hubungan menyatakan kunci asing secara eksplisit
- **View** (`resources/views/`): fail Blade (`.blade.php`)
- **Controller** (`app/Http/Controllers/`): aliran kerja setiap modul

Tambahan kepada MVC asas:

- **Observer** (`app/Observers/`): rekod log audit automatik bila model dicipta/dikemaskini/dipadam. Didaftar **sekali sahaja** dalam `AppServiceProvider::boot()` (bukan juga `#[ObservedBy]` pada model, kerana pendaftaran berganda pernah menggandakan log)
- **Policy** (`app/Policies/`): logik kebenaran dokumen (`DokumenPolicy`)
- **Middleware** (`app/Http/Middleware/`): `SemakPeranan` (alias `peranan:`) menyekat laluan ikut peranan; `WajibTukarKataLaluan` (alias `wajib.tukar`) memaksa pengguna dengan kata laluan sementara menukarnya dahulu
- **Service** (`app/Services/`): OCR (`OcrService`), model penglihatan (`VlmOcrService`) dan pengekstrakan AI (`OllamaExtractionService`) diasingkan daripada controller
- **Support** (`app/Support/`): kelas utiliti, cth. `JenisMedan` (sumber tunggal jenis medan EAV), `MedanJadual`, `TemplatKategori`, `BuangGarisan`, `PolisiKataLaluan`, `PeraturanNama`, `TeksEmel`

### 3.2 Corak Data: Entity-Attribute-Value (EAV)

Ini **konsep teras** yang membezakan sistem ini daripada sistem pengurusan dokumen biasa.

**Masalah yang diselesaikan:** Setiap jenis dokumen PKINK (Invois, Surat Rasmi, Borang Permohonan ICT, Minit Mesyuarat) memerlukan medan maklumat yang **berbeza sama sekali**. Reka bentuk pangkalan data tradisional ada dua pilihan, dan kedua-duanya bermasalah:

1. **Satu jadual gergasi** dengan lajur untuk setiap kemungkinan medan merentas semua jenis dokumen. Kebanyakan lajur akan `NULL` (invois tiada "Nama Pengerusi Mesyuarat", minit mesyuarat tiada "Jumlah RM")
2. **Satu jadual berasingan bagi setiap jenis dokumen.** Setiap kali pentadbir mahu tambah jenis dokumen baharu, pengaturcara **mesti** ubah skema dan tulis kod baharu

**Penyelesaian EAV:** Struktur medan disimpan sebagai **data**, bukan sebagai lajur pangkalan data yang tegar.

```
jenis_dokumen (Entity/Kategori)
      |
      | 1-ke-banyak
      v
dokumen_field (Attribute/Templat Medan: jenis_data, pilihan, tetapan, is_required, susunan)
      |
      | setiap medan merujuk...
      v
dokumen_data (Value/Nilai Sebenar) <-- merujuk kepada dokumen (rekod sebenar)
```

Contoh: `jenis_dokumen` "Invois Perjalanan" ada beberapa `dokumen_field` (No. Invois: Teks, Jumlah: Wang (RM), Status Bayaran: Dropdown). Bila satu invois dimuat naik, satu rekod `dokumen` dicipta dan setiap jawapan disimpan sebagai satu baris dalam `dokumen_data` (`dokumen_id` + `dokumen_field_id` + `nilai_data`).

**Kebaikan:** Pentadbir boleh cipta jenis dokumen baharu dengan medan sendiri **terus dari UI**. Prompt AI juga dibina daripada definisi medan, jadi kategori baharu terus serasi dengan Imbas & Arkib tanpa kod tambahan. **Jangan** tambah lajur atau jadual khusus bagi sesuatu jenis dokumen.

**Had (trade-off jujur):** Carian berstruktur ke atas nilai medan (cth. "Jumlah > RM1000") tidak boleh guna `WHERE` terus kerana nilai disimpan sebagai **baris**, bukan **lajur**. Carian kata kunci merentas semua nilai medan sudah disokong; penapis julat berstruktur belum dibina (Bahagian 12).

### 3.3 Struktur Folder Utama

```
app/
├── Console/Commands/     <- BersihImbasArkib (imbas-arkib:bersih)
├── Http/
│   ├── Controllers/      <- Satu controller bagi setiap modul
│   └── Middleware/       <- SemakPeranan, WajibTukarKataLaluan
├── Models/               <- Satu model bagi setiap jadual domain
├── Notifications/        <- Emel pendaftaran diluluskan/ditolak
├── Observers/            <- Log audit automatik
├── Policies/             <- DokumenPolicy
├── Providers/            <- AppServiceProvider (observer, policy, paginasi, emel BM)
├── Services/             <- OcrService, VlmOcrService, OllamaExtractionService
└── Support/              <- JenisMedan, MedanJadual, TemplatKategori, BuangGarisan, dll.

bootstrap/app.php         <- Alias middleware (peranan, wajib.tukar)
config/services.php       <- Konfigurasi OCR, GLM-OCR & Ollama
lang/ms/, lang/ms.json    <- Terjemahan BM (validasi, auth, kata laluan, paginasi)
resources/views/          <- Fail Blade, satu folder bagi setiap modul; errors/ = halaman ralat BM
routes/web.php            <- Semua laluan aplikasi
routes/console.php        <- Jadual imbas-arkib:bersih (harian) + arahan ai:panaskan
tests/                    <- Ujian automatik (SQLite dalam memori)
```

---

## 4. Struktur Pangkalan Data Penuh

### 4.0 Pengurusan Skema

Skema `dms_pkink_db` diurus **secara manual dalam HeidiSQL** (dump SQL + skrip SQL bernombor). Folder `database/migrations/` hanya mengandungi fail asal Laravel, dan tiada jadual `migrations`. Akibatnya:

- **Jangan** jalankan `php artisan migrate`, `migrate:fresh`, `migrate:refresh`, `db:wipe` atau `db:seed` ke atas pangkalan data sebenar, kerana ia boleh memadam rekod sebenar
- Perubahan skema diberi sebagai skrip SQL untuk dijalankan dalam HeidiSQL
- **Keunikan yang selamat soft delete** dikuatkuasakan melalui lajur janaan (generated column) yang bernilai `NULL` bila rekod dipadam: `email_aktif`, `ic_pekerja_aktif`, `kod_bahagian_aktif`, `kod_dokumen_aktif`, `kod_field_aktif`, serta `ic_menunggu` / `email_menunggu` (pendaftaran staf)

Pangkalan data mengandungi **19 jadual**: 13 jadual domain dalam 6 kumpulan, `notifications`, `push_subscriptions`, dan 4 jadual infra Laravel.

### 4.1 Kumpulan: Organisasi & Kawalan Akses

**`bahagian`**: Senarai bahagian/jabatan dalam PKINK

| Lajur | Jenis | Catatan |
|---|---|---|
| bahagian_id | BIGINT (PK) | |
| kod_bahagian | VARCHAR(50) | Unik melalui `kod_bahagian_aktif` (selamat soft delete) |
| nama_bahagian | VARCHAR(255) | |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`users`**: Akaun kakitangan

| Lajur | Jenis | Catatan |
|---|---|---|
| user_id | BIGINT (PK) | |
| ic_pekerja | VARCHAR(20) | No. KP 12 digit, **digunakan untuk log masuk**; unik melalui `ic_pekerja_aktif` |
| nama_staff | VARCHAR(255) | |
| jawatan | VARCHAR(150) | Pilihan; sumber auto-isi borang |
| email | VARCHAR(255) | Untuk tetapan semula kata laluan & notifikasi; unik melalui `email_aktif` |
| no_telefon | VARCHAR(20) | Pilihan; sumber auto-isi borang |
| password | VARCHAR(255) | Hash bcrypt |
| perlu_tukar_kata_laluan | TINYINT(1) | 1 = kata laluan sementara, wajib ditukar pada log masuk |
| kata_laluan_tamat_pada | TIMESTAMP | Tamat tempoh kata laluan sementara (72 jam, daripada emel pendaftaran) |
| bahagian_id | BIGINT (FK) | → bahagian |
| remember_token | VARCHAR(100) | Token "ingat saya" (tidak pernah dilog) |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`roles`**: 8 peranan sistem (Bahagian 6)

| Lajur | Jenis | Catatan |
|---|---|---|
| roles_id | BIGINT (PK) | |
| kod_roles | VARCHAR(50) | Unik. SUPERADMIN, ADMIN, KERANI, PENGURUS, STAFF, PENYOKONG, PELULUS, AUDIT |
| nama_roles | VARCHAR(100) | Nama paparan, cth. "Pentadbir Sistem", "Kerani Arkib" |

**`user_roles`**: Pivot banyak-ke-banyak antara users & roles. Seorang pengguna boleh ada beberapa peranan.

| Lajur | Jenis | Catatan |
|---|---|---|
| user_roles_id | BIGINT (PK) | |
| user_id | BIGINT (FK) | → users |
| role_id | BIGINT (FK) | → roles. **Perhatian:** lajur pivot ialah `role_id`, manakala PK `roles` ialah `roles_id` |

**`pendaftaran_staf`**: Permohonan akaun daripada staf baharu (borang awam di halaman log masuk)

| Lajur | Jenis | Catatan |
|---|---|---|
| pendaftaran_staf_id | BIGINT (PK) | |
| ic_pekerja, nama_staff, email | VARCHAR | Maklumat pemohon |
| bahagian_id | BIGINT (FK) | → bahagian |
| status | VARCHAR(20) | Menunggu / Diluluskan / Ditolak |
| sebab_tolak | TEXT | Wajib bila ditolak |
| disemak_oleh, disemak_pada | BIGINT (FK), TIMESTAMP | Pentadbir yang memproses |
| user_id | BIGINT (FK) | Akaun yang dicipta bila diluluskan |
| alamat_ip | VARCHAR(45) | |
| ic_menunggu, email_menunggu | Lajur janaan UNIQUE | Hanya satu permohonan "Menunggu" bagi setiap No. KP/emel |

### 4.2 Kumpulan: Templat Dokumen (EAV)

**`jenis_dokumen`**: Kategori dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| jenis_dokumen_id | BIGINT (PK) | |
| kod_dokumen | VARCHAR(50) | Unik melalui `kod_dokumen_aktif` |
| nama_dokumen | VARCHAR(255) | |
| kategori | VARCHAR(100) | Kumpulan umum (cth. Digital, Kewangan, Memo); mengumpul kad di halaman Isi Borang |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`dokumen_field`**: Definisi medan EAV bagi setiap kategori

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_field_id | BIGINT (PK) | |
| jenis_dokumen_id | BIGINT (FK) | → jenis_dokumen |
| nama_field | VARCHAR(100) | Label dipaparkan |
| kod_field | VARCHAR(100) | Unik dalam satu kategori melalui `kod_field_aktif` |
| jenis_data | VARCHAR(50) | Salah satu daripada 16 jenis (Bahagian 5.4) |
| pilihan | JSON | Senarai pilihan (dropdown/radio/kotak semak) atau definisi lajur (jadual) |
| tetapan | JSON | `bantuan`, `lain`, `tunjuk_jika`, `auto_isi`, `pejabat` (Bahagian 5.4) |
| is_required | TINYINT(1) | Wajib diisi |
| susunan | INT | Urutan paparan |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

### 4.3 Kumpulan: Dokumen Sebenar

**`dokumen`**: Rekod dokumen yang dimuat naik

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_id | BIGINT (PK) | |
| jenis_dokumen_id | BIGINT (FK) | → jenis_dokumen |
| bahagian_id | BIGINT (FK) | → bahagian (pemilik dokumen) |
| created_by | BIGINT (FK) | → users (pemuat naik) |
| no_rujukan | VARCHAR(100) | Unik, dijana automatik: `{KOD}/{TAHUN}/{TURUTAN 4-digit}` |
| tarikh_dokumen | DATE | |
| perkara | VARCHAR(255) | Ringkasan (pilihan) |
| status | VARCHAR(50) | Sentiasa "Archived"; tidak dipaparkan (status sebenar dalam `borang`) |
| created_at, updated_at, deleted_at | TIMESTAMP | Soft delete |

**`dokumen_data`**: Nilai setiap medan EAV bagi setiap dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_data_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | → dokumen |
| dokumen_field_id | BIGINT (FK) | → dokumen_field |
| nilai_data | LONGTEXT | Semua jenis disimpan sebagai teks; kotak semak & jadual sebagai JSON |

### 4.4 Kumpulan: Imbasan & OCR

**`dokumen_scan`**: Fail imbasan (PDF/JPG/PNG) bagi setiap dokumen

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_scan_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | → dokumen |
| nama_fail | VARCHAR(255) | Nama asal fail |
| lokasi_fail | VARCHAR(500) | Laluan dalam storan **peribadi** (disk `local`); nama fail dijana rawak |
| saiz_fail | BIGINT | Saiz (bait), diisi automatik |
| jenis_mime | VARCHAR(100) | Jenis fail sebenar, diisi automatik |
| hash_sha256 | CHAR(64) | Checksum untuk semakan integriti (`fail:integriti`) |
| tarikh_scan | DATETIME | |
| status_ocr | VARCHAR(50) | Pending / Selesai / Ralat / Tiada Teks Dikesan |

**`dokumen_ocr`**: Hasil OCR bagi setiap fail imbasan

| Lajur | Jenis | Catatan |
|---|---|---|
| dokumen_ocr_id | BIGINT (PK) | |
| dokumen_scan_id | BIGINT (FK) | → dokumen_scan |
| teks_dibaca | LONGTEXT | Teks penuh hasil OCR (ikut baris) |
| skor_padanan | DECIMAL(5,2) | Skor keyakinan purata Tesseract (0–100) |
| status_semakan | VARCHAR(50) | Belum Disemak / Disemak / Dibetulkan (butang dalam panel teks OCR) |

### 4.5 Kumpulan: Aliran Kelulusan

**`borang`**: Status permohonan kelulusan bagi setiap dokumen (satu borang bagi setiap dokumen)

| Lajur | Jenis | Catatan |
|---|---|---|
| borang_id | BIGINT (PK) | |
| dokumen_id | BIGINT (FK) | → dokumen |
| pemohon_id | BIGINT (FK) | → users. Pemohon tidak boleh bertindak atas permohonan sendiri |
| pegawai_penyokong_id, pegawai_pelulus_id | BIGINT (FK, nullable) | → users |
| pegawai_penolak_id | BIGINT (FK, nullable) | → users; pegawai yang menolak (dikosongkan bila hantar semula) |
| status_permohonan | VARCHAR(50) | Dalam Proses / Disokong / Diluluskan / Ditolak (tiada rekod = Belum Dihantar) |
| tarikh_permohonan, tarikh_sokongan, tarikh_kelulusan, tarikh_tolak | DATETIME | |
| ulasan_penyokong, ulasan_pelulus | TEXT | Wajib bila menolak |

**`borang_sejarah`** (P4): satu baris bagi setiap tindakan (Hantar, Hantar Semula, Sokong, Lulus, Tolak, Arkib Terus), ditulis oleh `BorangSejarahObserver` sahaja: `borang_id`, `tindakan`, `status_selepas`, `user_id`, `bagi_pihak_id` (pegawai asal bila melalui delegasi), `ulasan`, `created_at`. Hantar semula menimpa `borang` tetapi sejarah kekal.

**`delegasi_kelulusan`** (P5): `pemberi_id`, `penerima_id`, `kod_peranan` (PENYOKONG/PELULUS, CHECK), `tarikh_mula`, `tarikh_tamat` (CHECK ≥ mula), `sebab`, `dicipta_oleh`, `dibatalkan_pada` (tiada padam: dibatalkan sahaja).

### 4.6 Kumpulan: Audit & Infra Laravel

**`log_audit`**: Jejak setiap tindakan dalam sistem

| Lajur | Jenis | Catatan |
|---|---|---|
| log_audit_id | BIGINT (PK) | |
| user_id | BIGINT (FK, nullable) | Pelaku tindakan; `NULL` = sistem (arahan konsol/penjadual) atau tetamu |
| tindakan | VARCHAR(100) | Cipta / Kemaskini / Padam (Soft Delete) / Hantar untuk Kelulusan / Lulus Pendaftaran / dll. |
| nama_jadual | VARCHAR(100) | Jadual yang terlibat |
| rekod_id | BIGINT | ID rekod |
| data_lama, data_baharu | JSON | Sebelum & selepas (`password`/`remember_token` sentiasa ditapis) |
| alamat_ip, peranti_pengguna | VARCHAR / TEXT | |
| created_at | TIMESTAMP | Tiada `updated_at`: log tidak boleh diubah |

**`log_masuk`** (P13): setiap cubaan log masuk: `user_id` (NULL bila No. KP tidak berdaftar; No. KP yang dicuba **tidak** disimpan), `berjaya`, `sebab` (Kata laluan salah, Kod 2FA salah, Disekat, Kod pemulihan digunakan, ...), `alamat_ip`, `peranti_pengguna`, `created_at`.

**`pembetulan_ai`** (P16): nilai AI berbanding nilai akhir staf bagi setiap medan semasa Imbas & Arkib/Hantar: `dokumen_id`, `jenis_dokumen_id`, `dokumen_field_id`, `nilai_ai`, `nilai_disimpan`, `sama`, `model_ai`, `user_id`.

**Lajur tambahan (Fasa P):** `jenis_dokumen.tempoh_simpanan_tahun` (NULL = kekal) & `tindakan_tamat_tempoh`; `dokumen.tarikh_tamat_simpanan`, `status_pelupusan`, `tarikh_pelupusan`, `pelupusan_oleh`, `catatan_pelupusan` (P12); `users.dua_faktor_rahsia`, `dua_faktor_kod_pemulihan` (kedua-duanya disulitkan APP_KEY), `dua_faktor_disahkan_pada` (P13).

**`push_subscriptions`**: langganan notifikasi peranti (Web Push): `subscribable_id` = `user_id`, `endpoint` (unik, ASCII), `public_key`, `auth_token`, `content_encoding`.

**`notifications`**: notifikasi dalam sistem (UUID, `notifiable_id` = `user_id`, `data` JSON: jenis, tajuk, mesej, url, ikon, warna, peranan; `read_at`).

**`password_reset_tokens`**, **`sessions`**, **`cache`**, **`cache_locks`**: jadual infra Laravel (token tetapan semula kata laluan, sesi log masuk, cache dan had kadar).

---

## 5. Modul-Modul Sistem (Terperinci)

### 5.1 Pengesahan & Akaun

- **Log masuk guna No. KP + kata laluan.** Sesi dijana semula selepas log masuk. Had cubaan: 5 kali seminit bagi setiap No. KP + IP, dan 20 kali setiap 10 minit bagi setiap IP
- **Laluan tetamu** (log masuk, lupa/tetap semula kata laluan) dilindungi middleware `guest`; pengguna yang sudah log masuk dibawa ke papan pemuka
- **Lupa kata laluan:** pautan tetapan semula dihantar ke emel (kandungan emel dalam BM). Respons **sama** sama ada emel berdaftar, tidak berdaftar atau SMTP gagal, supaya borang tidak boleh digunakan untuk meneka emel. Had: 5 seminit bagi setiap emel + IP, 10 setiap 10 minit bagi setiap IP
- **Polisi kata laluan** (`PolisiKataLaluan`): sekurang-kurangnya 8 aksara, mengandungi huruf dan nombor. Terpakai untuk kata laluan yang dipilih pengguna sendiri
- **Pengesahan dua faktor (2FA, P13):** pilihan dari Profil (QR untuk Google/Microsoft Authenticator, 8 kod pemulihan sekali guna, rahsia disulitkan). Kata laluan disemak dengan `Auth::validate()` dahulu; akaun ber-2FA dibawa ke `/log-masuk/dua-faktor` (sesi menunggu sah 5 minit, 5 cubaan seminit) sebelum sesi dibuka. `DUA_FAKTOR_WAJIB=true` (lalai `false` hingga serahan) memaksa SUPERADMIN/ADMIN mendaftar (middleware `wajib.dua.faktor`) dan menghalang mereka mematikannya
- **Sejarah log masuk (P13):** setiap cubaan direkod dalam `log_masuk`; halaman **Sejarah Log Masuk** (SUPERADMIN/AUDIT) dengan penapis, dan Profil memaparkan 5 log masuk terkini pengguna
- **Kata laluan sementara:** kata laluan yang ditetapkan pentadbir untuk orang lain, dan kata laluan dalam emel pendaftaran, mesti ditukar pada log masuk seterusnya (middleware `wajib.tukar` → `/tukar-kata-laluan`). Kata laluan sementara daripada emel pendaftaran tamat selepas 72 jam

### 5.2 Pendaftaran Staf Baharu

1. Staf baharu klik **"Daftar Akaun Baharu"** di halaman log masuk dan isi No. KP, nama, emel dan bahagian
2. Respons berjaya yang **sama** untuk permohonan baharu dan pendua (tiada pendedahan sama ada No. KP/emel berdaftar). Had 5 permohonan setiap 10 minit bagi setiap IP, dengan medan honeypot anti-bot
3. SUPERADMIN/ADMIN semak di **Pengesahan Pendaftaran** (`/pendaftaran`, tab Menunggu/Diluluskan/Ditolak/Semua). Pendua akaun ditanda dan butang Lulus dinyahaktifkan
4. **Lulus:** pilih peranan, akaun dicipta dengan kata laluan sementara, dan emel BM dihantar. **Tolak:** sebab wajib, dan emel penolakan dihantar
5. Emel dihantar dalam transaksi pangkalan data: jika emel gagal, tindakan dibatalkan dan permohonan kekal "Menunggu"

### 5.3 Profil Pengguna

Halaman `/profil` (pautan daripada nama pengguna di topbar dan sidebar): ubah nama, emel (perlu kata laluan semasa), jawatan dan no. telefon. No. KP dan bahagian hanya boleh diubah oleh pentadbir. Tukar kata laluan memerlukan kata laluan semasa, mesti mengikut polisi, dan mesti berbeza. Jawatan dan telefon digunakan sebagai sumber **auto-isi** borang.

### 5.4 Papan Pemuka (Dashboard)

Satu **bahagian bagi setiap peranan**, disusun ikut keutamaan (tugas yang memerlukan tindakan dahulu); pengguna berbilang peranan melihat beberapa bahagian. Setiap bahagian menjawab: *apa yang perlu saya buat*, *apa status saya*, dan (pentadbir) *apa trendnya*. Kepala: ucapan ikut waktu, cip peranan & bahagian, serta **butang pantas** ikut peranan (maks 4).

| Peranan | Kad (maks 4) | Panel |
|---|---|---|
| STAFF | Perlu Tindakan (ditolak), Sedang Diproses, Diluluskan, Belum Dihantar | 5 dokumen terkini + status + langkah seterusnya |
| PENGURUS | Dokumen bulan ini, Menunggu Kelulusan, Diluluskan bulan ini, Ditolak (bahagian sendiri) | Permohonan bahagian terkini, kategori teratas |
| KERANI | Dimuat naik / diarkib bulan ini, Fail belum di-OCR (termasuk Ralat), Jumlah dokumen | Dokumen terkini |
| PENYOKONG / PELULUS | Menunggu, Tertunggak > 3 hari, Paling lama menunggu, Selesai bulan ini | Giliran paling lama dahulu (permohonan sendiri tidak termasuk) |
| AUDIT | Log hari ini, Log 7 hari, Tindakan padam, Pengguna aktif | Log terkini, pecahan tindakan |
| ADMIN / SUPERADMIN | Jumlah dokumen, Permohonan aktif, Purata kelulusan, Kakitangan | **Perlu Perhatian** (pendaftaran, tertunggak, OCR), aliran kelulusan, trend 6 bulan, analitik OCR/AI & kategori/bahagian (berlipat), dokumen terkini |

Responsif: kad 4 lajur (≥1200px) / 2×2 (tablet & telefon); jadual menjadi senarai kad di bawah 576px; carta ialah bar HTML. SQL papan pemuka mudah alih (tiada fungsi khusus MySQL) supaya `DashboardTest` boleh berjalan atas SQLite. Fail: `DashboardController`, `dashboard/index.blade.php`, `dashboard/partials/*`.

**Loceng topbar (semua peranan):** notifikasi dalam sistem (jadual `notifications`), dengan badge belum dibaca, 6 terkini, "Tandakan semua dibaca" dan halaman `/notifikasi`; disemak setiap 30 s dengan popup dalam halaman bila notifikasi baharu masuk, dan ditapis ikut peranan aktif. Penerima (`App\Support\Notifikasi`, observer `BorangNotifikasiObserver` selepas commit): permohonan dihantar → PENYOKONG; disokong → pemohon + PELULUS; diluluskan/ditolak → pemohon; pendaftaran staf baharu dan sandaran/integriti gagal → SUPERADMIN/ADMIN. Menu loceng turut memaparkan giliran kelulusan dan pendaftaran yang menunggu tindakan.

**Notifikasi peranti (Web Push):** butang **Hidupkan** dalam menu loceng meminta izin pelayar dan menyimpan langganan peranti (jadual `push_subscriptions`). Setiap notifikasi kemudian turut dipaparkan sebagai notifikasi sistem pengendalian (Windows/Android/Mac) walaupun tab atau pelayar ditutup; klik membuka dokumen berkaitan. Pakej `laravel-notification-channels/webpush`, kunci VAPID dalam `.env`, service worker `public/sw.js`, manifest PWA `public/manifest.webmanifest` (ikon `public/ikon/`). Hanya endpoint perkhidmatan push yang dikenali diterima (FCM, Mozilla, WNS, Apple) dan klien HTTP tidak mengikut redirect (`config/webpush.php`). Langganan berpindah kepada pengguna yang log masuk pada peranti itu, dan dibuang semasa log keluar. Had: memerlukan HTTPS (atau localhost); iPhone hanya selepas "Tambah ke Skrin Utama" (iOS 16.4+); notifikasi pelayar mesti dibenarkan dalam tetapan sistem pengendalian.

### 5.5 Pengurusan Kategori Dokumen (`jenis_dokumen`)

CRUD penuh dengan soft delete (SUPERADMIN/ADMIN). Senarai memaparkan bilangan medan setiap kategori, dan kad **Tanpa Medan** menandakan kategori yang belum lengkap. Padam kategori yang masih ada dokumen memaparkan amaran bilangan dokumen: dokumen kekal dalam arkib, tetapi kategori tidak lagi boleh dipilih untuk muat naik.

**Pembina medan EAV** (JavaScript, disahkan semula di pelayan) dengan **16 jenis data** (`App\Support\JenisMedan`):

| Kumpulan | Jenis |
|---|---|
| Teks | Teks, Textarea Panjang, Emel, No. Telefon / Faks, Pautan (URL) |
| Nombor & masa | Nombor, Wang (RM), Tarikh, Masa, Tarikh & Masa |
| Pilihan | Dropdown, Pilihan Tunggal (Radio), Kotak Semak (Banyak Pilihan), Ya / Tidak |
| Struktur | Jadual (baris berulang atau baris tetap), Tajuk Seksyen (paparan sahaja, tiada nilai) |

**Tetapan setiap medan** (`dokumen_field.tetapan`, JSON):

| Tetapan | Maksud |
|---|---|
| `bantuan` | Teks bantuan di bawah label (bagi seksyen: keterangan seksyen) |
| `lain` | Tambah pilihan "Lain-lain (nyatakan)" bagi dropdown/radio/kotak semak |
| `tunjuk_jika` | Medan bersyarat: hanya dipaparkan, disahkan dan disimpan jika medan lain bernilai tertentu |
| `auto_isi` | Diisi & dikunci daripada profil pemohon (nama, No. KP, emel, bahagian, jawatan, telefon). Pelayan menulis semula nilai ini, jadi ia tidak boleh dipalsukan |
| `pejabat` | (Tajuk seksyen) seksyen "untuk kegunaan pejabat": tidak wajib bagi pemohon, disembunyikan dan tidak dihantar kepada AI dalam Imbas & Hantar |

**Templat permulaan** (`TemplatKategori`): Memo Dalaman, Surat Masuk, Surat Keluar, Minit Mesyuarat, Borang Permohonan, atau salin medan kategori sedia ada. **Amaran tukar jenis:** pelayan menolak penukaran jenis data jika ada nilai lama yang tidak serasi, kecuali "Teruskan tukar jenis" ditanda (nilai lama tidak dipadam).

### 5.6 Pengurusan Dokumen: Modul Teras

**Muat Naik / Isi Digital:** pilih Jenis Dokumen, dan medan yang relevan dirender oleh pelayan (endpoint `jenis-dokumen.medan`, termasuk nilai lama & ralat selepas validasi gagal). Fail imbasan (PDF/JPG/PNG, maks 10MB setiap fail, boleh berbilang) adalah **pilihan**, kerana borang boleh diisi secara digital sepenuhnya. Dua butang: **Simpan** (tanpa hantar) dan **Simpan & Hantar** (terus mencipta permohonan Dalam Proses). No. Rujukan dijana `{KOD}/{TAHUN}/{TURUTAN}` daripada nombor tertinggi sedia ada (termasuk rekod terpadam) dengan `lockForUpdate`, dan dicuba semula sehingga 3 kali jika bertembung, supaya nombor tidak pernah digunakan semula. Hanya medan milik kategori yang dipilih disimpan. Muat naik biasa **tidak** menjalankan OCR, supaya borang tidak tersekat.

**Senarai:** carian kata kunci merentas No. Rujukan, Perkara, nama Jenis Dokumen, **nilai medan EAV**, nama fail imbasan dan **teks OCR** fail imbasan (kandungan dokumen imbasan boleh dicari). Penapis ikut Jenis Dokumen, Bahagian dan julat Tarikh, dengan 4 pilihan susunan dan paginasi. Bila satu Jenis Dokumen dipilih, panel **Penapis medan** memaparkan medan kategori itu: julat bagi nombor/wang/tarikh (dibanding secara numerik melalui `CAST`, mudah alih MySQL/SQLite) dan padanan tepat bagi pilihan; penapis aktif dipapar sebagai cip dan turut digunakan oleh eksport. Lajur Status memaparkan **status kelulusan** (Belum Dihantar / Dalam Proses / Disokong / Diluluskan / Ditolak). Butang **Eksport CSV** memuat turun senarai ikut carian/penapis semasa dan skop peranan (UTF-8 dengan BOM, terus dibuka dalam Excel); setiap eksport dilog.

**Butiran:** nilai medan ikut susunan kategori dengan tajuk seksyen, senarai fail imbasan (dibuka melalui laluan terlindung, Bahagian 7) dengan **pratonton** PDF/imej dalam halaman (fail pertama terbuka, yang lain dimuat bila dibuka), butang **Jalankan OCR** dan teks OCR bagi setiap fail, serta panel Status Kelulusan dengan tindakan ikut peranan.

**Muat turun bertera air** (`dokumen.scan.muat-turun`, `TeraAirService`): salinan dengan teks pepenjuru "SALINAN TERKAWAL PKINK" dan jalur nama pemuat turun, tarikh/masa serta No. Rujukan pada setiap muka surat. Imej ditera terus dengan GD; PDF dirasterkan dengan `pdftoppm` (150dpi) dan disusun semula dengan FPDF (FPDI percuma tidak menyokong PDF ber-xref stream daripada pengimbas). Fail asal tidak diubah (hash integriti kekal sah) dan setiap muat turun dilog "Muat Turun Fail". Fon: `TERA_AIR_FON`, atau Arial Bold / DejaVu Sans Bold, atau fon bitmap GD. Tera air ialah penanda jejak, bukan DRM: pautan nama fail masih membuka fail asal untuk pratonton.

**Kemaskini:** ubah tarikh/perkara/nilai medan, buang atau tambah fail. Dokumen yang ada fail mesti kekal sekurang-kurangnya satu fail; dokumen digital boleh tanpa fail. Medan auto-isi kekal nilai pemohon asal. Fail yang sempat ditulis dipadam jika transaksi gagal.

**Padam:** soft delete. Rekod, nilai medan dan fail fizikal **dikekalkan** untuk arkib, dan No. Rujukan tidak digunakan semula.

**Tempoh simpanan & pelupusan (P12):** setiap kategori boleh ada tempoh simpanan (tahun, kira dari Tarikh Dokumen) dan tindakan disyorkan (Semak Semula, Musnah, Serah Arkib Negara). `dokumen.tarikh_tamat_simpanan` dikira automatik semasa simpan, dan dikira semula bila tempoh kategori berubah (kecuali dokumen yang telah dikekalkan). Halaman **Pelupusan** (`/pelupusan`, SUPERADMIN/ADMIN, lencana bilangan dalam menu) dengan tab Tamat Tempoh / Akan Tamat (90 hari) / Telah Dilupuskan: **Kekalkan** (+1/2/5/10 tahun, catatan wajib) atau **Lupuskan / Diserah Arkib Negara** (catatan wajib): dokumen ditanda dan dipindah ke Tong Sampah, fail **kekal** dalam storan. Memulihkannya dari Tong Sampah mengosongkan keputusan pelupusan. Setiap keputusan dilog ("Kekalkan Dokumen", "Lupus Dokumen").

**Tong Sampah** (`/tong-sampah`, SUPERADMIN/ADMIN): senarai dokumen, kategori, bahagian dan kakitangan yang dipadam (tab dengan bilangan), siapa memadam dan bila (daripada log audit), serta butang **Pulihkan**. Memulihkan kategori turut memulihkan medan yang dipadam bersamanya. ADMIN tidak boleh memulihkan akaun Super Admin. Jika kod/emel/No. KP sudah digunakan oleh rekod aktif lain, pemulihan ditolak dengan mesej. Tiada padam kekal (sistem arkib). Pemulihan dilog sebagai "Pulihkan".

**Amaran padam beramai:** bila seorang pengguna memadam 5 rekod (dokumen/kategori/bahagian/kakitangan) dalam 10 minit, SUPERADMIN/ADMIN menerima notifikasi amaran sekali (`LogAudit::AMARAN_PADAM_*`).

### 5.7 Isi Borang (Pintu Masuk Pemohon)

Halaman `/isi-borang` memaparkan kad setiap kategori, dikumpul ikut kumpulan, dengan carian. Setiap kad menunjukkan bilangan medan dan bilangan medan yang diisi daripada profil, serta dua pilihan: **Isi Digital** (borang Muat Naik dengan kategori dipilih) atau **Imbas & Hantar**. Kategori tanpa medan hanya ada butang **Muat Naik**.

### 5.8 Imbas & Arkib dan Imbas & Hantar (OCR + AI)

Dua aliran yang berkongsi kod (`ImbasHantarController` mewarisi `ImbasArkibController`, yang mewarisi `DokumenController`):

| | Imbas & Arkib | Imbas & Hantar |
|---|---|---|
| Untuk | Dokumen fizikal lama yang **sudah lulus** | Borang kertas yang baru diisi oleh pemohon sendiri |
| Peranan | KERANI, ADMIN, SUPERADMIN | Semua peranan muat naik (termasuk STAFF, PENGURUS) |
| Auto-isi profil | Tidak terpakai | Mengatasi nilai AI |
| Seksyen pejabat | Diisi seperti biasa | Disembunyikan dan tidak dihantar kepada AI |
| Pengesahan | Semakan wajib | Semakan wajib + kotak pengesahan |
| Status `borang` | **Diluluskan** terus | **Dalam Proses** (masuk aliran kelulusan) |

**Langkah 1, Analisis:** pilih Jenis Dokumen + muat naik **satu** fail. Sistem menjalankan OCR (5.10) dan menghantar teks + senarai medan kepada AI SEA-LION tempatan, yang memulangkan cadangan nilai dalam JSON (termasuk Tarikh Dokumen & Perkara). Fail sementara dan hasil disimpan dalam `storage/app/private/ocr_sementara/{token}/` (`meta.json`), terikat kepada pengguna dan mod, dan tamat selepas 120 minit. Kegagalan OCR/AI tidak menghalang semakan: amaran dipaparkan dan medan diisi secara manual.

**Langkah 2, Semak & Sahkan** (`GET semak/{token}`): borang dengan label **"Diisi AI"**, pratonton fail imbasan di sebelah, skor keyakinan dan masa proses. Butang **"Baca semula (tulisan tangan)"** memaksa GLM-OCR. Muat semula halaman tidak menjalankan OCR semula, dan ralat validasi kembali ke skrin semak dengan nilai terisi.

**Simpan** (`POST simpan/{token}`): hanya tarikh, perkara dan nilai medan diterima daripada pelayar; fail, teks OCR dan skor dibaca daripada `meta.json` di pelayan (tidak boleh dipalsukan). Fail **disalin** dalam transaksi, dengan cuba semula jika No. Rujukan bertembung, dan folder token dipadam selepas berjaya. Rekod `dokumen`, `dokumen_data`, `dokumen_scan`, `dokumen_ocr` dan `borang` dicipta serentak. Token milik pengguna lain, atau token daripada mod lain, memberi 404.

**Kenapa AI, bukan regex dikodkan keras?** Pentadbir boleh mencipta kategori baharu bila-bila masa. Prompt dibina daripada definisi medan, jadi ia **automatik serasi** dengan kategori baharu tanpa kod tambahan.

**Kos & privasi:** model AI dijalankan **sepenuhnya tempatan** melalui Ollama, jadi tiada bayaran berterusan dan tiada dokumen rasmi PKINK meninggalkan komputer tempatan.

**Pembersihan:** `php artisan imbas-arkib:bersih [--jam=24] [--cuba]` memadam folder token yang ditinggalkan; dijadualkan harian dalam `routes/console.php` (perlukan cron / Task Scheduler menjalankan `php artisan schedule:run`).

### 5.9 Aliran Kelulusan (`borang`)

Alur: **Hantar** (pemohon, atau sesiapa yang boleh mengedit dokumen) → **Sokong** (PENYOKONG) → **Luluskan** (PELULUS), atau **Tolak** pada mana-mana peringkat (ulasan wajib). SUPERADMIN/ADMIN boleh menyokong dan meluluskan (disengajakan), tetapi **tiada sesiapa boleh menyokong, meluluskan atau menolak permohonan sendiri**; butang disembunyikan dengan nota "Anda pemohon permohonan ini". Pegawai yang menolak dan tarikhnya direkod (`pegawai_penolak_id`, `tarikh_tolak`) dan dipaparkan sebagai "Ditolak Oleh" kepada pemohon. Hantar semula hanya dibenarkan selepas Ditolak, dan mengosongkan rekod penolakan lama. Tindakan atas status yang sudah berubah memberi halaman **409** dengan mesej BM, dan dokumen terpadam memberi 404. Senarai `/kelulusan` memaparkan giliran PENYOKONG/PELULUS (tanpa permohonan sendiri), manakala pentadbir melihat semua dengan tab penapis status (bilangan setiap status). Semua peranan boleh mencari ikut No. Rujukan atau nama pemohon.

**Sejarah permohonan (P4):** butang "Sejarah Permohonan" dalam panel Status Kelulusan memaparkan garis masa setiap tindakan (siapa, bila, ulasan, "bagi pihak"), termasuk ulasan penolakan lama selepas dihantar semula.

**Skop bahagian & delegasi (P5, `App\Support\SkopKelulusan`):** PENYOKONG hanya menyokong/menolak permohonan **bahagian dokumen** itu; jika bahagian itu tiada PENYOKONG (atau dokumen tanpa bahagian), semua PENYOKONG boleh bertindak supaya tidak tersangkut. PELULUS semua bahagian; SUPERADMIN/ADMIN tanpa skop. Skop yang sama digunakan oleh senarai Kelulusan, papan pemuka, loceng, butang Butiran, tindakan dan penerima notifikasi. **Delegasi** (`/delegasi`): PENYOKONG/PELULUS melantik pengganti untuk julat tarikh (maks 180 hari, tiada pertindihan); pentadbir boleh mengurus sesiapa. Peranan delegasi aktif dimasukkan dalam `User::semuaKodPeranan()` (`kodPerananSendiri()` = peranan sebenar), jadi menu dan kebenaran ikut serta; penerima bertindak dengan skop bahagian pemberi dan direkod "bagi pihak" dalam sejarah. Penerima dimaklumkan melalui loceng.

**Slip kelulusan PDF ber-QR** (`kelulusan.slip`, `SlipKelulusanService`, `bacon/bacon-qr-code` + FPDF): bagi permohonan Diluluskan, sesiapa yang boleh melihat dokumen boleh memuat turun slip (No. Rujukan, kategori, pemohon, penyokong, pelulus, tarikh, ulasan) dan setiap jana dilog. Kod QR membuka halaman **awam** `/sahkan-slip/{borang}/{kod}` (tanpa log masuk, had 30 permintaan/minit) yang memaparkan "Slip Sah" dengan maklumat minimum, atau "Slip Tidak Sah". `kod` = 12 aksara HMAC-SHA256 (APP_KEY) atas borang + dokumen + tarikh kelulusan (`Borang::kodPengesahan()`), jadi rekod tidak boleh diteka dan slip lama menjadi tidak sah bila permohonan dipadam atau diluluskan semula. URL dalam QR mengikut `APP_URL` (tetapkan ke alamat awam semasa demo/produksi). Menukar `APP_KEY` membatalkan semua slip lama.

**Notifikasi emel** (`NOTIFIKASI_EMEL`, **mati secara lalai**): bila dihidupkan, setiap notifikasi loceng (5.4) turut dihantar melalui emel kepada penerima yang ada alamat emel, dengan pautan "Buka dalam Sistem". Dimatikan semasa pembangunan kerana akaun ujian menggunakan domain `@pkink.com`; hidupkan semasa serahan.

### 5.10 OCR (Pengecaman Teks)

**OCR manual:** butang "Jalankan OCR" bagi setiap fail dalam halaman Butiran Dokumen. **OCR automatik:** hanya dalam Imbas & Arkib / Imbas & Hantar. Alur teknikal (`OcrService`):

1. PDF ditukar oleh `pdftoppm` kepada imej PNG 300dpi bagi setiap muka surat
2. Bagi setiap muka surat, Tesseract (`msa+eng`, output TSV) dijalankan **serentak** atas imej asal dan imej yang garisan kotak/jadualnya dibuang (`BuangGarisan`, GD); hasil berkeyakinan lebih tinggi dipilih
3. Teks dikumpul **ikut baris**, dengan skor keyakinan purata
4. Muka surat dengan skor di bawah `OCR_VLM_AMBANG` (80) dibaca semula oleh **GLM-OCR** (`VlmOcrService`); jika gagal, teks Tesseract kekal
5. Disimpan ke `dokumen_ocr`, dan status fail dikemas kini (`Selesai` / `Ralat` / `Tiada Teks Dikesan`)

**Semakan teks OCR:** dalam panel teks OCR, staf yang boleh mengedit dokumen boleh **Tandakan Disemak** atau **Betulkan Teks** (status Dibetulkan); teks asal dan baharu direkod dalam log audit.

OCR berjalan segerak dalam permintaan (had masa `OCR_HAD_MASA`, 300 s). Kegagalan OCR/AI **tidak** membatalkan dokumen induk: status ditanda `Ralat` dan butiran dilog. Mesej teknikal (laluan binari, respons Ollama) hanya dipaparkan di skrin bila `APP_DEBUG=true`.

### 5.11 Prestasi & Ketepatan OCR/AI (tempatan, tanpa kos)

| Teknik | Fail | Kesan |
|---|---|---|
| Model AI **penuh dalam GPU** (`OLLAMA_NUM_GPU=99`, `OLLAMA_NUM_CTX=4096`); jika gagal dimuat, dicuba semula dengan tetapan lalai | `OllamaExtractionService`, `config/services.php` | RTX 3050 6GB: VRAM 75% → 100%, AI ~1.7× lebih laju |
| Model kekal dimuat (`OLLAMA_KEEP_ALIVE=30m`) + **dipanaskan** bila halaman Imbas dibuka; `php artisan ai:panaskan` sebelum demo | `ImbasArkibController::panaskanAi` | Imbasan pertama tidak menunggu model dimuat (~7 s) |
| **Buang garisan kotak/jadual** sebelum OCR; Tesseract serentak atas imej asal & imej bersih (`OCR_BUANG_GARISAN`) | `App\Support\BuangGarisan`, `OcrService` | Borang huruf dalam kotak: 65% → 81% keyakinan |
| Teks OCR **kekal ikut baris** | `OcrService::uraiTsv` | AI padankan "Label: nilai" dengan lebih tepat |
| Prompt: abaikan teks bercetak borang, jangan teka, JSON padat; label borang yang tersalin dibuang | `OllamaExtractionService::binaPrompt` | Kurang nilai rekaan; jawapan lebih pendek |
| AI turut mengisi **Tarikh Dokumen & Perkara** | `ekstrak(..., medanAsas: true)` | Memo/surat/minit hampir terisi penuh |
| AI suhu 0 + seed tetap; tahun 2 digit dinormalkan (`20/9/26` → `20/9/2026`) | `OllamaExtractionService` | Hasil konsisten |
| Seksyen "Untuk kegunaan pejabat" tidak dihantar kepada AI dalam Imbas & Hantar | `JenisMedan::idMedanPejabat` | Tiada nilai rekaan bagi seksyen pegawai |

Ukuran dengan 7 dokumen ujian (data rekaan, folder `../Ujian_Borang_Diisi/`), OCR + AI:

| Dokumen | Sebelum | Selepas |
|---|---|---|
| Borang ICT (ditaip) | **gagal** (tamat masa 120 s) | 9.3 s |
| Borang Daftar Emel (huruf dalam kotak) | 20.9 s, nama/telefon salah | 15.7 s, nama/alamat/jawatan/telefon betul |
| Memo Dalaman | 9.9 s | 9.3 s, + Tarikh & Perkara |
| Surat Masuk | 15.7 s, pengirim kosong, nilai diteka | 10.5 s, pengirim/rujukan/tarikh/perkara betul |
| Minit Mesyuarat (2 muka surat) | 56.3 s | 37.1 s |

**Tulisan tangan: laluan hibrid Tesseract → GLM-OCR.** Tesseract tidak dapat membaca tulisan tangan (borang tulisan tangan dibaca sebagai "L ARY AWN AW"). Eksperimen dengan model penglihatan tempatan (Qwen3-VL 4B, GLM-OCR) menunjukkan **GLM-OCR** membaca tulisan tangan blok hampir sepenuhnya betul dalam ~2 s/muka surat, tetapi lebih lemah daripada Tesseract bagi huruf dalam kotak, kepala surat berwarna dan medan kosong. Oleh itu Tesseract kekal enjin utama, dan GLM-OCR hanya membaca muka surat berkeyakinan rendah (`OCR_VLM_AMBANG=80`, `OCR_VLM_MODEL=glm-ocr:q8_0`, `OCR_VLM_AKTIF`). Dalam ujian 7 dokumen, hanya borang tulisan tangan dinaik taraf: nama, jabatan, peralatan, justifikasi dan tarikh dibaca betul (≈28–30 s, termasuk pertukaran model dalam GPU 6GB).

Ukur setiap perubahan OCR/AI dengan 7 dokumen ujian ini, sebelum dan selepas perubahan.

**Had yang tinggal:** tanda ✓ tulisan tangan dalam jadual; medan yang KOSONG pada borang kadang-kadang diisi teks lain oleh AI (cth. "ERASED BN"); tulisan sambung (cursive) dan Jawi belum diuji; GLM-OCR pada CPU terlalu perlahan (~100 s/dokumen). Cadangan: templat zon (setiap medan dibaca daripada petaknya sendiri).

### 5.12 Pengurusan Bahagian & Kakitangan

CRUD penuh dengan soft delete (SUPERADMIN/ADMIN).

- **Bahagian:** senarai memaparkan bilangan kakitangan setiap bahagian, dan kad **Tanpa Kakitangan**. Bahagian yang masih ada kakitangan aktif atau pendaftaran menunggu **tidak boleh dipadam**; bahagian yang hanya dirujuk oleh dokumen boleh dipadam dengan amaran (dokumen kekal memaparkan nama bahagian)
- **Kakitangan:** senarai memaparkan bahagian (bahagian terpadam ditanda "Dipadam") dan **peranan**, dengan kad **Menunggu Pengesahan** (pautan ke Pengesahan Pendaftaran). Bahagian mesti wujud dan aktif
- **Peranan:** hanya SUPERADMIN boleh menetapkan peranan; kakitangan yang didaftar oleh ADMIN diberi STAFF. ADMIN tidak boleh mengurus akaun SUPERADMIN, SUPERADMIN terakhir dilindungi, dan pengguna tidak boleh memadam akaun sendiri. Kata laluan yang ditetapkan pentadbir menjadi kata laluan sementara

### 5.13 Log Audit

Cipta/Kemaskini/Padam pada `bahagian`, `jenis_dokumen`, `users`, `dokumen` dan **`dokumen_field`** (setiap medan borang dalam pembina) direkod oleh Observer; kemaskini hanya merekod lajur yang berubah. Tindakan tanpa pengguna log masuk direkod dengan `user_id` kosong dan dipaparkan sebagai "Sistem / tetamu". Perubahan yang tidak dilihat oleh observer (nilai EAV, fail imbasan, langkah kelulusan `borang`, tetapan peranan, pendaftaran staf, tukar kata laluan, tetapan semula kata laluan melalui emel, OCR manual, semakan/pembetulan teks OCR) direkod terus dalam controller dengan `LogAudit::create()`. Halaman Log Audit (SUPERADMIN & AUDIT sahaja) ada penapis pengguna/jadual/tindakan/ID rekod/julat tarikh dan paginasi, dan setiap baris boleh dibuka untuk melihat JSON sebelum/selepas. `password` dan `remember_token` **sentiasa** ditapis. Butang **Eksport CSV** memuat turun log ikut penapis semasa (eksport itu sendiri dilog).

**Status Sistem** (`/status-sistem`, SUPERADMIN/ADMIN) turut memaparkan **Ketepatan AI** (P16): peratus medan yang disimpan tanpa diubah staf bagi setiap kategori, dan medan yang paling kerap dibetulkan. Semakan langsung setiap kali dibuka, dengan status Baik/Amaran/Ralat dan nota tindakan: pangkalan data, mod debug, Tesseract (versi + pek bahasa `OCR_BAHASA`), Poppler, Ollama + model AI dan GLM-OCR (dimuat dalam memori atau belum), Web Push (kunci VAPID + OpenSSL), notifikasi emel, penjadual (degupan `penjadual:degupan` setiap minit), sandaran terakhir (`terakhir.json`), integriti fail (`integriti.json`) dan ruang cakera. Sesuai disemak sebelum demo.

### 5.14 Bahasa & Antara Muka

- **Locale BM** (`lang/ms/*`, `lang/ms.json`): mesej validasi lalai dan nama medan (cth. `ic_pekerja` → "No. Kad Pengenalan"), tarikh (`translatedFormat`, `diffForHumans`: "27 Oktober 2026", "1 hari yang lepas") dan paginasi ("Memaparkan 26 hingga 50 daripada 125 rekod")
- **Halaman ralat BM:** 403 (dalam layout aplikasi), dan 404, 405, 409, 419 (sesi tamat), 429, 500, 503 serta fallback 4xx/5xx dalam rangka berdiri sendiri (`errors/rangka.blade.php`), tanpa sesi atau pangkalan data
- **Komponen seragam** (`layouts/app.blade.php`): butang utama `.save-btn` (13px) dan sekunder `.outline-button` (12px); medan fail BM ("Pilih Fail" / "Tiada fail dipilih"); paginasi Bootstrap 5 ikut warna tema
- **Responsif:** sidebar boleh dilipat; di telefon, butang berikon menjadi ikon sahaja (kecuali `.kekal-label`), jadual senarai ditatal mendatar (`td.boleh-balut` untuk teks panjang), dan kad ringkasan 2 lajur

---

## 6. Kawalan Akses Ikut Peranan (RBAC)

Sistem ada **8 peranan**; seorang pengguna boleh memegang beberapa peranan. Kawalan dikuatkuasakan dalam **tiga lapisan yang mesti selari**:

1. Middleware `peranan:` (`SemakPeranan`) menyekat seluruh kumpulan laluan
2. `DokumenPolicy` dipanggil melalui `Gate::authorize()` dalam controller (laluan `dokumen` tiada middleware peranan)
3. `Dokumen::scopeBolehDilihat($user)` menapis query senarai, selari dengan `DokumenPolicy::view`

| Peranan | Skop Lihat Dokumen | Muat Naik / Isi Borang / Imbas & Hantar | Edit | Padam | Imbas & Arkib | Pentadbiran | Lain-lain |
|---|---|---|---|---|---|---|---|
| **SUPERADMIN** | Semua | Ya | Ya | Ya | Ya | Ya | Sahaja yang boleh tetapkan peranan & urus akaun SUPERADMIN; Log Audit |
| **ADMIN** | Semua | Ya | Ya | Ya | Ya | Ya | Sokong & lulus (kecuali permohonan sendiri) |
| **KERANI** | Semua | Ya | Ya | Ya | Ya | Lihat sahaja (senarai Kategori & Bahagian) | |
| **PENGURUS** | Bahagian sendiri | Ya | Bahagian sendiri | Tidak | Tidak | Tidak | |
| **STAFF** | Milik sendiri | Ya | Milik sendiri | Tidak | Tidak | Tidak | Hantar dokumen untuk kelulusan |
| **PENYOKONG** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Tidak | Sokong/tolak peringkat 1 |
| **PELULUS** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Tidak | Luluskan/tolak peringkat 2 |
| **AUDIT** | Semua (baca) | Tidak | Tidak | Tidak | Tidak | Tidak | Log Audit |

"Pentadbiran" = Kategori Dokumen, Pengurusan Bahagian, Pengurusan Kakitangan dan Pengesahan Pendaftaran.

**Tukar peranan:** pengguna berbilang peranan boleh klik nama/avatar di topbar dan memilih satu peranan tanpa log masuk semula. Hanya **satu peranan aktif pada satu masa** (tiada mod "semua peranan"): selepas log masuk, peranan tertinggi dipilih automatik ikut `User::SUSUNAN_PERANAN` (SUPERADMIN, ADMIN, KERANI, PENGURUS, PELULUS, PENYOKONG, AUDIT, STAFF). Peranan delegasi aktif (P5) turut disenaraikan dengan tanda "(delegasi)". Peranan aktif disimpan dalam sesi dan ditapis dalam `User::kodPeranan()`, jadi ketiga-tiga lapisan RBAC, menu dan papan pemuka ikut serentak. Peraturan pemohon tidak boleh meluluskan permohonan sendiri kekal.

Menu sidebar dan butang tindakan **disembunyikan** mengikut peranan pengguna. Akses terus melalui URL tanpa kebenaran memaparkan halaman **403 Akses Ditolak** dalam BM.

---

## 7. Keselamatan Sistem

- **Fail imbasan disimpan secara PERIBADI** (disk `local`, bukan `public`, dan tiada `storage:link`). Fail hanya boleh dicapai melalui `/dokumen/scan/{id}/papar`, yang menyemak `DokumenPolicy` dahulu. Nama fail dalam storan dijana rawak
- **Imbas & Arkib tidak mempercayai pelayar:** fail, teks OCR dan skor dibaca daripada `meta.json` milik pengguna di pelayan; token 40 aksara alfanumerik (elak path traversal), terikat pengguna dan mod, tamat 120 minit
- **Pemohon tidak boleh meluluskan permohonan sendiri** (termasuk SUPERADMIN/ADMIN)
- **Tiada pendedahan akaun:** lupa kata laluan dan pendaftaran awam memberi respons yang sama sama ada No. KP/emel berdaftar atau tidak
- **Had kadar** pada log masuk, lupa kata laluan dan pendaftaran awam; honeypot pada pendaftaran
- **Polisi kata laluan** (min 8, huruf + nombor) dan **kata laluan sementara** wajib ditukar; hash bcrypt
- **Perlindungan privilege escalation:** ADMIN tidak boleh mengedit/memadam/menukar kata laluan akaun SUPERADMIN; SUPERADMIN terakhir dilindungi
- **Lajur sensitif ditapis daripada Log Audit** (`password`, `remember_token`)
- **Suntikan dalam emel dihalang:** nama disahkan dengan regex (`PeraturanNama`), dan aksara Markdown di-escape dalam emel (`TeksEmel`)
- **Integriti fail arkib:** SHA-256 setiap fail imbasan disimpan semasa muat naik dan disemak setiap minggu (`fail:integriti`); fail hilang/berubah dilaporkan
- **Validasi keunikan selamat soft delete** melalui lajur janaan pangkalan data
- **Perlindungan CSRF** pada setiap borang POST/PUT/DELETE; halaman 419 BM bila sesi tamat
- **Subresource Integrity (SRI)** pada semua skrip/gaya CDN jsDelivr; `SriCdnTest` gagal jika tag CDN baharu tiada SRI
- **Mesej ralat teknikal tidak didedahkan dalam produksi:** butiran OCR/AI hanya bila `APP_DEBUG=true`; halaman 500 BM tanpa butiran
- **Fail muat naik selamat (Fasa Keselamatan S1, `PemeriksaFail` + peraturan `FailSelamat`):** imbasan Windows Defender (`MpCmdRun`) / ClamAV; had "bom" 50 muka surat PDF (pdfinfo) & 40 juta piksel imej; PDF `/Launch` atau fail terbenam ditolak (JavaScript dibenarkan); fail berbahaya dilog "Fail Berbahaya Ditolak" + amaran pentadbir; OCR maksimum 50 muka surat dan tidak berjalan bila ruang cakera < 1 GB; pratonton fail dengan CSP `sandbox`. Tiada antivirus = diterima + amaran, kecuali `ANTIVIRUS_WAJIB=true`
- **Pusat Keselamatan** (`/keselamatan`, SUPERADMIN/ADMIN, S2–S3): ringkasan log masuk gagal/IP/akaun disasarkan, semakan pengerasan (akaun DB root/tanpa kata laluan, MySQL terbuka pada rangkaian, debug, antivirus, 2FA & kata laluan lalai pentadbir, sandaran disulitkan, uji pemulihan, salinan luar talian, integriti), tindakan sensitif terkini. **Amaran automatik** ke loceng & telefon: 10 log masuk gagal dari satu IP dalam 10 minit (IP **disekat automatik 1 jam**), pentadbir log masuk dari IP baharu (30 hari), sandaran tertunggak 26 jam
- **Butang kecemasan** (middleware `PengawalKeselamatan`): kunci akaun (log masuk ditolak, sesi ditamatkan), log keluar paksa seorang/semua pengguna, sekat IP (localhost tidak pernah disekat), **mod kecemasan** (hanya SUPERADMIN log masuk, baca sahaja, laluan awam ditutup). Keadaan disimpan dalam `storage/app/private/keselamatan.json` (bukan cache). Jika Super Admin terkunci di luar: `php artisan keselamatan:kecemasan off|status|buka-ip`
- **Sandaran tahan ransomware (S4):** zip disulitkan AES-256 (`SANDARAN_KATA_LALUAN`), simpanan bertingkat (14 harian, 8 mingguan, 6 bulanan), `sandaran:uji` mingguan ke DB sementara, `sandaran:luar-talian` ke cakera luaran yang dicabut, `sandaran:pulih` berpandu
- **Pelan Tindak Balas Insiden (S5):** `docs/PELAN_TINDAK_BALAS_INSIDEN.md`, dibuka & dicetak dari Pusat Keselamatan: hubungan kecemasan, peraturan (jangan bayar tebusan), senario A–F termasuk ransomware/peras ugut, rutin, pemulihan, pengerasan pelayan (pengguna DB `dms_app`, kata laluan root, `bind-address=127.0.0.1`). **Had:** aplikasi tidak boleh menahan worm/virus peringkat sistem pengendalian atau penyerang yang menguasai pelayan; pertahanannya ialah pengerasan Windows/MySQL, Defender masa nyata dan sandaran luar talian
- **Kegagalan proses luaran (OCR/AI/emel) tidak merosakkan data:** OCR/AI ditanda status ralat tanpa membatalkan dokumen; emel pendaftaran gagal membatalkan transaksi; fail yang sempat ditulis dipadam bila transaksi gagal

---

## 8. Carta Alir Pengguna

### 8.1 Isi Digital / Muat Naik Dokumen (STAFF)
```
Log Masuk -> Isi Borang -> pilih kategori -> "Isi Digital"
    -> Medan EAV dipaparkan (medan profil diisi automatik & dikunci)
    -> Isi medan (+ fail imbasan, pilihan)
    -> "Simpan"            -> Dokumen tersimpan (Belum Dihantar)
    -> "Simpan & Hantar"   -> Dokumen tersimpan + Status: Dalam Proses
```

### 8.2 Imbas & Hantar (pemohon dengan borang kertas yang sudah diisi)
```
Isi Borang -> "Imbas & Hantar" -> pilih kategori + 1 fail imbasan
    -> OCR + AI isi borang digital (medan profil daripada profil, seksyen pejabat disembunyikan)
    -> Semak setiap medan bersebelahan pratonton fail -> tanda pengesahan
    -> Hantar -> Status: Dalam Proses
```

### 8.3 Aliran Kelulusan (PENYOKONG -> PELULUS)
```
Pemohon hantar -> Status: Dalam Proses
    -> PENYOKONG (bukan pemohon) sokong -> Status: Disokong
    -> PELULUS (bukan pemohon) luluskan -> Status: Diluluskan
    (Pada mana-mana peringkat: Tolak dengan ulasan wajib -> Status: Ditolak
     -> pemohon boleh betulkan & Hantar Semula)
```

### 8.4 Imbas & Arkib (dokumen lama sedia lulus; KERANI/pentadbir)
```
Imbas & Arkib -> pilih Jenis Dokumen + 1 fail
    -> OCR (Tesseract, GLM-OCR bagi tulisan tangan) -> AI (SEA-LION) cadangkan nilai
    -> Skrin Semak (label "Diisi AI", pratonton fail, "Baca semula" jika tulisan tangan)
    -> Staf semak & betulkan -> Sahkan & Simpan
    -> Dokumen tercipta terus berstatus: DILULUSKAN
```

### 8.5 Pendaftaran Staf Baharu
```
Halaman log masuk -> "Daftar Akaun Baharu" -> isi No. KP, nama, emel, bahagian
    -> Pentadbir semak di Pengesahan Pendaftaran
       -> Lulus (pilih peranan) -> emel kata laluan sementara (sah 72 jam)
          -> Log masuk -> wajib tukar kata laluan -> Papan Pemuka
       -> Tolak (sebab wajib) -> emel penolakan
```

---

## 9. Peta Fail → Lokasi Dalam Projek

### Model (`app/Models/`)
`Bahagian`, `Borang`, `Dokumen`, `DokumenData`, `DokumenField`, `DokumenOcr`, `DokumenScan`, `JenisDokumen`, `LogAudit`, `PendaftaranStaf`, `Role`, `User`

### Observer (`app/Observers/`)
`BahagianObserver`, `DokumenFieldObserver`, `DokumenObserver`, `JenisDokumenObserver`, `UserObserver` (didaftar dalam `AppServiceProvider`)

### Policy, Middleware & Notifikasi
| Fail | Peranan |
|---|---|
| `app/Policies/DokumenPolicy.php` | Kebenaran lihat/cipta/edit/padam dokumen |
| `app/Http/Middleware/SemakPeranan.php` | Alias `peranan:` |
| `app/Http/Middleware/WajibTukarKataLaluan.php` | Alias `wajib.tukar` |
| `app/Notifications/PendaftaranDiluluskan.php`, `PendaftaranDitolak.php` | Emel keputusan pendaftaran |
| `app/Notifications/NotifikasiDms.php`, `app/Support/Notifikasi.php`, `app/Observers/BorangNotifikasiObserver.php` | Notifikasi loceng & peranti (penerima ikut peranan) |
| `public/sw.js`, `public/manifest.webmanifest`, `public/ikon/`, `config/webpush.php` | Notifikasi peranti (Web Push) & PWA |

### Service (`app/Services/`)
| Fail | Peranan |
|---|---|
| `OcrService.php` | pdftoppm + Tesseract (TSV), buang garisan, laluan hibrid |
| `VlmOcrService.php` | GLM-OCR melalui Ollama untuk tulisan tangan |
| `OllamaExtractionService.php` | Prompt daripada medan EAV, ekstrak JSON, panaskan model |

### Support (`app/Support/`)
`JenisMedan` (16 jenis medan, auto-isi, seksyen pejabat), `MedanJadual` (jadual baris berulang), `LainDinyatakan`, `TemplatKategori`, `BuangGarisan`, `PolisiKataLaluan`, `PeraturanNama`, `PeraturanProfil`, `TeksEmel`

### Controller (`app/Http/Controllers/`)
| Controller | Modul |
|---|---|
| `AuthController` | Log masuk / log keluar |
| `PasswordResetController` | Lupa & tetap semula kata laluan |
| `PendaftaranStafController` | Permohonan awam + Pengesahan Pendaftaran |
| `ProfilController` | Profil, tukar kata laluan (termasuk wajib tukar) & tukar peranan aktif |
| `DashboardController` | Papan pemuka ikut peranan |
| `JenisDokumenController` | Kategori Dokumen + pembina medan + endpoint medan |
| `BahagianController`, `UserController` | Pengurusan Bahagian & Kakitangan |
| `DokumenController` | CRUD dokumen, fail imbasan, OCR manual |
| `IsiBorangController` | Halaman Isi Borang |
| `ImbasArkibController` (mewarisi `DokumenController`) | Imbas & Arkib |
| `ImbasHantarController` (mewarisi `ImbasArkibController`) | Imbas & Hantar (`mod()` = `hantar`) |
| `BorangController` | Aliran kelulusan |
| `SlipKelulusanController` | Slip kelulusan PDF ber-QR + halaman pengesahan awam |
| `LogAuditController` | Log Audit |
| `NotifikasiController` | Loceng, halaman Notifikasi, langganan peranti |
| `TongSampahController` | Tong Sampah: senarai & pulih rekod terpadam |
| `KeselamatanController` | Pusat Keselamatan, butang kecemasan, Pelan Tindak Balas Insiden |
| `StatusSistemController` | Status Sistem (OCR/AI, notifikasi, sandaran, penjadual, storan, ketepatan AI) |
| `DelegasiController` | Delegasi kelulusan semasa cuti (P5) |
| `PelupusanController` | Tempoh simpanan & pelupusan (P12) |
| `DuaFaktorController`, `LogMasukController` | 2FA dalam Profil, Sejarah Log Masuk (P13) |

### Arahan Konsol
| Arahan | Fail |
|---|---|
| `php artisan imbas-arkib:bersih [--jam=24] [--cuba]` | `app/Console/Commands/BersihImbasArkib.php` (dijadualkan harian) |
| `php artisan sandaran:jalan [--simpan=14]` | `app/Console/Commands/SandaranJalan.php` (dijadualkan 12:30 setiap hari) |
| `php artisan fail:integriti` | `app/Console/Commands/FailIntegriti.php` (dijadualkan setiap Isnin 12:45) |
| `php artisan ai:panaskan` | `routes/console.php` |
| `php artisan keselamatan:kecemasan on\|off\|status\|buka-ip [--sebab=]` | `app/Console/Commands/KeselamatanKecemasan.php` |
| `php artisan sandaran:uji [zip]` | `app/Console/Commands/SandaranUji.php` (dijadualkan setiap Rabu 13:00) |
| `php artisan sandaran:luar-talian {destinasi}` | `app/Console/Commands/SandaranLuarTalian.php` (manual, mingguan) |
| `php artisan sandaran:pulih [zip] [--pangkalan-data] [--fail-imbasan] [--ke-db=] [--ya]` | `app/Console/Commands/SandaranPulih.php` |
| `php artisan ocr:nilai [folder] [--jana-label=KOD] [--tanpa-few-shot] [--simpan=laporan.json]` | `app/Console/Commands/OcrNilai.php` (P15; lalai `../Ujian_Borang_Diisi`) |
| `php artisan demo:sedia\|hidup\|mati\|set-semula\|terowong\|semak\|kod [--percubaan]` | `app/Console/Commands/Demo*.php` (Fasa D: mod demo & mod percubaan, lihat 10.3) |

### Views (`resources/views/`)
| Folder | Kandungan |
|---|---|
| `layouts/app.blade.php` | Layout utama: sidebar, topbar, loceng, CSS & skrip bersama |
| `auth/` | `login` (+ modal pendaftaran), `forgot-password`, `reset-password`, `tukar-kata-laluan` |
| `dashboard/` | `index` + `partials/` (satu bagi setiap peranan, `kad-kpi`, `bar-senarai`, `giliran`, `gaya`) |
| `dokumen/` | `index`, `create`, `edit`, `show` + `partials/medan-*` (input, senarai, jadual, skrip, gaya) |
| `jenis_dokumen/` | `index`, `create`, `edit` + `partials/medan-script` (pembina medan) |
| `imbas_arkib/` | `create`, `semak` (dikongsi oleh Imbas & Arkib dan Imbas & Hantar) |
| `isi_borang/`, `kelulusan/`, `log_audit/`, `pendaftaran/`, `profil/` | Satu halaman setiap modul |
| `bahagian/`, `users/` | `index`, `create`, `edit` |
| `errors/` | `403` (layout aplikasi), `rangka` + `404`, `405`, `409`, `419`, `429`, `500`, `503`, `4xx`, `5xx` |

### Konfigurasi & Terjemahan
| Fail | Kandungan |
|---|---|
| `bootstrap/app.php` | Alias middleware `peranan`, `wajib.tukar` |
| `app/Providers/AppServiceProvider.php` | Observer, `DokumenPolicy`, paginasi Bootstrap 5, emel tetapan semula BM |
| `config/services.php` | `ocr.*` dan `ollama.*` (dibaca daripada `.env`) |
| `config/app.php` | Locale `ms`, zon masa `Asia/Kuala_Lumpur` |
| `lang/ms/`, `lang/ms.json` | Terjemahan BM |
| `routes/web.php`, `routes/console.php` | Laluan & jadual |

### Ujian (`tests/`)
`tests/SkemaUjian.php` mencipta skema minimum dalam SQLite `:memory:` (jadual domain tiada dalam SQLite secara lalai); `tests/Feature/*` dan `tests/Unit/*` (49 kelas ujian, 293 ujian) meliputi log masuk, pendaftaran, profil, kata laluan sementara, dokumen & transaksi fail, medan EAV, Imbas & Arkib/Hantar, OCR hibrid, AI, papan pemuka, kelulusan, halaman ralat, locale, SRI dan UI. DB & storan sebenar tidak disentuh (`Storage::fake`).

---

## 10. Panduan Pasang Sistem Dari Kosong

1. Pasang PHP 8.3+ (sambungan `gd`, `pdo_mysql`), Composer dan MySQL/MariaDB 10.4+ (disyorkan: Laravel Herd + MySQL XAMPP di Windows)
2. Clone projek dan jalankan `composer install`
3. Salin `.env.example` ke `.env`, kemudian:
   - semak `DB_DATABASE=dms_pkink_db`, `DB_USERNAME` dan `DB_PASSWORD` (`.env.example` sudah ditetapkan untuk MySQL)
   - semak `APP_LOCALE=ms`, `APP_TIMEZONE=Asia/Kuala_Lumpur`
   - tetapan emel `MAIL_*` (SMTP) untuk lupa kata laluan dan emel pendaftaran
4. Jana kunci aplikasi: `php artisan key:generate`
5. Import dump SQL `dms_pkink_db` dan jalankan skrip SQL tambahan mengikut turutan dalam HeidiSQL. **Fail SQL tidak disimpan dalam repo ini**; dapatkan daripada pembangun. **Jangan** guna `php artisan migrate`
6. **Jangan** jalankan `php artisan storage:link`: fail imbasan sengaja disimpan dalam disk peribadi `local`
7. Pasang Tesseract OCR (dengan pek `msa` + `eng`) dan Poppler, kemudian tetapkan laluan penuh dalam `.env`:
   ```
   OCR_TESSERACT_BIN="C:/Program Files/Tesseract-OCR/tesseract.exe"
   OCR_PDFTOPPM_BIN="C:/poppler/Library/bin/pdftoppm.exe"
   ```
8. Pasang Ollama, kemudian daftarkan model SEA-LION sebagai `sea-lion-local` dan tarik GLM-OCR: `ollama pull glm-ocr:q8_0`. Tetapan `OCR_*`, `OCR_VLM_*` dan `OLLAMA_*` sudah ada dalam `.env.example`
9. Notifikasi peranti: `php artisan webpush:vapid` (menulis `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` ke `.env`) dan tetapkan `VAPID_SUBJECT`. Di **Windows**, PHP perlukan pemboleh ubah persekitaran `OPENSSL_CONF` untuk mencipta kunci EC (penjanaan kunci dan penyulitan setiap mesej); tanpanya ralat "Unable to create the key". Tetapkan sekali (`setx OPENSSL_CONF "C:\Users\NAMA\.config\herd\bin\php83\extras\ssl\openssl.cnf"`), kemudian mulakan semula pelayan dalam terminal baharu. `php artisan serve` hanya menurunkan pemboleh ubah persekitaran dalam senarai putih kepada proses pelayan; `AppServiceProvider` menambah `OPENSSL_CONF` ke senarai itu. Semak hasilnya di halaman **Status Sistem**
10. Jalankan `php artisan config:clear`
11. Jalankan `php artisan serve` (atau buka melalui Herd)
12. Tetapkan sandaran dalam `.env` (`SANDARAN_MYSQLDUMP_BIN`, `SANDARAN_SALINAN`; lihat 10.2), kemudian jadualkan penjadual Laravel setiap minit melalui Windows Task Scheduler (jalankan sekali dalam PowerShell, tukar laluan ikut komputer):
    ```
    schtasks /Create /TN "DMS PKINK Penjadual" /SC MINUTE /MO 1 /F /TR 'conhost.exe --headless C:\Users\NAMA\.config\herd\bin\php83\php.exe C:\laluan\projek\artisan schedule:run'
    ```
    `conhost --headless` mengelakkan tetingkap konsol muncul setiap minit (laluan tanpa ruang kosong). Ini menjalankan `imbas-arkib:bersih` (harian) dan `sandaran:jalan` (12:30 setiap hari)
13. Sebelum demo: `php artisan ai:panaskan`

**Produksi:** tetapkan `APP_ENV=production`, `APP_DEBUG=false`, dan padam akaun ujian (Bahagian 13).

### 10.1 Arahan Pembangunan

| Arahan | Tujuan |
|---|---|
| `composer test` | Kosongkan config, kemudian jalankan semua ujian (`php artisan test`) |
| `php artisan test --filter=NamaTest` | Satu ujian |
| `vendor/bin/pint --dirty` | Format fail yang berubah |
| `php artisan config:clear` | Selepas mengubah `.env`/config |
| `php artisan sandaran:jalan` | Sandaran serta-merta (sebelum perubahan besar) |

### 10.2 Sandaran & Pemulihan

**Sandaran** (`php artisan sandaran:jalan`, dijadualkan 12:30 setiap hari) menghasilkan `sandaran_YYYYMMDD_HHMMSS.zip` yang mengandungi:
- `pangkalan_data/dms_pkink_db.sql`: mysqldump penuh (17 jadual, `--single-transaction`)
- `fail/...`: semua fail dalam `storage/app/private` (fail imbasan), kecuali `ocr_sementara`

| Tetapan `.env` | Lalai | Maksud |
|---|---|---|
| `SANDARAN_MYSQLDUMP_BIN` | `mysqldump` | Laluan penuh, cth. `C:/xampp/mysql/bin/mysqldump.exe` |
| `SANDARAN_FOLDER` | `storage/app/sandaran` | Folder sandaran utama |
| `SANDARAN_SALINAN` | (tiada) | Salinan kedua di luar projek, cth. folder OneDrive |
| `SANDARAN_SIMPAN_HARI` | 14 | Sandaran lebih lama dipadam di kedua-dua folder |

Keputusan terakhir ditulis ke `{folder}/terakhir.json` (berjaya/gagal, saiz, bilangan fail) dan ke log Laravel. Kata laluan DB dihantar melalui `MYSQL_PWD`, bukan baris arahan.

**Pemulihan** (diuji 27 Oktober 2026: semua 17 jadual dan bilangan baris sepadan, lajur janaan dikira semula):
1. Ekstrak zip sandaran ke folder sementara
2. Pangkalan data: dalam HeidiSQL cipta pangkalan data kosong (`utf8mb4_unicode_ci`), kemudian jalankan `pangkalan_data/dms_pkink_db.sql`; atau dalam PowerShell:
   ```
   & "C:\xampp\mysql\bin\mysql.exe" -u root -p dms_pkink_db -e "source C:/sementara/pangkalan_data/dms_pkink_db.sql"
   ```
3. Fail imbasan: salin kandungan folder `fail/` ke `storage/app/private/`
4. `php artisan config:clear`, kemudian log masuk dan buka beberapa dokumen untuk memastikan fail imbasan boleh dipaparkan

### 10.3 Hari Demo (peserta cuba di telefon melalui pautan)

OCR (Tesseract) dan AI (Ollama + GPU) hanya ada pada komputer riba ini, jadi komputer riba menjadi pelayan dan dibuka ke Internet melalui **Cloudflare Quick Tunnel**: percuma, HTTPS automatik, tiada port router dibuka, dan berfungsi melalui data telefon peserta.

**Persediaan sekali sahaja**
1. Laman Herd: `herd link dms-pkink` dalam folder projek → `http://dms-pkink.test` (nginx + PHP-FPM, ramai pengguna serentak; `php artisan serve` hanya satu permintaan pada satu masa).
2. Had masa nginx: fail `%USERPROFILE%\.config\herd\config\valet\Nginx\dms-pkink-masa-tamat.conf` mengandungi `fastcgi_read_timeout 300s;` (lalai 60 s memberi 504 kepada analisis OCR/AI yang menunggu giliran). Kemudian `herd restart`.
3. `winget install --id Cloudflare.cloudflared`

**Sebelum demo**
```
php artisan demo:sedia        # salin DB & fail sebenar ke dms_pkink_demo + storage/app/demo (DB sebenar hanya dibaca)
php artisan demo:hidup        # laman web guna DB/fail demo
php artisan demo:terowong     # tetingkap BERASINGAN, biarkan terbuka: cetak pautan https://….trycloudflare.com
php artisan demo:semak --panaskan   # senarai semak + muat model AI ke GPU
```
Tayang `https://….trycloudflare.com/demo` (atau `http://dms-pkink.test/demo`) pada projektor: kod QR + senarai akaun demo.

**Kod akses (kedua-dua mod)**
- `demo:hidup` menjana kod 6 aksara (dicetak di terminal, dipaparkan pada `/demo`). Setiap permintaan **melalui terowong** mesti membuka akses dengan kod ini sekali; akses di komputer ini (`dms-pkink.test`) dikecualikan.
- Kod QR pada `/demo` mengandungi kod (`…/?kod=XXXXXX`), jadi peserta hanya perlu imbas. Pautan tanpa kod memaparkan halaman "Masukkan kod akses" (had 10 cubaan seminit).
- `php artisan demo:kod` memaparkan kod semasa; `--baharu` menukarnya (semua peserta perlu masukkan semula), cth. jika gambar QR tersebar di luar dewan.

**Semasa mod demo**
- Log masuk hanya melalui butang "Masuk sebagai ADMIN/KERANI/…" (akaun `DEMO_AKAUN`); borang kata laluan, lupa kata laluan dan pendaftaran ditutup. Kata laluan semua akaun lain dalam salinan demo dirawakkan oleh `demo:sedia`.
- Tindakan yang menjejaskan peserta lain disekat dengan mesej "Tindakan ini dimatikan dalam mod demo" (urus kakitangan, sahkan pendaftaran, tindakan Pusat Keselamatan, 2FA, tukar kata laluan/profil; senarai dalam `config/demo.php`).
- `APP_DEBUG` dimatikan, emel ditulis ke log, banner "Mod Demo" dipaparkan.
- Analisis OCR/AI berjalan satu pada satu masa; peserta lain menunggu giliran hingga `OCR_TUNGGU_GILIRAN` (120 s).
- Mod demo hanya mempengaruhi permintaan web: penjadual (sandaran harian, semakan keselamatan) kekal pada DB sebenar.

**Selepas demo / antara sesi**
```
php artisan demo:set-semula   # buang semua perubahan peserta (DB + fail demo), ~1 s
php artisan demo:mati         # laman kembali kepada DB sebenar
```
Tutup tetingkap `demo:terowong` (Ctrl+C) untuk menutup pautan awam. Pautan `trycloudflare.com` berubah setiap kali terowong dimulakan, jadi cetak kod QR dari halaman `/demo` pada hari demo, bukan lebih awal. `demo:terowong` menulis degupan setiap 10 s; jika ia terhenti, alamat lama diabaikan selepas 30 s.

**Mod Percubaan (sistem penuh, selepas peserta biasa dengan mod demo)**

Peserta mendaftar akaun sendiri, pentadbir meluluskan di komputer ini, peserta menerima emel kata laluan sementara dan menggunakan semua aliran sebenar tanpa sekatan.
```
php artisan demo:sedia --percubaan --kekal=<No. KP akaun SUPERADMIN anda>   # dms_pkink_percubaan
php artisan demo:hidup --percubaan
php artisan demo:terowong       # tetingkap berasingan
# di komputer ini juga buka pautan terowong (dms-pkink.test dialihkan ke situ semasa terowong hidup)
```
- Semua akaun kecuali `--kekal` dikunci (kata laluan rawak + `dikunci_hingga`), kerana kata laluan akaun ujian tercatat dalam README 13. Akaun yang dikekalkan tidak diubah (termasuk 2FA).
- Emel SEBENAR dihantar melalui SMTP `.env` (kata laluan sementara, lupa kata laluan, notifikasi aliran kelulusan `NOTIFIKASI_EMEL` dihidupkan). Akaun yang dikunci tidak diemel.
- Semua URL (termasuk pautan dalam emel yang dijana semasa pentadbir meluluskan) ikut alamat terowong.
- IP setiap peserta diambil daripada `CF-Connecting-IP` (`IpTerowong`), jadi had percubaan log masuk & sekatan IP dikira bagi setiap peranti. Peserta dalam satu Wi-Fi berkongsi IP awam: tambah IP itu ke `KESELAMATAN_IP_DIPERCAYAI` jika perlu.
- Selepas sesi: `php artisan demo:mati` kemudian **`php artisan demo:set-semula --percubaan`** untuk memadam akaun dan data peribadi peserta (No. KP, emel).

---

## 11. Isu Yang Telah Dibaiki Sepanjang Pembangunan

| Isu | Punca | Pembetulan |
|---|---|---|
| Kebocoran `remember_token` dalam Log Audit | `UserObserver` log semua perubahan, termasuk putaran token | Lajur sensitif dikecualikan secara eksplisit |
| Log audit berganda | Observer didaftar dua kali (`#[ObservedBy]` + `AppServiceProvider`) | Satu pendaftaran pusat sahaja |
| `user_roles` sentiasa kosong | Kod guna `roles_id`, lajur pivot sebenar ialah `role_id` | Betulkan rujukan lajur |
| ADMIN boleh tukar kata laluan SUPERADMIN | Tiada semakan kebenaran tambahan | `pastikanBolehUrus()` |
| UNIQUE index hilang pada emel/IC/kod | Constraint dibuang kerana konflik soft delete | Lajur janaan selamat soft delete |
| Fail imbasan boleh dicapai melalui URL awam | Disk `public` tanpa semakan | Disk `local` + laluan menyemak `DokumenPolicy` |
| OCR gagal "tesseract not recognized" | Herd tidak nampak System PATH | Laluan penuh `.exe` dalam `.env` |
| Imbas & Arkib boleh memindahkan fail dokumen lain | `simpan()` mempercayai medan tersembunyi daripada pelayar | Data dibaca daripada `meta.json` token di pelayan |
| Kerja Imbas & Arkib hilang bila validasi gagal | Skrin semak hasil POST | Aliran token GET `semak/{token}` / POST `simpan/{token}` |
| Fail yatim bila transaksi gagal (K-12) | Fail ditulis sebelum commit | Fail dipadam/dipulihkan bila transaksi gagal |
| STAFF/PENGURUS boleh guna Imbas & Arkib | Tiada sekatan laluan | Laluan dihadkan kepada KERANI/ADMIN/SUPERADMIN |
| Masa dipaparkan 8 jam lebih awal | Aplikasi UTC, MySQL waktu Malaysia | `APP_TIMEZONE=Asia/Kuala_Lumpur` + skrip SQL pembetulan |
| Pendaftaran awam & lupa kata laluan mendedahkan akaun | Mesej berbeza bagi No. KP/emel berdaftar | Respons seragam |
| Suntikan pautan Markdown melalui nama dalam emel | Nama tidak disahkan/di-escape | `PeraturanNama` + `TeksEmel::selamat()` |
| Pemohon boleh meluluskan permohonan sendiri | Tiada semakan `pemohon_id` | 403 + butang disembunyikan + keluar dari giliran |
| Mesej validasi & tarikh dalam BI | `APP_LOCALE=en`, tiada `lang/` | Locale `ms` + `lang/ms/*` |
| Paginasi memaparkan anak panah SVG gergasi | Templat lalai Tailwind tanpa CSS Tailwind | `Paginator::useBootstrapFive()` |
| Butang loceng dengan titik merah palsu | Hiasan CSS tanpa fungsi | Loceng memaparkan bilangan sebenar giliran |
| Tindakan tanpa log masuk dikreditkan kepada user_id 1 | Observer guna `Auth::id() ?? 1` | `Auth::id()` (kosong = Sistem) |
| Perubahan medan borang tidak dilog | Tiada observer; padam medan guna query pukal | `DokumenFieldObserver` + padam setiap model |
| Siapa yang menolak tidak direkod dalam `borang` | Tiada lajur | `pegawai_penolak_id`, `tarikh_tolak` |
| Bahagian dengan kakitangan boleh dipadam tanpa amaran | Tiada semakan rujukan | Disekat; amaran bilangan dokumen |

---

## 12. Had Sistem & Kerja Akan Datang

### Aliran Kelulusan: Versi Asas Sahaja (Disengajakan)
Dibina ringkas kerana sistem baru digunakan oleh Bahagian Digital untuk mengarkib dokumen sedia lulus:
- Satu dokumen = satu rekod `borang`; hantar semula menimpa rekod itu, tetapi setiap tindakan kekal dalam `borang_sejarah` (P4)
- Skop: PENYOKONG ikut bahagian dokumen, PELULUS semua bahagian, dan delegasi semasa cuti (P5). Tiada carta organisasi berbilang peringkat
- Notifikasi emel sudah dibina tetapi **mati secara lalai** (`NOTIFIKASI_EMEL=false`); hidupkan semasa serahan
- Kebenaran disemak dalam controller, bukan melalui `BorangPolicy`

### OCR: Segerak (Synchronous)
OCR diproses dalam permintaan HTTP yang sama (tiada queue). Analisis Imbas & Arkib/Hantar dijalankan satu pada satu masa (kunci cache, `OCR_TUNGGU_GILIRAN`), jadi permintaan serentak menunggu giliran dan tidak berebut GPU. Sesuai untuk volum rendah semasa; perlu dipindah ke `php artisan queue:work` (T2) jika penggunaan meningkat.

### Ketepatan OCR/AI
Tanda ✓ tulisan tangan, medan kosong yang diisi teks lain oleh AI, tulisan sambung dan Jawi (lihat 5.11). Cadangan seterusnya: templat zon setiap medan.

**Penilaian (P15):** `php artisan ocr:nilai` menilai setiap medan terhadap dokumen berlabel (`X.label.json` di sebelah fail: `kategori`, `disahkan`, `tarikh_dokumen`, `perkara`, `medan`; hanya label `"disahkan": true` dikira). `--jana-label=KOD` menjana draf label daripada hasil AI untuk disemak manusia, supaya set 30–50 dokumen boleh dibina dengan cepat. Laporan memaparkan ketepatan setiap dokumen, skor OCR, masa OCR/AI dan senarai medan salah.

**Pembetulan staf (P16):** setiap simpan Imbas & Arkib/Hantar merekod nilai AI berbanding nilai akhir (`pembetulan_ai`). Prompt AI bagi kategori yang sama menyenaraikan nilai yang pernah **salah** (dan "medan ini biasanya kosong") supaya tidak diulang (`OLLAMA_FEW_SHOT`, lalai hidup). Nilai **betul** sengaja tidak dimasukkan: dalam ujian dengan borang 4-b, model menyalin nilai betul dokumen lama ke dokumen baharu walaupun diarah tidak. Contoh pada 4-b selepas satu pembetulan: "ERASED BN" pada medan nama yang kosong tidak lagi diisi (dokumen yang sama, jadi bukan ukuran ketepatan umum). LoRA kekal kerja masa depan.

### Kerja Kecil Belum Dibuat
- (Tiada: penapis julat EAV siap dalam P8)

---

## 13. Akaun Ujian (Untuk Pembangunan/Demo Sahaja)

Log masuk guna **No. KP**. Kata laluan seragam: `123456789`

| Peranan | No. KP | Nama | Emel |
|---|---|---|---|
| ADMIN | 990101010101 | Nurul Aina binti Ahmad | admin.ujian@pkink.com |
| KERANI | 990101010102 | Mohd Firdaus bin Ismail | kerani.ujian@pkink.com |
| PENGURUS (Bahagian Digital) | 990101010103 | Siti Aisyah binti Rahman | pengurus.ujian@pkink.com |
| STAFF (Bahagian Digital) | 990101010104 | Ahmad Hakim bin Yusof | staff1.ujian@pkink.com |
| STAFF (Bahagian Kewangan) | 990101010105 | Farah Nabila binti Hassan | staff2.ujian@pkink.com |
| AUDIT | 990101010106 | Zulkifli bin Omar | audit.ujian@pkink.com |
| PENYOKONG | 990101010107 | Nor Hidayah binti Kamal | penyokong.ujian@pkink.com |
| PELULUS | 990101010108 | Hazwan bin Abdullah | pelulus.ujian@pkink.com |
| **ADMIN + STAFF** (berbilang peranan, untuk demo tukar peranan) | 990101010109 | Demo Pelbagai Peranan | demo.pelbagai@pkink.com |

**Nota:** Akaun ujian ini **mesti dipadam** (soft delete melalui Pengurusan Kakitangan) sebelum sistem digunakan dalam persekitaran sebenar/produksi.

---

## 14. Glosari Istilah

| Istilah | Maksud |
|---|---|
| **EAV** | Entity-Attribute-Value: corak reka bentuk pangkalan data yang membenarkan medan dinamik tanpa ubah struktur jadual |
| **RBAC** | Role-Based Access Control: kawalan akses berdasarkan peranan pengguna |
| **OCR** | Optical Character Recognition: pengecaman teks daripada imej |
| **LLM** | Large Language Model: model AI bahasa (cth. SEA-LION) |
| **VLM** | Vision Language Model: model AI yang membaca imej (cth. GLM-OCR untuk tulisan tangan) |
| **Ollama** | Perisian untuk menjalankan model AI secara tempatan melalui API HTTP |
| **Auto-isi** | Medan borang yang diisi & dikunci daripada profil pemohon |
| **Token (Imbas & Arkib)** | Pengecam 40 aksara bagi satu sesi semakan; folder `ocr_sementara/{token}/` |
| **Soft Delete** | "Padam" rekod dengan menandakan `deleted_at`, tanpa membuang daripada pangkalan data |
| **Observer (Laravel)** | Kelas yang "mendengar" peristiwa model untuk menjalankan kod tambahan automatik |
| **Policy (Laravel)** | Kelas yang memusatkan logik kebenaran bagi satu model |
| **Middleware** | Lapisan yang menyemak permintaan sebelum sampai ke controller |
| **Generated Column** | Lajur yang nilainya dikira automatik daripada lajur lain (untuk UNIQUE selamat soft delete) |
| **Gate / Authorize** | Mekanisme Laravel untuk menyemak kebenaran sebelum tindakan |
| **SRI** | Subresource Integrity: hash yang memastikan fail CDN tidak diubah |
| **Had Kadar (Rate Limit)** | Had bilangan cubaan dalam tempoh tertentu (log masuk, pendaftaran) |

---

*Dokumen ini dikemas kini sepanjang pembangunan projek (terakhir: 27 Oktober 2026). Untuk persoalan teknikal semasa pembentangan, rujuk nombor bahagian berkaitan di atas.*
