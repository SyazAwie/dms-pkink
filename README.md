# Sistem Arkib Digital (DMS) PKINK

Aplikasi web untuk mendigitalkan pengurusan, penyimpanan dan penjejakan dokumen merentas bahagian di Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK). Dibangunkan sebagai projek latihan industri sains komputer.

## Tech Stack

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Pangkalan Data | MySQL / MariaDB 10.4 |
| Persekitaran Tempatan | Laravel Herd (nginx + PHP) + XAMPP (MySQL sahaja) |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons |
| Estetika UI | Glassmorphism — panel lut sinar, kecerunan, elemen terapung |
| Model Data Dinamik | Entity-Attribute-Value (EAV) untuk medan dokumen |

---

## 1. Konsep Teras: Kenapa EAV?

Setiap jenis dokumen PKINK (Invois, Surat Rasmi, Minit Mesyuarat) ada medan maklumat yang berbeza sama sekali. Daripada mereka bentuk satu jadual gergasi dengan lajur untuk setiap kemungkinan medan (kebanyakannya kosong), atau satu jadual berasingan bagi setiap jenis dokumen (perlukan migration setiap kali jenis baharu ditambah), sistem ini guna corak **EAV**:

- `jenis_dokumen` — kategori dokumen (cth: "Invois Perjalanan")
- `dokumen_field` — **templat**: senarai medan yang perlu diisi bagi kategori itu (nama, kod, jenis data, wajib/tidak, pilihan dropdown)
- `dokumen` — rekod dokumen sebenar
- `dokumen_data` — **nilai sebenar** bagi setiap medan, terikat kepada satu dokumen

Admin boleh cipta kategori dokumen baharu dan tentukan medannya sendiri terus dari UI, tanpa sesiapa perlu menulis kod atau migration.

**Had EAV yang perlu diketahui:** carian/penapis ke atas nilai medan (cth. "cari semua invois RM > 1000") tidak boleh guna `WHERE` terus ke atas lajur, kerana nilai disimpan sebagai baris dalam `dokumen_data`, bukan lajur. Carian kata kunci sudah menyokong ini (lihat Bahagian 3), tetapi penapis nombor/julat ke atas nilai medan belum dibina.

---

## 2. Struktur Pangkalan Data

| Kumpulan | Jadual |
|---|---|
| Organisasi & RBAC | `bahagian`, `users`, `roles`, `user_roles` |
| Templat Dokumen | `jenis_dokumen`, `dokumen_field` |
| Dokumen Sebenar | `dokumen`, `dokumen_data` |
| Imbasan & OCR | `dokumen_scan`, `dokumen_ocr` |
| Aliran Kelulusan | `borang` — versi asas siap (lihat Bahagian 7.1) |
| Audit | `log_audit` |
| Infra Laravel | `password_reset_tokens`, `sessions` |

Skema asal (`eborang-dms-schema-v3.sql` / ERD v2) telah dipinda melalui beberapa migration tambahan sepanjang pembangunan — lihat Bahagian 5.

---

## 3. Modul Yang Telah Siap

### 3.1 Pengesahan (Authentication)
Log masuk, tetapan semula kata laluan (token dari URL, e-mel `readonly`).

### 3.2 Master Layout & Navigasi
`app.blade.php` — sidebar terapung, palet warna korporat, navigasi yang kini **disembunyikan/dipaparkan ikut peranan pengguna**.

### 3.3 Kategori Dokumen (`jenis_dokumen`) — CRUD Lengkap + Enjin EAV
- Senarai, Tambah, Kemaskini dengan soft delete
- **Pembina medan dinamik**: admin tambah/buang medan (Teks, Nombor, Tarikh, Dropdown, Textarea) terus dalam borang Cipta/Edit Kategori, guna baris JS dinamik
- Kod medan disahkan unik dalam satu kategori (selamat dengan soft delete, guna lajur janaan)

### 3.4 Dokumen — CRUD Lengkap + Muat Naik
- **Muat Naik**: borang yang berubah bentuk secara automatik (AJAX) ikut Kategori Dokumen dipilih, upload berbilang fail imbasan (PDF/JPG/PNG, maks 10MB/fail)
- **No. Rujukan dijana automatik**: format `{KOD}/{TAHUN}/{TURUTAN 4-digit}`, guna nombor tertinggi sedia ada (kebal daripada isu padam/count())
- **Senarai**: carian kata kunci (termasuk **nilai medan EAV** dan nama fail), penapis (Jenis, Bahagian, julat Tarikh), susunan, panel penapis boleh lipat dengan chip penapis aktif
- **Butiran**: papar semua nilai medan EAV + senarai fail imbasan boleh muat turun
- **Kemaskini**: ubah tarikh/perkara/nilai medan, buang/tambah fail (mesti kekal ≥1 fail)
- **Padam**: soft delete — rekod & fail fizikal dikekalkan untuk arkib

