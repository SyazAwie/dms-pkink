# Pelan Tindak Balas Insiden Keselamatan: Sistem Arkib Digital (DMS) PKINK

> **CETAK dokumen ini dan simpan salinan kertas di luar bilik pelayan.** Bila pelayan diserang atau disulitkan, anda mungkin tidak boleh membukanya dalam sistem.
> Untuk: Super Admin, Admin dan Pegawai Keselamatan ICT (ICTSO) PKINK. Kemas kini nama & nombor telefon di Bahagian 1 sebelum dicetak.

---

## 1. Hubungan kecemasan (isi sebelum dicetak)

| Peranan | Nama | Telefon |
|---|---|---|
| Super Admin sistem | | |
| Pegawai Keselamatan ICT (ICTSO) PKINK | | |
| Ketua Bahagian Digital | | |
| Penjaga kata laluan sandaran (`SANDARAN_KATA_LALUAN`) | | |

**Pihak luar:**
- **Cyber999 (MyCERT, CyberSecurity Malaysia)**: laporan insiden siber. Talian 1-300-88-2999, emel cyber999@cybersecurity.my. *Sahkan nombor terkini di laman rasmi CyberSecurity Malaysia.*
- **NACSA (Agensi Keselamatan Siber Negara)**: untuk agensi kerajaan; ikut saluran laporan insiden yang ditetapkan oleh ICTSO / kerajaan negeri.
- **Polis (PDRM)**: wajib dilaporkan bagi **peras ugut / wang tebusan** (jenayah).

---

## 2. Peraturan emas semasa insiden

1. **JANGAN bayar wang tebusan.** Bayaran tidak menjamin data dipulangkan, membiayai serangan seterusnya, dan menandakan PKINK sebagai sasaran yang membayar. Pemulihan dibuat daripada sandaran.
2. **JANGAN padam bukti.** Jangan padam log, nota tebusan atau fail yang disulitkan. Ambil gambar skrin dan catat masa.
3. **JANGAN berunding sendiri** dengan penyerang. Serahkan kepada ICTSO / pihak berkuasa.
4. **Asingkan dahulu, siasat kemudian.** Cabut kabel rangkaian / matikan Wi-Fi pelayan yang disyaki; jangan matikan kuasa (bukti dalam memori hilang) kecuali disulitkan sedang berjalan.
5. **Catat semuanya**: masa, apa yang dilihat, siapa yang bertindak (Borang Log Insiden, Bahagian 7).

---

## 3. Senario dan langkah

### A. Cubaan teka kata laluan / IP mencurigakan
*Tanda: amaran "Cubaan teka kata laluan dikesan", lonjakan log masuk gagal di Pusat Keselamatan.*
1. Buka **Pusat Keselamatan**. IP yang gagal 10 kali dalam 10 minit sudah **disekat automatik 1 jam**.
2. Jika berulang, klik **Sekat 24 j** atau sekat **kekal** pada IP itu.
3. Semak **Akaun Paling Disasarkan**: minta pemilik akaun menukar kata laluan dan hidupkan 2FA.
4. Tiada tindakan lanjut jika tiada log masuk berjaya yang mencurigakan.

### B. Akaun staf / pentadbir disyaki dicuri
*Tanda: amaran "Log masuk pentadbir dari IP baharu", tindakan pelik dalam Log Audit, pengguna melaporkan aktiviti yang bukan dibuatnya.*
1. Pusat Keselamatan → **Kunci Akaun** (sehingga dibuka) dengan sebab. Semua sesinya ditamatkan serta-merta.
2. Semak **Log Audit** (tapis ikut pengguna) dan **Sejarah Log Masuk**: apa yang telah dibuat, dari IP mana. **Sekat IP** penyerang.
3. Data yang dipadam: pulihkan di **Tong Sampah** (tiada padam kekal).
4. Bersama pemilik: tetapkan kata laluan sementara baharu (Pengurusan Kakitangan), **buka kunci**, dan wajibkan 2FA.
5. Jika akaun **Super Admin** dicuri dan anda tidak boleh log masuk: di pelayan jalankan
   `php artisan keselamatan:kecemasan on --sebab="akaun super admin dicuri"`, kemudian tukar kata laluan akaun itu melalui HeidiSQL/konsol bersama ICTSO.

