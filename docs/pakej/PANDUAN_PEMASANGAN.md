# Panduan Pemasangan: Sistem Arkib Digital (DMS) PKINK

Untuk: pentadbir pelayan / Bahagian Digital PKINK. Panduan ini meliputi pelayan **Windows** dan **Linux (Ubuntu 22.04+)**. Langkah yang berbeza ditanda **[Windows]** atau **[Linux]**.

---

## Kandungan
1. Kandungan pakej
2. Keperluan pelayan
3. Pemasangan langkah demi langkah
4. Log masuk pertama & akaun UAT
5. Semakan selepas pemasangan
6. Sandaran & pemulihan
7. Senarai semak keselamatan
8. Penyelenggaraan & kemas kini
9. Penyelesaian masalah
10. Rujukan arahan

---

## 1. Kandungan pakej

| Folder / fail | Kandungan |
|---|---|
| `app/`, `bootstrap/`, `config/`, `lang/`, `resources/`, `routes/`, `vendor/` | Kod sistem (Laravel 13) dan pustaka PHP (sudah dipasang, tidak perlu Composer) |
| `public/` | **Satu-satunya** folder yang dihidangkan oleh pelayan web |
| `storage/` | Fail imbasan, log, cache (kosong semasa pemasangan; mesti boleh ditulis) |
| `Templat/` | 7 borang kosong yang digunakan oleh templat kategori |
| `database/dms_pkink.sql` | Pangkalan data: struktur + konfigurasi (peranan, bahagian, kategori, medan, templat) + akaun UAT |
| `docs/` | Manual pengguna, panduan ini, manual templat, pelan tindak balas insiden, dokumentasi teknikal, ERD |
| `skrip/` | Skrip proses latar (Windows & Linux), contoh konfigurasi nginx dan fail Modelfile model AI |
| `.env.example` | Templat tetapan pelayan |

Diserahkan **berasingan** (bukan dalam zip): `AKAUN_UAT.pdf` (No. KP & kata laluan sementara akaun ujian) dan `SHA256SUMS.txt` (semak keutuhan zip).

> **Penting:** sistem ini **tidak** menggunakan *migration* Laravel. Arahan `php artisan migrate`, `migrate:fresh`, `db:wipe` dan `db:seed` **tidak boleh dijalankan** kerana boleh memadam data. Pangkalan data dipasang daripada `database/dms_pkink.sql` sahaja.

---

## 2. Keperluan pelayan

### 2.1 Perkakasan

| Komponen | Minimum | Disyorkan |
|---|---|---|
| CPU | 4 teras | 8 teras |
| RAM | 8 GB | 16 GB |
| GPU | Tidak wajib (AI berfungsi tetapi jauh lebih perlahan, ~1–3 minit setiap dokumen) | NVIDIA, **6 GB VRAM** atau lebih (CUDA) |
| Storan | **25 GB** untuk UAT | ~**100 GB** untuk produksi tahun pertama (~5,000 dokumen) |

**Anggaran storan:**

| Bahagian | Saiz |
|---|---|
| Model AI SEA-LION + GLM-OCR | ~6.7 GB |
| Ollama, PHP, MySQL, pelayan web, Tesseract, Poppler | ~5–8 GB |
| Sistem + pustaka | ~60 MB |
| Ruang kerja OCR (imej sementara 300dpi) | kekalkan ≥ 2 GB kosong (OCR ditolak jika < 1 GB) |
| Data (imbasan) | ~0.25–1 MB setiap dokumen |
| Sandaran | sehingga 28 salinan penuh data (14 harian + 8 mingguan + 6 bulanan; boleh dilaras) |

Formula kasar: **17 GB + 29 × saiz data**. Sandaran disyorkan pada cakera atau NAS berasingan (`SANDARAN_FOLDER`).

### 2.2 Perisian

| Perisian | Versi | Catatan |
|---|---|---|
| PHP | 8.3+ | Sambungan: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd` (dengan FreeType), `iconv`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip` (libzip dengan AES), `zlib`; [Linux] `posix` (disyorkan) |
| MySQL / MariaDB | MySQL 8.0+ atau MariaDB 10.4+ | Set aksara `utf8mb4` |
| Pelayan web | [Windows] Apache 2.4 atau IIS; [Linux] nginx + PHP-FPM | Dokumen root = `public/` |
| Tesseract OCR | 5.x dengan pek bahasa `msa` + `eng` | Pengecaman teks |
| Poppler (`pdftoppm`) | 22+ | PDF → imej untuk OCR |
| Ollama | terkini | AI tempatan (tiada data dihantar ke luar) |
| Antivirus | [Windows] Microsoft Defender; [Linux] ClamAV (`clamdscan`) | Imbas setiap fail yang dimuat naik |