### 3.5 Pengurusan Bahagian & Kakitangan — CRUD Lengkap
Termasuk penetapan peranan (`roles[]`) semasa daftar/kemaskini kakitangan.

### 3.6 RBAC (Kawalan Akses Ikut Peranan)
8 peranan: SUPERADMIN, ADMIN, PENGURUS, STAFF, KERANI, AUDIT, PENYOKONG, PELULUS.

| Peranan | Skop Lihat Dokumen | Muat Naik | Edit | Padam | Modul Pentadbiran |
|---|---|---|---|---|---|
| SUPERADMIN / ADMIN | Semua | Ya | Ya | Ya | Ya |
| KERANI | Semua | Ya | Ya | Ya | Tidak |
| PENGURUS | Bahagian sendiri | Ya | Bahagian sendiri | Tidak | Tidak |
| STAFF | Milik sendiri | Ya | Milik sendiri | Tidak | Tidak |
| AUDIT / PENYOKONG / PELULUS | Semua | Tidak | Tidak | Tidak | Tidak |

Dikuatkuasakan melalui `DokumenPolicy`, middleware `peranan`, dan `Dokumen::scopeBolehDilihat()`. Halaman 403 tersendiri.

### 3.7 Log Audit
- Setiap Cipta/Kemaskini/Padam pada `bahagian`, `jenis_dokumen`, `users`, `dokumen` direkod (Observer pattern), termasuk perubahan peranan
- Halaman Log Audit (SUPERADMIN & AUDIT sahaja): penapis pengguna/jadual/tindakan/tarikh, butiran data lama/baharu boleh dibuka
- Lajur sensitif (`password`, `remember_token`) dikecualikan/ditapis daripada log

---

## 4. Isu Yang Telah Dibaiki Sepanjang Pembangunan

- **Kebocoran `remember_token`** ke dalam `log_audit` (plaintext) melalui putaran token "ingat saya" — dibetulkan dalam `UserObserver`
- **Log audit berganda** disebabkan Observer didaftar dua kali (`#[ObservedBy]` attribute + `AppServiceProvider::boot()`)
- **`user_roles` sentiasa kosong** — punca: kod guna lajur `roles_id`, sedangkan lajur pivot sebenar ialah `role_id`
- **Privilege escalation**: ADMIN sebelum ini boleh tukar kata laluan akaun SUPERADMIN — kini disekat (`pastikanBolehUrus()`)
- **UNIQUE index hilang** (SOP buang semua unique constraint kerana konflik dengan soft delete) — dipulihkan guna lajur janaan (generated column) yang serasi soft delete

---

## 5. Migration SQL — Jalankan Ikut Susunan

Skema asal tiada jadual `migrations` (diuruskan manual via HeidiSQL). Enam skrip berikut **mesti dijalankan mengikut nombor**, sebelum kod PHP berkaitan diletak:

| # | Fail | Tujuan |
|---|---|---|
| 01 | `01_perbaikan_unique_index.sql` | Pulihkan UNIQUE (email, IC, kod) yang serasi soft delete |
| 02 | `02_pembersihan_log_audit.sql` | Buang rekod token bocor & log berganda sedia ada |
| 03 | `03_migration_eav_dokumen_field.sql` | Tambah `pilihan` (JSON) + soft delete pada `dokumen_field`, integriti `dokumen_data` |
| 04 | `04_migration_dokumen_kolum_tambahan.sql` | Tambah `bahagian_id` + `created_by` pada `dokumen` |
| 05 | `05_migration_dokumen_soft_delete.sql` | Soft delete pada `dokumen` |
| 06 | `06_migration_rbac_dan_indeks_audit.sql` | **Beri SUPERADMIN kepada akaun sedia ada** + indeks `log_audit` — **WAJIB sebelum letak kod RBAC**, jika tidak anda akan terkunci |

---

## 6. Peta Fail → Lokasi Dalam Projek

> Fail Blade yang dimuat turun dinamakan dengan `_blade.php` (bukan `.blade.php`) kerana had platform muat turun — **namakan semula** ke `.blade.php` selepas disalin.