### C. Data dipadam atau diubah oleh penyerang (dalam sistem)
*Tanda: amaran "padam beramai", rekod hilang, nilai dokumen berubah.*
1. Hentikan dahulu: **Kunci** akaun yang melakukannya. Jika tidak pasti siapa, hidupkan **Mod Kecemasan** (Super Admin): hanya Super Admin boleh log masuk dan sistem baca sahaja.
2. Rekod yang dipadam: **Tong Sampah → Pulihkan**.
3. Nilai yang diubah: Log Audit menyimpan **data sebelum/selepas** setiap perubahan. Betulkan secara manual.
4. Kerosakan besar: pulih ke pangkalan data **berasingan** untuk dibandingkan dahulu:
   `php artisan sandaran:pulih --pangkalan-data --ke-db=dms_semakan`
5. Matikan Mod Kecemasan bila selesai.

### D. RANSOMWARE / PERAS UGUT (paling serius)
*Tanda: nota tebusan, fail tidak boleh dibuka / sambungan pelik, sistem tidak berfungsi, emel ugutan "bayar atau data dipadam/didedahkan".*
1. **Asingkan pelayan** dari rangkaian (cabut kabel, matikan Wi-Fi). Jangan bayar. Jangan padam nota tebusan.
2. Maklumkan **ICTSO**, **Ketua Bahagian Digital**, kemudian **Cyber999** dan **polis** (peras ugut).
3. Jika sistem masih boleh dibuka: **Mod Kecemasan ON** (Pusat Keselamatan atau `php artisan keselamatan:kecemasan on`).
4. **Kenal pasti sandaran bersih**: guna **salinan luar talian** (cakera luaran yang dicabut). Sandaran dalam pelayan dan OneDrive mungkin turut disulitkan. Pilih sandaran **sebelum** tarikh serangan bermula (simpanan bertingkat menyimpan sandaran mingguan 8 minggu & bulanan 6 bulan).
5. **Bina semula pelayan yang bersih**: pasang semula Windows (atau pelayan baharu), Windows Update, Defender, XAMPP/Herd, Tesseract/Poppler, kod sistem daripada GitHub. **Jangan** guna semula pelayan yang dijangkiti tanpa format.
6. Pulihkan (lihat Bahagian 5): `php artisan sandaran:pulih E:\DMS_Sandaran\sandaran_YYYYMMDD_HHMMSS.zip`
7. **Tukar SEMUA rahsia** sebelum dibuka semula: kata laluan root & `dms_app` MySQL, `APP_KEY` (`php artisan key:generate`; slip kelulusan lama tidak sah lagi), kunci VAPID, kata laluan SMTP, `SANDARAN_KATA_LALUAN`, dan kata laluan semua pentadbir.
8. Pusat Keselamatan → **Log Keluar Semua**, kemudian matikan Mod Kecemasan. Pantau Pusat Keselamatan setiap hari selama 2 minggu.

### E. Fail berbahaya dimuat naik
*Tanda: amaran "Fail berbahaya ditolak".*
1. Fail itu **sudah ditolak** dan tidak disimpan. Semak siapa pemuat naik (Log Audit "Fail Berbahaya Ditolak").
2. Jika pengguna sah: komputernya mungkin dijangkiti; minta ICT imbas komputer tersebut.
3. Jika berulang dari akaun yang sama: **Kunci Akaun** dan siasat.

### F. Pelayan tidak boleh diakses / rosak (bukan serangan)
1. Semak kuasa, rangkaian, XAMPP (MySQL berjalan?), Herd/`php artisan serve`.
2. Cakera rosak: pasang pelayan baharu dan pulihkan daripada sandaran (Bahagian 5).

---

## 4. Rutin pencegahan

| Kekerapan | Tindakan |
|---|---|
| Harian (automatik) | Sandaran 12:30 (disulitkan), amaran ke loceng & telefon pentadbir |
| Mingguan (automatik) | Uji pemulihan sandaran (Rabu), semakan integriti fail (Isnin) |
| **Mingguan (manual)** | **Salinan luar talian**: `php artisan sandaran:luar-talian E:\DMS_Sandaran`, kemudian **CABUT** cakera. Gilirkan 2 cakera. |
| Mingguan | Buka Pusat Keselamatan: semua semakan hijau? |
| Bulanan | Windows Update, kemas kini XAMPP/Poppler/Tesseract, semak akaun yang tidak aktif |
| Setiap 6 bulan | Latihan: pulihkan sandaran luar talian ke `--ke-db` dan semak; tukar kata laluan pentadbir |

---

## 5. Pemulihan daripada sandaran (langkah tepat)

