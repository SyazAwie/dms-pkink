# Pelan Tindak Balas Insiden Keselamatan: Sistem Arkib Digital (DMS) PKINK

> **Cetak dokumen ini dan simpan salinan bercetak di luar bilik pelayan.** Sekiranya pelayan diserang atau datanya disulitkan, dokumen dalam pelayan mungkin tidak dapat dibuka.
>
> Pengguna sasaran: Super Admin, Pentadbir Sistem dan Pegawai Keselamatan ICT (ICTSO) PKINK. Lengkapkan nama dan nombor telefon dalam Bahagian 1 sebelum dokumen ini dicetak.

---

## 1. Senarai hubungan kecemasan (lengkapkan sebelum dicetak)

| Peranan | Nama | Telefon |
|---|---|---|
| Super Admin sistem | | |
| Pegawai Keselamatan ICT (ICTSO) PKINK | | |
| Ketua Bahagian Digital | | |
| Pentadbir pelayan | | |
| Penjaga kata laluan sandaran (`SANDARAN_KATA_LALUAN`) | | |

**Pihak luar:**
- **Cyber999 (MyCERT, CyberSecurity Malaysia):** saluran laporan insiden siber. Talian 1-300-88-2999, emel cyber999@cybersecurity.my. *Sahkan maklumat terkini di laman rasmi CyberSecurity Malaysia.*
- **NACSA (Agensi Keselamatan Siber Negara):** bagi agensi kerajaan, ikut saluran laporan insiden yang ditetapkan oleh ICTSO atau Kerajaan Negeri.
- **Polis Diraja Malaysia (PDRM):** laporan polis wajib dibuat bagi kes **peras ugut atau tuntutan wang tebusan**.

---

## 2. Prinsip utama semasa insiden

1. **Jangan membayar wang tebusan.** Bayaran tidak menjamin data dipulangkan, membiayai serangan seterusnya dan menjadikan PKINK sasaran berulang. Pemulihan dibuat daripada sandaran.
2. **Jangan memadam bukti.** Jangan padam log, nota tebusan atau fail yang disulitkan. Ambil tangkapan skrin dan catat masa.
3. **Jangan berunding dengan penyerang.** Serahkan urusan kepada ICTSO dan pihak berkuasa.
4. **Asingkan dahulu, siasat kemudian.** Putuskan sambungan rangkaian pelayan yang disyaki. Jangan matikan kuasa pelayan (bukti dalam memori akan hilang), kecuali proses penyulitan sedang berlaku.
5. **Rekodkan setiap tindakan:** masa, pemerhatian dan pegawai yang bertindak (Borang Log Insiden, Bahagian 7).

---

## 3. Senario dan langkah tindakan

### A. Cubaan meneka kata laluan atau alamat IP mencurigakan
*Petunjuk: amaran "Cubaan teka kata laluan dikesan" atau peningkatan log masuk gagal dalam Pusat Keselamatan.*
1. Buka **Pusat Keselamatan**. Alamat IP yang gagal log masuk 10 kali dalam tempoh 10 minit telah **disekat secara automatik selama 1 jam**.
2. Jika cubaan berulang, klik **Sekat 24 j** atau sekat alamat IP tersebut secara **kekal**.
3. Semak senarai **Akaun Paling Disasarkan**: minta pemilik akaun menukar kata laluan dan mengaktifkan 2FA.
4. Tiada tindakan lanjut diperlukan jika tiada log masuk berjaya yang mencurigakan.

### B. Akaun kakitangan atau pentadbir disyaki telah dicerobohi
*Petunjuk: amaran "Log masuk pentadbir dari IP baharu", tindakan luar biasa dalam Log Audit, atau pengguna melaporkan aktiviti yang tidak dilakukannya.*
1. Dalam Pusat Keselamatan, klik **Kunci Akaun** dan nyatakan sebabnya. Semua sesi akaun tersebut ditamatkan serta-merta.
2. Semak **Log Audit** (tapis mengikut pengguna) dan **Sejarah Log Masuk** untuk mengenal pasti tindakan yang dilakukan dan alamat IP yang digunakan. **Sekat alamat IP** penyerang.
3. Rekod yang dipadam boleh dipulihkan melalui **Tong Sampah**, kerana sistem tidak memadam rekod secara kekal.
4. Bersama pemilik akaun: tetapkan kata laluan sementara baharu (Pengurusan Kakitangan), **buka kunci** akaun dan wajibkan 2FA.
5. Jika akaun **Super Admin** dicerobohi dan tidak dapat diakses, jalankan arahan berikut di pelayan:
   `php artisan keselamatan:kecemasan on --sebab="akaun super admin dicerobohi"`
   Kemudian tetapkan semula kata laluan akaun tersebut bersama ICTSO melalui pentadbir pangkalan data. Jika 2FA akaun itu turut perlu ditetapkan semula: `php artisan pengguna:set-semula-2fa <No. KP>`.