### Model (`app/Models/`)
`Dokumen.php`, `DokumenData.php`, `DokumenField.php`, `DokumenScan.php`, `DokumenOcr.php`, `JenisDokumen.php`, `User.php`, `Role.php`, `LogAudit.php`, `Borang.php`

### Observer (`app/Observers/`)
`DokumenObserver.php`, `JenisDokumenObserver.php`, `UserObserver.php`
*(`BahagianObserver.php` turut wujud tetapi tidak diubah dalam sesi ini)*

### Policy (`app/Policies/` — cipta folder)
`DokumenPolicy.php`

### Middleware (`app/Http/Middleware/` — cipta fail)
`SemakPeranan.php`

### Controller (`app/Http/Controllers/`)
`DokumenController.php`, `JenisDokumenController.php`, `UserController.php`, `LogAuditController.php`, `BorangController.php`, `DashboardController.php`

### Service (`app/Services/` — cipta folder)
`OcrService.php`

### Provider & Bootstrap
`AppServiceProvider.php` → `app/Providers/`
`app.php` → `bootstrap/app.php`

### Routes
`web.php` → `routes/web.php`

### Views
| Fail | Lokasi |
|---|---|
| `app_blade.php` | `resources/views/layouts/app.blade.php` |
| `create_blade.php`, `edit_blade.php`, `medan-script_blade.php` | `resources/views/jenis_dokumen/` (`medan-script` → subfolder `partials/`) |
| `dokumen_create_blade.php`, `dokumen_index_blade.php`, `dokumen_show_blade.php`, `dokumen_edit_blade.php` | `resources/views/dokumen/` (sebagai `create/index/show/edit.blade.php`) |
| `users_create_blade.php`, `users_edit_blade.php` | `resources/views/users/` (sebagai `create/edit.blade.php`) |
| `log_audit_index_blade.php` | `resources/views/log_audit/index.blade.php` (cipta folder) |
| `kelulusan_index_blade.php` | `resources/views/kelulusan/index.blade.php` (cipta folder) |
| `errors_403_blade.php` | `resources/views/errors/403.blade.php` (cipta folder) |

### Langkah Tambahan (bukan fail kod)
```
php artisan storage:link
```
Wajib dijalankan sekali supaya fail imbasan yang dimuat naik boleh diakses browser.

---

## 7. Apa Yang Belum Siap

Disusun ikut keutamaan cadangan:

### 🔴 Kritikal — sebelum guna dokumen sebenar
- **Fail imbasan tidak dilindungi RBAC.** Fail disimpan di cakera `public`; sesiapa yang tahu/teka URL boleh buka fail walaupun tidak dibenarkan lihat dokumen itu (nama fail rawak, tapi bukan kawalan sebenar). Penyelesaian: simpan fail secara *private* + laluan muat turun yang semak `DokumenPolicy` dahulu.

### 🟡 Modul Besar Belum Dibina
- Tiada lagi — OCR dan Dashboard kedua-duanya siap (lihat 7.1 dan 7.2)

### 🟢 Kemas Kini Kecil
- Halaman senarai Kakitangan (`users/index.blade.php`) belum papar lajur peranan pengguna (data sudah dimuat dalam controller, tinggal tambah lajur)
- KERANI sepatutnya "lihat sahaja" bagi Kategori Dokumen & Bahagian, tetapi kedua-dua modul kini disekat kepada SUPERADMIN/ADMIN sahaja — perlu semak `index.blade.php` kedua-duanya untuk sembunyikan butang tindakan mengikut peranan
- Tiada skrin "Tong Sampah" untuk pulihkan (`restore`) rekod yang disoft-delete (Dokumen, Kategori, Bahagian, Kakitangan)
- Penapis nombor/julat ke atas nilai medan EAV (cth. "Jumlah RM antara X-Y") belum dibina — carian kata kunci sedia ada, tetapi bukan penapis berstruktur
- `dokumen_scan` tiada saiz fail, jenis MIME atau hash (checksum) untuk sahkan integriti arkib jangka panjang

---

## 7.1 Aliran Kelulusan (`borang`) — Siap, versi ASAS sahaja

Sengaja dibina ringkas kerana sistem baru digunakan oleh Bahagian Digital untuk scan dokumen sedia ada; aliran kelulusan penuh belum diperlukan. Fungsi: Hantar (STAFF/PENGURUS/KERANI/ADMIN, skop sama macam edit dokumen) → Sokong (PENYOKONG) → Lulus (PELULUS), atau Tolak pada mana-mana peringkat (ulasan wajib). Status dipapar & tindakan dilakukan terus dalam halaman Butiran Dokumen; ada juga halaman senarai `/kelulusan` untuk Penyokong/Pelulus/Admin.

