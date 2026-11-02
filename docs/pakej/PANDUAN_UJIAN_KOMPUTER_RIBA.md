# Panduan Ujian di Komputer Riba: Sistem Arkib Digital (DMS) PKINK

Pengguna sasaran: pegawai yang ingin menilai sistem pada komputer riba atau komputer meja **Windows 10/11** sebelum sistem dipasang di pelayan. Panduan ini menggunakan tetapan minimum untuk ujian sahaja. Bagi pemasangan di pelayan, rujuk **Panduan Pemasangan** (`docs/PANDUAN_PEMASANGAN.pdf`).

> **Nota:** tetapan dalam panduan ini (tanpa HTTPS, tanpa pengesahan dua faktor wajib dan tanpa sandaran automatik) sesuai untuk ujian di komputer peribadi sahaja, dan **tidak boleh digunakan** untuk pelayan produksi.

---

## 1. Keperluan

| Perkara | Keperluan |
|---|---|
| Sistem pengendalian | Windows 10 atau Windows 11 (64-bit) |
| RAM | 8 GB (16 GB jika fungsi AI hendak diuji) |
| Ruang cakera | ~2 GB untuk sistem asas; tambahan ~8 GB jika fungsi AI hendak diuji |
| Fail | `DMS_PKINK_v<versi>_<tarikh>.zip` dan `AKAUN_UAT.pdf` (akaun ujian) |
| Internet | Diperlukan semasa memuat turun perisian sahaja |

Fungsi yang boleh diuji dengan **pemasangan asas** (Bahagian 2 hingga 5): log masuk, papan pemuka, isi borang secara digital, muat naik dokumen, aliran sokongan dan kelulusan, slip kelulusan, borang siap, carian, pengurusan kakitangan dan kategori, log audit serta Pusat Keselamatan.

Fungsi **Imbas & Arkib** dan **Imbas & Hantar** (pengecaman teks dan pengisian automatik oleh AI) memerlukan pemasangan tambahan dalam Bahagian 6.

---

## 2. Pasang perisian asas

### 2.1 PHP: Laravel Herd
Sistem memerlukan **PHP 8.3 atau lebih baharu**. PHP yang dibekalkan bersama XAMPP lazimnya versi 8.2 atau lebih rendah dan **tidak boleh digunakan**; oleh itu, XAMPP digunakan untuk MySQL sahaja.

1. Muat turun dan pasang **Laravel Herd untuk Windows** (percuma): https://herd.laravel.com/windows
2. Buka Herd → **Settings → PHP**, dan pastikan **PHP 8.3** dipasang dan dipilih (versi yang telah diuji bersama sistem ini).
3. Buka **Windows PowerShell** baharu dan semak versi PHP:
   ```
   php -v
   ```
   Paparan mesti bermula dengan `PHP 8.3` (atau lebih baharu). Jika paparan menunjukkan `PHP 8.2`, laluan PHP XAMPP mendahului Herd dalam pemboleh ubah PATH Windows; alih keluar `C:\xampp\php` daripada PATH, kemudian buka PowerShell baharu.

### 2.2 Pangkalan data: XAMPP (MySQL sahaja)
1. Muat turun dan pasang **XAMPP**: https://www.apachefriends.org (pilih komponen **MySQL** dan **phpMyAdmin**).
2. Buka **XAMPP Control Panel**, kemudian klik **Start** pada **MySQL** (dan **Apache** jika ingin menggunakan phpMyAdmin).

Jika komputer sudah mempunyai MySQL 8.0+ atau MariaDB 10.4+, perisian tersebut boleh digunakan terus.

---

## 3. Salin fail sistem dan pangkalan data

### 3.1 Ekstrak fail sistem
Ekstrak fail zip ke `C:\dms_pkink`. Struktur folder yang betul ialah `C:\dms_pkink\DMS_PKINK\app`, `C:\dms_pkink\DMS_PKINK\public` dan seterusnya. Dalam panduan ini, folder sistem ialah **`C:\dms_pkink\DMS_PKINK`**.