### C. Data dipadam atau diubah oleh pihak tidak sah (dalam sistem)
*Petunjuk: amaran pemadaman beramai-ramai, rekod hilang atau nilai dokumen berubah.*
1. Hentikan tindakan tersebut terlebih dahulu: **kunci** akaun yang terlibat. Jika akaun tidak dapat dikenal pasti, aktifkan **Mod Kecemasan** (Super Admin): hanya Super Admin boleh log masuk dan sistem menjadi baca sahaja.
2. Rekod yang dipadam: **Tong Sampah → Pulihkan**.
3. Nilai yang diubah: Log Audit menyimpan **data sebelum dan selepas** setiap perubahan. Betulkan nilai tersebut secara manual.
4. Bagi kerosakan besar, pulihkan sandaran ke pangkalan data **berasingan** untuk perbandingan terlebih dahulu:
   `php artisan sandaran:pulih --pangkalan-data --ke-db=dms_semakan`
5. Nyahaktifkan Mod Kecemasan setelah selesai.

### D. Perisian tebusan (ransomware) atau peras ugut (paling serius)
*Petunjuk: nota tebusan, fail tidak dapat dibuka atau mempunyai sambungan nama yang luar biasa, sistem tidak berfungsi, atau emel ugutan untuk membayar sebelum data dipadam atau didedahkan.*
1. **Asingkan pelayan** daripada rangkaian. Jangan membayar tebusan dan jangan padam nota tebusan.
2. Maklumkan kepada **ICTSO** dan **Ketua Bahagian Digital**, kemudian kepada **Cyber999** dan **PDRM**.
3. Jika sistem masih boleh diakses, aktifkan **Mod Kecemasan** (Pusat Keselamatan atau `php artisan keselamatan:kecemasan on`).
4. **Kenal pasti sandaran yang bersih:** gunakan **salinan luar talian** (cakera luaran yang telah ditanggalkan). Sandaran dalam pelayan atau storan rangkaian yang bersambung mungkin turut disulitkan. Pilih sandaran yang dibuat **sebelum** tarikh serangan bermula (simpanan bertingkat menyimpan sandaran mingguan selama 8 minggu dan bulanan selama 6 bulan).
5. **Bina semula pelayan yang bersih:** pasang semula sistem pengendalian (atau gunakan pelayan baharu), kemas kini keselamatan, antivirus, perisian pelayan web dan pangkalan data, Tesseract, Poppler serta Ollama, kemudian pasang sistem daripada pakej pemasangan rasmi (rujuk Panduan Pemasangan). **Jangan** menggunakan semula pelayan yang dijangkiti tanpa diformat.
6. Pulihkan data (rujuk Bahagian 5): `php artisan sandaran:pulih <laluan fail sandaran.zip>`
7. **Tukar semua maklumat rahsia** sebelum sistem dibuka semula: kata laluan pentadbir dan pengguna aplikasi pangkalan data, `APP_KEY` (`php artisan key:generate`; slip kelulusan lama tidak lagi sah), kunci VAPID, kata laluan SMTP, `SANDARAN_KATA_LALUAN` dan kata laluan semua pentadbir sistem.
8. Dalam Pusat Keselamatan, klik **Log Keluar Semua**, kemudian nyahaktifkan Mod Kecemasan. Pantau Pusat Keselamatan setiap hari selama dua minggu.

### E. Fail berbahaya dimuat naik
*Petunjuk: amaran "Fail berbahaya ditolak".*
1. Fail tersebut **telah ditolak** dan tidak disimpan. Kenal pasti pemuat naik melalui Log Audit (tindakan "Fail Berbahaya Ditolak").
2. Jika pemuat naik ialah pengguna sah, komputernya mungkin dijangkiti. Minta Bahagian Digital mengimbas komputer tersebut.
3. Jika berulang daripada akaun yang sama, **kunci akaun** tersebut dan jalankan siasatan.

### F. Pelayan tidak dapat diakses atau rosak (bukan serangan)
1. Semak bekalan kuasa, rangkaian, perkhidmatan pangkalan data dan pelayan web.
2. Jika cakera rosak, sediakan pelayan baharu dan pulihkan data daripada sandaran (Bahagian 5).