**Had yang disengajakan (nota lengkap ada dalam `BorangController.php`), untuk pembangun seterusnya lengkapkan bila sistem sedia digunakan menyeluruh:**
- Satu dokumen = satu rekod `borang` sahaja (`updateOrCreate`). Hantar semula **menimpa** ulasan/tarikh sebelumnya — tiada sejarah berbilang penghantaran. Jika sejarah penuh diperlukan kelak, tambah jadual `borang_sejarah` atau buang andaian 1:1 pada `Dokumen::borang()`
- Penyokong & Pelulus **tidak diskop ikut bahagian** — mana-mana pengguna berperanan itu boleh bertindak ke atas borang bahagian lain. Tiada carta organisasi/hierarki kelulusan
- Tiada notifikasi (e-mel/loceng) apabila status berubah — pemohon perlu semak sendiri
- Kebenaran disemak terus dalam `BorangController` (`hasAnyRole`), bukan Policy berasingan seperti `DokumenPolicy` — pertimbangkan `BorangPolicy` jika modul ini berkembang

---

## 7.2 OCR (Pengecaman Teks) — Siap, guna Tesseract tempatan

OCR berjalan **automatik selepas setiap fail dimuat naik** (dalam `store()`/`update()` di `DokumenController`), guna `App\Services\OcrService` yang panggil terus `tesseract` dan `pdftoppm` (Poppler) melalui Symfony Process — tiada pustaka Composer tambahan.

**Wajib dipasang berasingan pada mesin (bukan kod, perisian sistem):**
1. **Tesseract OCR** (Windows: [UB-Mannheim build](https://github.com/UB-Mannheim/tesseract/wiki)) — semasa pasang, tanda bahasa **Malay** sekali dengan English
2. **Poppler** (Windows: [poppler-windows releases](https://github.com/oschwartz10612/poppler-windows/releases)) — untuk `pdftoppm`, diperlukan sebab Tesseract tak boleh baca PDF terus
3. Kedua-dua mesti ditambah ke **System PATH**, dan terminal/Herd restart selepas itu

Sahkan pemasangan: buka terminal baharu, `tesseract --version` dan `pdftoppm -v` kedua-duanya patut papar nombor versi.

**Cara ia berfungsi:**
- PDF → `pdftoppm` tukar setiap muka surat kepada PNG 300dpi → setiap imej dihantar ke `tesseract`
- Imej (JPG/PNG) → terus ke `tesseract`
- Tesseract dijalankan dengan output format **TSV**, supaya teks dan **skor keyakinan purata** (lajur `conf`) boleh diambil sekali gus — skor disimpan dalam `dokumen_ocr.skor_padanan`
- Teks dan skor disimpan ke `dokumen_ocr`; `dokumen_scan.status_ocr` dikemaskini kepada `Selesai`, `Tiada Teks Dikesan`, atau `Ralat`
- OCR dijalankan **selepas** transaksi DB disimpan (bukan dalam transaksi) sebab ia proses luaran yang boleh ambil beberapa saat — elak kunci pangkalan data lama
- **Kegagalan OCR tidak menggagalkan muat naik dokumen** — ditanda status `Ralat` dan dicatat dalam `storage/logs/laravel.log`, staf tetap boleh teruskan guna sistem

**Had yang diketahui:**
- Diproses **secara segerak (synchronous)** dalam permintaan HTTP yang sama — borang muat naik akan **ambil masa lebih lama** (beberapa saat setiap fail/muka surat). Untuk fail besar/PDF banyak muka surat, pertimbangkan pindah ke queue job (`php artisan queue:work`) pada masa depan supaya staf tak perlu tunggu
- Ketepatan bergantung kualiti imbasan — dokumen condong/kabur/tulisan tangan akan beri skor keyakinan rendah
- Semakan manual (`dokumen_ocr.status_semakan`) sentiasa `Belum Disemak` — tiada UI lagi untuk tandakan teks OCR telah disemak/dibetulkan oleh staf

---

## 8. Akaun Ujian Peranan (Untuk Pembangunan Sahaja)

Kata laluan seragam untuk ujian: `123456789`

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

**Padam akaun ini (soft delete melalui UI) sebelum sistem digunakan sebenar.**