1. Pastikan `SANDARAN_KATA_LALUAN` dalam `.env` sama dengan kata laluan semasa sandaran dibuat.
2. Pemulihan ke pangkalan data sebenar perlu akaun MySQL yang boleh `DROP/CREATE` jadual. Dalam PowerShell, sementara untuk sesi itu sahaja:
   ```
   $env:DB_USERNAME = 'root'; $env:DB_PASSWORD = '<kata laluan root>'
   php artisan sandaran:pulih E:\DMS_Sandaran\sandaran_YYYYMMDD_HHMMSS.zip
   ```
   Taip `PULIHKAN` bila diminta. Sistem akan: hidupkan Mod Kecemasan → ambil sandaran keadaan semasa (bukti) → pulihkan pangkalan data & fail imbasan.
3. Semak beberapa dokumen dan fail. Jalankan `php artisan fail:integriti`.
4. Matikan Mod Kecemasan: `php artisan keselamatan:kecemasan off`.
5. Tanpa sistem langsung: zip sandaran boleh dibuka dengan **7-Zip** (kata laluan sandaran). Ia mengandungi `pangkalan_data/dms_pkink_db.sql` (import dalam HeidiSQL) dan `fail/` (salin ke `storage/app/private/`).

---

## 6. Pengerasan pelayan (lakukan SEKALI, kemudian semak di Pusat Keselamatan)

**6.1 Pengguna pangkalan data khas** (HeidiSQL, sebagai root). Gantikan `<KATA-LALUAN-KUAT>` (min 16 aksara):
```sql
CREATE USER IF NOT EXISTS 'dms_app'@'localhost' IDENTIFIED BY '<KATA-LALUAN-KUAT>';
CREATE USER IF NOT EXISTS 'dms_app'@'127.0.0.1' IDENTIFIED BY '<KATA-LALUAN-KUAT>';
-- Aplikasi: baca/tulis data sahaja (tiada DROP/ALTER pada jadual sebenar)
GRANT SELECT, INSERT, UPDATE, DELETE, SHOW VIEW, TRIGGER, LOCK TABLES ON `dms_pkink_db`.* TO 'dms_app'@'localhost', 'dms_app'@'127.0.0.1';
-- sandaran:uji: pangkalan data sementara sahaja
GRANT ALL PRIVILEGES ON `dms\_ujian\_pulih\_%`.* TO 'dms_app'@'localhost', 'dms_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```
Kemudian dalam `.env`: `DB_USERNAME=dms_app`, `DB_PASSWORD=<KATA-LALUAN-KUAT>`, jalankan `php artisan config:clear` dan mulakan semula pelayan. Uji: log masuk, muat naik, `php artisan sandaran:jalan`, `php artisan sandaran:uji`.

**6.2 Kata laluan root MySQL** (selepas 6.1 berfungsi):
```sql
ALTER USER IF EXISTS 'root'@'localhost' IDENTIFIED BY '<KATA-LALUAN-ROOT>';
ALTER USER IF EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '<KATA-LALUAN-ROOT>';
ALTER USER IF EXISTS 'root'@'::1' IDENTIFIED BY '<KATA-LALUAN-ROOT>';
FLUSH PRIVILEGES;
```
Kemas kini kata laluan dalam sesi HeidiSQL. Simpan kata laluan root di luar pelayan (bersama penjaga kata laluan).

**6.3 MySQL hanya untuk pelayan ini**: buka `C:\xampp\mysql\bin\my.ini`, di bawah `[mysqld]` tambah `bind-address=127.0.0.1`, simpan, kemudian Stop/Start MySQL dalam XAMPP Control Panel.

**6.4 Windows pelayan**: Windows Defender masa nyata HIDUP; Windows Update automatik; Windows Firewall HIDUP (jangan buka port 3306); akaun Windows berkata laluan kuat; Remote Desktop dimatikan jika tidak perlu; jangan guna pelayan untuk melayari web atau membuka emel.

**6.5 Sistem (`.env`) semasa serahan**: `APP_DEBUG=false`, `DUA_FAKTOR_WAJIB=true`, `ANTIVIRUS_WAJIB=true`, `SANDARAN_KATA_LALUAN=<kata laluan kuat>`; tukar semua kata laluan akaun demo `123456789`.

---

## 7. Borang Log Insiden (salin & isi)

| Perkara | Catatan |
|---|---|
| Tarikh & masa dikesan | |
| Dikesan oleh / bagaimana | |
| Jenis insiden (A–F) | |
| Sistem / akaun / IP terlibat | |
| Tindakan diambil (masa, oleh siapa) | |
| Pihak dimaklumkan (ICTSO, Cyber999, polis) & masa | |
| Sandaran digunakan (nama fail) | |
| Rahsia yang ditukar | |
| Tarikh sistem pulih sepenuhnya | |
| Punca & pengajaran | |