---

## 4. Rutin pencegahan

| Kekerapan | Tindakan |
|---|---|
| Harian (automatik) | Sandaran disulitkan pada masa `SANDARAN_MASA`; amaran kepada loceng notifikasi dan peranti pentadbir |
| Mingguan (automatik) | Ujian pemulihan sandaran (Rabu) dan semakan integriti fail imbasan (Isnin) |
| **Mingguan (manual)** | **Salinan luar talian:** `php artisan sandaran:luar-talian <folder cakera luaran>`, kemudian **tanggalkan** cakera tersebut. Gunakan dua cakera secara bergilir. |
| Mingguan | Semak Pusat Keselamatan: semua semakan berstatus hijau |
| Bulanan | Kemas kini keselamatan sistem pengendalian dan perisian pelayan; semak akaun yang tidak aktif |
| Setiap 6 bulan | Latihan pemulihan: pulihkan sandaran luar talian menggunakan `--ke-db` dan semak hasilnya; tukar kata laluan pentadbir |

---

## 5. Pemulihan daripada sandaran

1. Pastikan nilai `SANDARAN_KATA_LALUAN` dalam `.env` sama dengan kata laluan semasa sandaran dibuat.
2. Pemulihan ke pangkalan data sebenar memerlukan akaun pangkalan data yang mempunyai keistimewaan `DROP` dan `CREATE`. Tetapkan akaun tersebut bagi sesi terminal semasa sahaja:
   - [Windows, PowerShell] `$env:DB_USERNAME = 'root'; $env:DB_PASSWORD = '<kata laluan pentadbir DB>'`
   - [Linux] `DB_USERNAME=root DB_PASSWORD='<kata laluan pentadbir DB>' php artisan sandaran:pulih <fail.zip>`

   Kemudian jalankan `php artisan sandaran:pulih <laluan fail sandaran.zip>` dan taip `PULIHKAN` apabila diminta. Sistem akan mengaktifkan Mod Kecemasan, mengambil sandaran keadaan semasa (sebagai bukti), kemudian memulihkan pangkalan data dan fail imbasan.
3. Semak beberapa dokumen dan fail imbasan, kemudian jalankan `php artisan fail:integriti`.
4. Nyahaktifkan Mod Kecemasan: `php artisan keselamatan:kecemasan off`.
5. Jika sistem tidak dapat dijalankan langsung: fail zip sandaran boleh dibuka menggunakan **7-Zip** dengan kata laluan sandaran. Kandungannya ialah `pangkalan_data/<nama pangkalan data>.sql` (diimport melalui alat pengurusan pangkalan data) dan folder `fail/` (disalin ke `storage/app/private/`).

---

## 6. Pengerasan pelayan

Langkah pengerasan terperinci (pengguna pangkalan data khas, kata laluan pentadbir pangkalan data, had alamat pendengaran pangkalan data, tembok api, HTTPS dan antivirus) terdapat dalam **Panduan Pemasangan, Bahagian 7: Senarai Semak Keselamatan**. Selepas langkah tersebut dilaksanakan, semak status setiap item dalam **Pusat Keselamatan → Semakan Pengerasan**.

Tetapan minimum dalam `.env` bagi pelayan produksi:
- `APP_ENV=production` dan `APP_DEBUG=false`
- `DUA_FAKTOR_WAJIB=true` dan `ANTIVIRUS_WAJIB=true`
- `SANDARAN_KATA_LALUAN` ditetapkan dengan kata laluan yang kukuh dan disimpan di luar pelayan

Sebelum sistem digunakan secara rasmi, pastikan semua akaun UAT telah dipadam atau dinyahaktifkan, dan setiap akaun pentadbir telah menukar kata laluan sementara serta mendaftar 2FA.

---

## 7. Borang Log Insiden (salin dan lengkapkan)

| Perkara | Catatan |
|---|---|
| Tarikh dan masa insiden dikesan | |
| Dikesan oleh / kaedah pengesanan | |
| Jenis insiden (A–F) | |
| Sistem, akaun atau alamat IP yang terlibat | |
| Tindakan yang diambil (masa dan pegawai) | |
| Pihak yang dimaklumkan (ICTSO, Cyber999, PDRM) dan masa | |
| Sandaran yang digunakan (nama fail) | |
| Maklumat rahsia yang telah ditukar | |
| Tarikh sistem pulih sepenuhnya | |
| Punca insiden dan pengajaran | |