**Tetapan PHP (`php.ini`) disyorkan:**
```
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 64M
max_execution_time = 330
```

---

## 3. Pemasangan langkah demi langkah

Contoh laluan: **[Windows]** `C:\dms_pkink`, **[Linux]** `/var/www/dms_pkink`.

### 3.1 Salin fail sistem
1. Semak keutuhan zip: bandingkan SHA-256 zip dengan `SHA256SUMS.txt`.
   - [Windows] `Get-FileHash .\DMS_PKINK_*.zip -Algorithm SHA256`
   - [Linux] `sha256sum DMS_PKINK_*.zip`
2. Ekstrak zip ke lokasi sistem.
3. **[Linux]** Tetapkan pemilik & kebenaran:
   ```
   sudo chown -R www-data:www-data /var/www/dms_pkink
   sudo find /var/www/dms_pkink -type d -exec chmod 755 {} \;
   sudo find /var/www/dms_pkink -type f -exec chmod 644 {} \;
   sudo chmod -R 775 /var/www/dms_pkink/storage /var/www/dms_pkink/bootstrap/cache /var/www/dms_pkink/Templat
   ```
   **[Windows]** Beri akaun pelayan web (contohnya `IUSR` atau akaun perkhidmatan Apache) dan akaun tugas latar kebenaran **Ubah (Modify)** pada `storage\`, `bootstrap\cache\` dan `Templat\`.

### 3.2 Pangkalan data
1. Cipta pangkalan data kosong dan pengguna khas (jangan guna `root`):
   ```sql
   CREATE DATABASE dms_pkink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'dms_app'@'localhost' IDENTIFIED BY '<kata laluan kuat>';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES, SHOW VIEW, TRIGGER ON dms_pkink.* TO 'dms_app'@'localhost';
   -- Ujian pemulihan sandaran (php artisan sandaran:uji) mencipta dan memadam DB sementara dms_ujian_pulih_*
   GRANT ALL PRIVILEGES ON `dms\_ujian\_pulih\_%`.* TO 'dms_app'@'localhost';
   FLUSH PRIVILEGES;
   ```
   `LOCK TABLES`, `SHOW VIEW` dan `TRIGGER` diperlukan oleh sandaran (`mysqldump`). Pemulihan penuh pangkalan data (bahagian 6) dijalankan oleh pentadbir pangkalan data.
2. Import struktur & data (sebagai pentadbir DB):
   - [Windows] `"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p dms_pkink < C:\dms_pkink\database\dms_pkink.sql`
   - [Linux] `mysql -u root -p dms_pkink < /var/www/dms_pkink/database/dms_pkink.sql`
   - atau buka fail itu dalam HeidiSQL / MySQL Workbench dan jalankan.
3. Selepas import, fail `database/dms_pkink.sql` boleh dipindahkan keluar dari folder sistem.

### 3.3 Fail tetapan `.env`
1. Salin `.env.example` kepada `.env` dalam folder sistem.
2. Isi setiap nilai bertanda `<ISI>`: `APP_URL`, `DB_*`, `MAIL_*`, `VAPID_SUBJECT`, dan alih keluar tanda `#` pada laluan program OCR dan sandaran yang sesuai dengan sistem pengendalian pelayan.
3. Jana kunci aplikasi (sekali sahaja; **jangan** ditukar selepas data wujud):
   ```
   php artisan key:generate
   ```
4. Jana kunci notifikasi peranti (Web Push):
   ```
   php artisan webpush:vapid
   ```
   **[Windows]** PHP memerlukan pemboleh ubah persekitaran `OPENSSL_CONF` yang menunjuk ke fail `openssl.cnf` pemasangan PHP, contohnya
   `setx /M OPENSSL_CONF "C:\php\extras\ssl\openssl.cnf"`, kemudian buka terminal baharu dan mulakan semula pelayan web. Tanpanya ralat *"Unable to create the key"*.
5. Tetapkan `SANDARAN_KATA_LALUAN` (kata laluan zip sandaran) dan **simpan salinannya di luar pelayan** (contohnya dalam sampul bermeterai).
6. Kosongkan cache tetapan: `php artisan config:clear`