### 3.2 Cipta dan import pangkalan data
**Kaedah A: phpMyAdmin** (Apache dan MySQL dihidupkan dalam XAMPP)
1. Buka http://localhost/phpmyadmin
2. Klik **New**, isi nama pangkalan data `dms_pkink`, pilih *collation* `utf8mb4_unicode_ci`, kemudian klik **Create**.
3. Pilih pangkalan data `dms_pkink` → tab **Import** → pilih fail `C:\dms_pkink\DMS_PKINK\database\dms_pkink.sql` → klik **Import**.

**Kaedah B: baris arahan** (buka **Command Prompt**, bukan PowerShell)
```
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE dms_pkink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe -u root dms_pkink < C:\dms_pkink\DMS_PKINK\database\dms_pkink.sql
```

Import yang berjaya menghasilkan 27 jadual.

---

## 4. Tetapan sistem (`.env`)

1. Dalam folder `C:\dms_pkink\DMS_PKINK`, salin fail **`.env.example`** dan namakan salinan itu **`.env`**. (Jika sambungan nama fail tidak kelihatan, aktifkan *View → Show → File name extensions* dalam File Explorer.)
2. Buka `.env` menggunakan Notepad dan ubah baris berikut kepada nilai di bawah. Baris lain dibiarkan seperti asal.

| Baris dalam `.env` | Nilai untuk ujian di komputer riba |
|---|---|
| `APP_URL` | `APP_URL=http://127.0.0.1:8000` |
| `DB_USERNAME` | `DB_USERNAME=root` |
| `DB_PASSWORD` | `DB_PASSWORD=` (kosong; lalai XAMPP) |
| `SESSION_SECURE_COOKIE` | `SESSION_SECURE_COOKIE=false` |
| `MAIL_MAILER` | `MAIL_MAILER=log` (emel tidak dihantar; direkodkan dalam `storage/logs`) |
| `MAIL_FROM_ADDRESS` | `MAIL_FROM_ADDRESS="dms@pkink.gov.my"` |
| `VAPID_SUBJECT` | `VAPID_SUBJECT="mailto:dms@pkink.gov.my"` |
| `SANDARAN_AKTIF` | `SANDARAN_AKTIF=false` |
| `DUA_FAKTOR_WAJIB` | `DUA_FAKTOR_WAJIB=false` |
| `ANTIVIRUS_WAJIB` | `ANTIVIRUS_WAJIB=false` |

3. Simpan fail.

> `SESSION_SECURE_COOKIE=false` **wajib** untuk ujian melalui `http://`. Tanpanya, log masuk akan gagal dengan ralat *419 Page Expired*.

---

## 5. Jalankan sistem

1. Buka **Windows PowerShell** dan masuk ke folder sistem:
   ```
   cd C:\dms_pkink\DMS_PKINK
   ```
2. Jana kunci aplikasi (sekali sahaja):
   ```
   php artisan key:generate --force
   ```
3. Hidupkan pelayan ujian:
   ```
   php artisan serve
   ```
   Biarkan tetingkap PowerShell ini terbuka sepanjang ujian. Untuk menghentikan sistem, tekan **Ctrl + C**.
4. Buka pelayar web (Google Chrome atau Microsoft Edge) di alamat **http://127.0.0.1:8000**
5. Log masuk menggunakan **No. Kad Pengenalan** dan **kata laluan sementara** dalam `AKAUN_UAT.pdf`. Sistem akan meminta kata laluan baharu pada log masuk pertama (sekurang-kurangnya 8 aksara, mengandungi huruf dan nombor).

Untuk menggunakan sistem semula pada hari lain: hidupkan **MySQL** dalam XAMPP Control Panel, kemudian ulang langkah 5.1 dan 5.3.

Cara menggunakan setiap fungsi diterangkan dalam **Manual Pengguna** (`docs/MANUAL_PENGGUNA_DMS_PKINK.pdf`).

---

## 6. Pilihan: fungsi imbasan (OCR) dan AI

Langkah ini diperlukan hanya untuk menguji **Imbas & Arkib** dan **Imbas & Hantar**.

### 6.1 Pengecaman teks (OCR): Tesseract dan Poppler
1. Pasang **Tesseract OCR** (pemasang UB-Mannheim): https://github.com/UB-Mannheim/tesseract/wiki. Pilihan lalai pemasang adalah memadai.
2. Muat turun **Poppler untuk Windows** (https://github.com/oschwartz10612/poppler-windows/releases) dan ekstrak ke `C:\poppler`.
3. Tambah baris berikut dalam `.env` (atau buang tanda `#` pada baris contoh Windows yang sedia ada):
   ```
   OCR_TESSERACT_BIN="C:/Program Files/Tesseract-OCR/tesseract.exe"
   OCR_PDFTOPPM_BIN="C:/poppler/Library/bin/pdftoppm.exe"
   ```
   Pastikan laluan `pdftoppm.exe` sepadan dengan folder sebenar selepas diekstrak.

### 6.2 Kecerdasan buatan (AI): Ollama dan model
1. Pasang **Ollama**: https://ollama.com/download
2. Daftar dua model mengikut **Panduan Pemasangan, Bahagian 3.6** (jumlah muat turun ~6.7 GB):
   - `sea-lion-local` (pengisian medan), menggunakan fail `skrip/ollama/Modelfile.sea-lion-local`;
   - `glm-ocr:q8_0` (tulisan tangan): `ollama pull glm-ocr:q8_0`
3. Komputer **tanpa kad grafik NVIDIA** (6 GB VRAM atau lebih): buang baris `OLLAMA_NUM_GPU` dalam `.env`. AI tetap berfungsi tetapi lebih perlahan (kira-kira 1 hingga 3 minit bagi setiap dokumen).

Selepas mengubah `.env`, hentikan sistem (**Ctrl + C**) dan jalankan semula `php artisan serve`. Semak status setiap komponen melalui menu **Status Sistem** (log masuk sebagai Super Admin).

> Pekerja latar dan penjadual **tidak perlu** dipasang untuk ujian. Tanpanya, analisis imbasan dijalankan terus dan pengguna menunggu di halaman sehingga analisis selesai. Status Sistem akan memaparkan amaran bagi kedua-dua komponen ini; amaran tersebut boleh diabaikan semasa ujian.

---

## 7. Penyelesaian masalah

| Gejala | Punca dan tindakan |
|---|---|
| `Your Composer dependencies require a PHP version ">= 8.3.0"` | PHP yang digunakan ialah versi lama (contohnya PHP XAMPP). Rujuk Bahagian 2.1 dan semak `php -v`. |
| *419 Page Expired* semasa log masuk | `SESSION_SECURE_COOKIE` belum ditetapkan kepada `false`, atau alamat pelayar tidak sama dengan `APP_URL`. Gunakan `http://127.0.0.1:8000`. |
| `SQLSTATE[HY000] [2002]` / *No connection could be made* | MySQL tidak berjalan. Klik **Start** pada MySQL dalam XAMPP Control Panel. |
| `SQLSTATE[HY000] [1049] Unknown database` | Pangkalan data belum dicipta atau namanya berbeza daripada `DB_DATABASE` dalam `.env` (Bahagian 3.2). |
| *500 Server Error* | Semak `APP_KEY` dalam `.env` telah diisi (Bahagian 5.2) dan baca butiran ralat dalam `storage\logs\laravel-<tarikh>.log`. |
| `Failed to listen on 127.0.0.1:8000` | Port 8000 sedang digunakan. Jalankan `php artisan serve --port=8080`, tukar `APP_URL` kepada `http://127.0.0.1:8080`, dan buka alamat tersebut. |
| Imbas & Arkib memaparkan ralat OCR | Tesseract atau Poppler belum dipasang, atau laluannya dalam `.env` tidak tepat (Bahagian 6.1). |
| Medan tidak diisi oleh AI | Ollama tidak berjalan atau model belum didaftarkan (Bahagian 6.2). Medan masih boleh diisi secara manual. |

---

## 8. Selepas ujian

Untuk membuang sistem daripada komputer: padam folder `C:\dms_pkink`, dan padam pangkalan data `dms_pkink` melalui phpMyAdmin (tab **Operations → Drop the database**). Laravel Herd, XAMPP, Tesseract dan Ollama boleh dinyahpasang melalui **Settings → Apps** dalam Windows.