### 3.4 Pelayan web
**[Linux] nginx + PHP-FPM:** salin contoh `skrip/linux/nginx-dms-pkink.conf`, tukar `server_name`, sijil SSL dan soket PHP-FPM, kemudian:
```
sudo ln -s /etc/nginx/sites-available/dms-pkink /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

**[Windows] Apache 2.4:** tambah hos maya; `public\.htaccess` sudah disediakan (perlukan `mod_rewrite`):
```
<VirtualHost *:443>
    ServerName dms.contoh.gov.my
    DocumentRoot "C:/dms_pkink/public"
    <Directory "C:/dms_pkink/public">
        AllowOverride All
        Require all granted
    </Directory>
    Timeout 330
    SSLEngine on
    SSLCertificateFile    "C:/ssl/dms-pkink.crt"
    SSLCertificateKeyFile "C:/ssl/dms-pkink.key"
</VirtualHost>
```
**[Windows] IIS:** boleh digunakan dengan PHP (FastCGI) dan modul *URL Rewrite* (import peraturan `public\.htaccess`), dokumen root `C:\dms_pkink\public`, had masa FastCGI 330 s.

> **HTTPS sangat disyorkan:** notifikasi peranti di telefon dan kuki sesi selamat (`SESSION_SECURE_COOKIE=true`) memerlukannya. Untuk ujian sementara melalui `http://`, tetapkan `SESSION_SECURE_COOKIE=false`.

Hanya folder `public/` boleh dicapai dari pelayar. Fail imbasan disimpan dalam `storage/app/private` dan hanya dihidangkan melalui sistem selepas semakan kebenaran. Arahan `php artisan storage:link` **tidak boleh dijalankan**.

### 3.5 Tesseract OCR & Poppler
**[Windows]**
1. Pasang Tesseract (pemasang UB-Mannheim, https://github.com/UB-Mannheim/tesseract/wiki); semasa pemasangan tandakan bahasa **Malay** dan **English**.
2. Ekstrak Poppler untuk Windows (https://github.com/oschwartz10612/poppler-windows/releases) ke `C:\poppler`.
3. Dalam `.env`:
   ```
   OCR_TESSERACT_BIN="C:/Program Files/Tesseract-OCR/tesseract.exe"
   OCR_PDFTOPPM_BIN="C:/poppler/Library/bin/pdftoppm.exe"
   ```

**[Linux]**
```
sudo apt install tesseract-ocr tesseract-ocr-msa tesseract-ocr-eng poppler-utils
```
`.env`: `OCR_TESSERACT_BIN=/usr/bin/tesseract`, `OCR_PDFTOPPM_BIN=/usr/bin/pdftoppm`.

Semak: `tesseract --list-langs` mesti menyenaraikan `msa` dan `eng`.

### 3.6 AI tempatan: Ollama & model
1. Pasang Ollama (https://ollama.com/download). **[Linux]** `curl -fsSL https://ollama.com/install.sh | sh` (perkhidmatan `ollama` dicipta automatik). Pastikan pemacu NVIDIA dipasang jika ada GPU.
2. **Model SEA-LION** (pengekstrakan medan), ikut `skrip/ollama/Modelfile.sea-lion-local`:
   - Muat turun `Llama-SEA-LION-v3.5-8B-R-Q4_K_M.gguf` (~4.9 GB) daripada https://huggingface.co/aisingapore/Llama-SEA-LION-v3.5-8B-R-GGUF
   - Semak SHA-256: `a6dae47bb2f7a5631bb126cab83f2abc8452c0488192a688474102630227b9c2`
   - Letakkan fail dalam folder `skrip/ollama/`, kemudian dalam folder itu: `ollama create sea-lion-local -f Modelfile.sea-lion-local`
3. **Model GLM-OCR** (tulisan tangan): `ollama pull glm-ocr:q8_0` (~1.8 GB)
4. Semak: `ollama list` menyenaraikan `sea-lion-local` dan `glm-ocr:q8_0`, kemudian `php artisan ai:panaskan`.

Tanpa GPU: alih keluar baris `OLLAMA_NUM_GPU` dalam `.env`. Tanpa Ollama langsung, sistem masih berfungsi (OCR Tesseract, isian manual), tetapi tiada isian automatik AI.

### 3.7 Proses latar: pekerja OCR/AI dan penjadual
Dua proses mesti sentiasa berjalan:
- **Pekerja latar OCR/AI** (*worker*, arahan `queue:work --queue=ocr`): memproses baris gilir (*queue*) analisis Imbas & Arkib, Imbas & Hantar dan butang "Jalankan OCR". **Hanya satu pekerja latar** dijalankan kerana GPU hanya memuatkan satu model pada satu masa.
- **Penjadual** (arahan `schedule:run` setiap minit): sandaran harian, pembersihan fail sementara dan semakan keselamatan.

**[Windows]** PowerShell sebagai pentadbir:
```
powershell -ExecutionPolicy Bypass -File C:\dms_pkink\skrip\pasang-tugas-windows.ps1 -Php "C:\php\php.exe"
```
Ini mendaftar tugas **"DMS PKINK Penjadual"** (setiap minit) dan **"DMS PKINK Worker OCR"** (bermula semasa pelayan dihidupkan dan dimulakan semula secara automatik). Pilihan `-Akaun` untuk akaun perkhidmatan khas; pilihan `-Buang` untuk mengalih keluar kedua-dua tugas.

**[Linux]**
```
sudo cp /var/www/dms_pkink/skrip/linux/dms-worker-ocr.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now dms-worker-ocr
sudo crontab -u www-data -e      # tambah baris dalam skrip/linux/crontab.txt
```

Tanpa pekerja latar, OCR/AI masih berfungsi tetapi pengguna perlu menunggu di halaman sehingga analisis selesai.

### 3.8 Antivirus
- **[Windows]** Microsoft Defender digunakan secara automatik (`MpCmdRun.exe`).
- **[Linux]** `sudo apt install clamav clamav-daemon && sudo systemctl enable --now clamav-daemon`. Kosongkan `ANTIVIRUS_BIN` (lalai `/usr/bin/clamdscan`) atau tetapkan laluan lain.

`ANTIVIRUS_WAJIB=true` menolak muat naik bila imbasan antivirus tidak dapat dibuat. Untuk ujian awal tanpa antivirus, tetapkan `false` buat sementara.

### 3.9 Pengoptimuman produksi
Selepas `.env` muktamad:
```
php artisan optimize
```
Selepas sebarang perubahan `.env`, jalankan semula `php artisan optimize` (atau `php artisan optimize:clear` untuk membuang cache).

---

## 4. Log masuk pertama & akaun UAT

1. Buka `APP_URL` dalam pelayar. Log masuk menggunakan **No. KP** dan kata laluan sementara dalam `AKAUN_UAT.pdf`.
2. Sistem mewajibkan **tukar kata laluan** pada log masuk pertama (sekurang-kurangnya 8 aksara, huruf dan nombor).
3. Akaun **SUPERADMIN/ADMIN** wajib mendaftar **2FA** (aplikasi pengesah seperti Google Authenticator / Microsoft Authenticator) bila `DUA_FAKTOR_WAJIB=true`. Simpan kod pemulihan yang dipaparkan.
4. Akaun UAT disediakan satu bagi setiap peranan:

| Peranan | Kegunaan dalam UAT |
|---|---|
| SUPERADMIN | Semua tetapan, termasuk peranan pengguna |
| ADMIN | Kakitangan, bahagian, kategori, templat, Status Sistem, Pusat Keselamatan |
| KERANI | Muat naik & Imbas & Arkib dokumen lama |
| PENGURUS | Dokumen bahagian sendiri |
| STAFF | Isi Borang (Isi Digital / Imbas & Hantar), dokumen sendiri |
| PENYOKONG | Menyokong / menolak permohonan |
| PELULUS | Meluluskan / menolak permohonan |
| AUDIT | Log audit & sejarah log masuk (baca sahaja) |

5. Selepas UAT, padam atau nyahaktifkan akaun ujian yang tidak diperlukan melalui **Pengurusan Kakitangan**, dan cipta akaun kakitangan sebenar (atau gunakan **Pengesahan Pendaftaran** bagi permohonan daripada halaman log masuk).

---

## 5. Semakan selepas pemasangan

Log masuk sebagai SUPERADMIN → **Status Sistem**. Setiap baris sepatutnya hijau:

| Semakan | Jika merah / kuning |
|---|---|
| Tesseract / pek bahasa | Laluan `OCR_TESSERACT_BIN` atau pek `msa` tiada (3.5) |
| Poppler | Laluan `OCR_PDFTOPPM_BIN` (3.5) |
| Ollama / model | Ollama tidak berjalan atau model belum didaftar (3.6) |
| Pekerja Latar OCR/AI | Tugas atau perkhidmatan pekerja latar (3.7) |
| Penjadual | Tugas/cron penjadual (3.7) |
| Sandaran | Jalankan `php artisan sandaran:jalan` sekali, semak `SANDARAN_MYSQLDUMP_BIN` |
| Notifikasi peranti | Kunci VAPID / `OPENSSL_CONF` (3.3) |
| Notifikasi emel | Tetapan `MAIL_*` |

Kemudian **Pusat Keselamatan** → Semakan Pengerasan (lihat bahagian 7).

**Ujian fungsi ringkas:**
1. KERANI: **Imbas & Arkib** satu borang yang diisi → medan diisi AI → semak → Simpan.
2. STAFF: **Isi Borang** → Isi Digital → Simpan & Hantar.
3. PENYOKONG → Sokong; PELULUS → Lulus; muat turun **slip kelulusan** dan **borang siap**, imbas kod QR.
4. Semak loceng notifikasi dan emel bagi setiap langkah.
5. AUDIT: semak **Log Audit** merekod semua tindakan di atas.

---

## 6. Sandaran & pemulihan

**Sandaran harian** (`php artisan sandaran:jalan`, dijadualkan pada `SANDARAN_MASA`): satu zip (disulitkan AES-256 bila `SANDARAN_KATA_LALUAN` ditetapkan) mengandungi pangkalan data, fail imbasan dan folder `Templat/`.

| Tetapan | Lalai | Maksud |
|---|---|---|
| `SANDARAN_AKTIF` | `true` | `false` bila pelayan menggunakan sandaran sendiri yang diuji (contohnya *snapshot* mesin maya); amaran "sandaran tertunggak" turut dimatikan |
| `SANDARAN_MASA` | `02:00` (`.env.example`) | Masa sandaran harian |
| `SANDARAN_FOLDER` | `storage/app/sandaran` | Disyorkan cakera/NAS berasingan |
| `SANDARAN_SALINAN` | (tiada) | Salinan kedua di lokasi lain |
| `SANDARAN_SIMPAN_HARI` / `_MINGGU` / `_BULAN` | 14 / 8 / 6 | Bilangan salinan yang disimpan |

**Uji sandaran** (sekurang-kurangnya sebulan sekali): `php artisan sandaran:uji`. Ia mengimport sandaran terkini ke pangkalan data sementara dan membandingkan bilangan baris, tanpa menyentuh DB sebenar.

**Salinan luar talian** (disyorkan mingguan, cakera luaran yang dicabut selepas itu): `php artisan sandaran:luar-talian E:\DMS_Sandaran` (salin + sahkan SHA-256).

**Pemulihan** (pemulihan pangkalan data memerlukan keistimewaan penuh; jalankan dengan akaun pentadbir pangkalan data atau gunakan `--ke-db` untuk semakan):
```
php artisan sandaran:pulih                      # sandaran terkini: DB + fail imbasan
php artisan sandaran:pulih <fail.zip> --pangkalan-data
php artisan sandaran:pulih <fail.zip> --fail-imbasan
php artisan sandaran:pulih <fail.zip> --ke-db=dms_pkink_semak    # import ke DB lain untuk semakan
```
Selepas pemulihan: `php artisan optimize:clear`, kemudian log masuk dan buka beberapa dokumen untuk memastikan fail imbasan dipaparkan.

---

## 7. Senarai semak keselamatan

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS dengan sijil sah; `SESSION_SECURE_COOKIE=true`
- [ ] Pengguna DB khas (`dms_app`) dengan keistimewaan terhad; akaun `root` MySQL mempunyai kata laluan
- [ ] MySQL hanya mendengar pada `127.0.0.1` (`bind-address = 127.0.0.1`) jika DB di pelayan yang sama
- [ ] Firewall: hanya port 443 (dan 80 untuk lencongan) dibuka kepada pengguna; port 3306 dan 11434 (Ollama) **tidak** dibuka ke rangkaian
- [ ] `DUA_FAKTOR_WAJIB=true`, `ANTIVIRUS_WAJIB=true`, antivirus aktif
- [ ] `SANDARAN_KATA_LALUAN` ditetapkan dan disimpan di luar pelayan; sandaran diuji (`sandaran:uji`); salinan luar talian mingguan
- [ ] `KESELAMATAN_IP_DIPERCAYAI` diisi dengan IP awam pejabat (jika ramai staf berkongsi satu IP)
- [ ] **Pusat Keselamatan**: semua semakan pengerasan hijau
- [ ] `docs/PELAN_TINDAK_BALAS_INSIDEN.pdf` dicetak dan disimpan di luar pelayan
- [ ] Akaun UAT yang tidak diperlukan dipadam selepas UAT

**Butang kecemasan:** Pusat Keselamatan menyediakan log keluar semua sesi, kunci akaun, sekat IP dan mod kecemasan. Dari baris arahan: `php artisan keselamatan:kecemasan on|off|status`.

---

## 8. Penyelenggaraan & kemas kini

| Tugas | Arahan / tindakan |
|---|---|
| Selepas mengemas kini kod sistem | `php artisan optimize:clear`, `php artisan optimize`, `php artisan queue:restart` |
| Selepas mengubah `.env` | `php artisan optimize` (atau `config:clear`) |
| Semak integriti fail imbasan | `php artisan fail:integriti` (hash setiap fail berbanding pangkalan data) |
| Log | `storage/logs/laravel-*.log`, `storage/logs/worker-ocr.log` |
| Ruang cakera | Pantau Status Sistem; sandaran lama dipadam automatik ikut tetapan simpanan |
| Perubahan skema DB | Hanya melalui skrip SQL yang dibekalkan, selepas sandaran |

---

## 9. Penyelesaian masalah

| Gejala | Punca & tindakan |
|---|---|
| Halaman putih / ralat 500 | Semak `storage/logs`; kebenaran `storage/` & `bootstrap/cache/`; `APP_KEY` kosong (`php artisan key:generate`) |
| Ralat 419 (sesi tamat) semasa log masuk | `SESSION_SECURE_COOKIE=true` tetapi diakses melalui `http://`; atau `APP_URL` tidak sepadan |
| "Tesseract not recognized" / OCR gagal | Laluan penuh binari dalam `.env` (3.5); pek bahasa `msa` |
| Medan tidak diisi AI | Ollama tidak berjalan, model belum didaftar, atau tiada GPU (lebih perlahan). Status Sistem → Ollama |
| Analisis kekal "Menunggu…" | Pekerja latar OCR tidak berjalan (3.7) |
| Muat naik ditolak "imbasan antivirus tidak dapat dibuat" | Antivirus tiada/tidak berjalan; pasang (3.8) atau `ANTIVIRUS_WAJIB=false` sementara |
| "Unable to create the key" (Web Push) | [Windows] `OPENSSL_CONF` (3.3) |
| Emel tidak sampai | Tetapan `MAIL_*`; semak `storage/logs` |
| Sandaran gagal "Access denied" | Pengguna DB tiada keistimewaan `LOCK TABLES`; laluan `SANDARAN_MYSQLDUMP_BIN` |
| Masa analisis sangat lama | Tiada GPU / VRAM kurang 6 GB; model dimuat sebahagian ke CPU |

---

## 10. Rujukan arahan

| Arahan | Fungsi |
|---|---|
| `php artisan key:generate` | Jana kunci aplikasi (sekali sahaja) |
| `php artisan webpush:vapid` | Jana kunci notifikasi peranti |
| `php artisan ai:panaskan` | Muatkan model AI ke memori (sebelum digunakan) |
| `php artisan sandaran:jalan` / `sandaran:uji` / `sandaran:pulih` / `sandaran:luar-talian <folder>` | Sandaran, uji, pulih, salinan luar talian |
| `php artisan fail:integriti` | Semak integriti fail imbasan |
| `php artisan imbas-arkib:bersih` | Padam fail sementara Imbas & Arkib (dijadualkan setiap hari) |
| `php artisan templat:daftar <fail> <KOD>` | Daftar templat borang kosong yang disalin terus ke `Templat/` |
| `php artisan keselamatan:kecemasan on\|off\|status` | Mod kecemasan |
| `php artisan queue:restart` | Mulakan semula pekerja latar selepas kemas kini |
| `php artisan optimize` / `optimize:clear` | Simpan / kosongkan cache tetapan dan laluan |

**Arahan yang tidak boleh dijalankan:** `migrate`, `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, `storage:link`.
