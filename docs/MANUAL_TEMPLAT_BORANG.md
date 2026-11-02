# Manual Pentadbir: Templat Borang Kosong

Untuk: **SUPERADMIN / ADMIN** Sistem Arkib Digital (DMS) PKINK. KERANI boleh melihat templat tetapi tidak boleh mengubahnya.

Templat borang kosong ialah salinan borang yang **belum diisi** bagi satu kategori dokumen. Sistem menggunakannya untuk empat perkara:

| Kegunaan | Bila | Perlu disusun? |
|---|---|---|
| AI membezakan teks bercetak borang dengan tulisan pengisi (Fasa TB3) | Imbas & Arkib, Imbas & Hantar | Tidak |
| Cadangan kategori bila borang dimuat naik di bawah kategori yang salah (TB5) | Skrin semak | Tidak |
| **Borang siap** untuk dimuat turun: nilai ditulis atas borang asal + cop status + QR (TB6) | Butiran Dokumen → Muat Turun Borang Siap | **Ya** |
| Beza imej: kotak medan tanpa dakwat = kosong; tulisan dibaca semula tanpa garisan kotak (TB4) | Imbas & Arkib, Imbas & Hantar (`OCR_BEZA_TEMPLAT`, lalai hidup) | **Ya** |

---

## 1. Sediakan fail borang kosong

1. Gunakan fail **asal** borang (Word/PDF daripada bahagian pemilik borang), bukan imbasan borang yang sudah diisi.
2. Simpan sebagai **PDF** (Word: *Fail → Simpan Sebagai → PDF*). PDF daripada Word paling jelas. Imbasan borang kosong (JPG/PNG/PDF) juga boleh, tetapi mesti lurus, terang dan tanpa contengan.
3. Pastikan versi borang **sama** dengan yang digunakan oleh kakitangan sekarang. Jika borang ada beberapa versi (cth. versi 2024 dan 2026), muat naik setiap versi sebagai templat berasingan (langkah 2).
4. Saiz maksimum 10MB. Fail disemak keselamatan seperti muat naik biasa (tiada JavaScript `/Launch`, tiada fail terbenam).

> Fail templat disimpan dalam folder `Templat/` di akar projek dan termasuk dalam sandaran harian.

## 2. Muat naik templat

1. Menu **Kategori Dokumen** → pilih kategori → **Edit**.
2. Panel **TEMPLAT BORANG KOSONG** di sebelah kanan → pilih fail → isi *Catatan* (pilihan, cth. "versi 2026") → **Muat Naik**.
3. Status OCR templat bertukar kepada **Selesai** dalam beberapa saat (melalui worker OCR: **Dalam Giliran → Sedang Diproses → Selesai**; tanpa worker, terus diproses). Jika **Ralat**, padam dan muat naik semula fail yang lebih jelas.
4. Klik **Pratonton** untuk memastikan fail yang betul dimuat naik.

Kategori baharu juga boleh diberi templat terus semasa **Cipta Kategori**.

**Versi.** Setiap muat naik baharu menjadi versi seterusnya. Versi lama **kekal** supaya imbasan lama masih dikenali. Butang **Aktif / Tidak aktif** menentukan sama ada sesuatu versi digunakan. **Padam** membuang templat daripada senarai; fail asal kekal dalam `Templat/` untuk jejak audit, dan imej cache (`templat_cache/`) dibuang.

> Templat tidak boleh diubah dalam **mod demo/percubaan**, kerana folder `Templat/` dikongsi dengan sistem sebenar.

## 3. Susun kedudukan medan (untuk borang siap & beza imej)

Langkah ini dibuat **sekali bagi setiap versi templat**.

1. Dalam panel templat, klik **Susun Kedudukan** pada versi berkenaan.
2. Klik **Cadang Automatik**. Sistem membaca label pada borang (cth. "Nama Pemohon:") dan meletakkan kotak bagi setiap medan di ruang isian selepasnya.
3. **Semak setiap kotak.** Cadang Automatik kadang-kadang tersilap, terutamanya:
   - tanda ✓ diletak pada **perkataan tajuk**, bukan kotak pilihan;
   - **lajur jadual yang sempit** (kotak lajur terlalu kecil atau bertindih);
   - kotak pilihan berbentuk **segi empat** kecil atau peluru senarai;
   - tarikh dalam ruang **bertitik** (`....../....../......`).
4. Cara membetulkan: pilih item di sebelah kanan, kemudian **seret pada borang** untuk melukis kotaknya. Seret kotak untuk mengalih, seret bucu kanan bawah untuk mengubah saiz. Butang <kbd>Delete</kbd> membuang kotak, dan anak panah menggerakkannya sedikit.
5. Jenis medan:
   - **Teks / nombor / tarikh / textarea:** satu kotak yang meliputi **seluruh ruang tulisan** (dari selepas label hingga hujung garisan isian, setinggi tulisan tangan biasa). Kotak yang terlalu sempit memotong tulisan pada borang siap dan menyebabkan TB4 tersilap menganggap medan itu kosong.
   - **Pilihan (radio / dropdown / kotak semak / ya-tidak):** satu kotak kecil bagi **setiap pilihan**. Pilih *gaya* **tanda** (✓ pada pilihan dipilih) atau **potong** (garis pada pilihan yang TIDAK dipilih, cth. "disokong/ ~~tidak disokong~~").
   - **Jadual:** *Baris pertama* = kedudukan & tinggi satu baris; kotak setiap lajur hanya menentukan lebar lajur. Baris yang melebihi bilangan baris pada borang dicetak dalam lampiran.
   - **Wang:** tanda **Tanpa "RM"** jika borang sudah mencetak "RM" sebelum ruang isian.
   - **Medan asas** (Tarikh Dokumen, No. Rujukan, Perkara) dan **Cop** (cop status + QR) juga boleh diletak.
6. Medan yang **tiada tempat** pada borang (cth. "Disahkan" ya/tidak yang hanya tandatangan) biarkan tanpa kotak. Ia tidak dicetak.
7. Klik **Pratonton PDF**. Sistem menjana borang dengan nilai contoh. Semak setiap nilai jatuh di tempat yang betul.
8. Klik **Simpan Susunan**. Tindakan ini direkod dalam log audit ("Susun Kedudukan Medan").

**Versi baharu mewarisi susunan.** Bila versi baharu dimuat naik, susunan versi sebelumnya disalin sebagai permulaan tetapi **belum disahkan**. Panel memaparkan *"Borang siap: n medan diwarisi versi lama. Semak & simpan susunan untuk mengaktifkan."* Sehingga anda menyimpan susunan versi baharu, muat turun borang siap terus menggunakan versi lama.

## 4. Semak hasilnya

1. Buka mana-mana dokumen kategori itu → **Butiran Dokumen** → **Muat Turun Borang Siap**. Cop mengikut status semasa (Diisi / Dalam Proses / Disokong / Diluluskan / Ditolak). QR pengesahan hanya dicetak bila **Diluluskan**. Setiap muat turun direkod dalam log audit.
2. Imbas satu borang yang sudah diisi melalui **Imbas & Arkib**. Skrin semak sepatutnya:
   - tidak menyalin teks bercetak borang sebagai nilai;
   - memaparkan notis cadangan kategori jika borang dimuat naik di bawah kategori yang salah.
3. Ukur ketepatan (pembangun): `php artisan ocr:nilai` atas dokumen berlabel dalam `../Ujian_Borang_Diisi/`. Banding `--tanpa-templat` (tanpa Fasa TB) dan `--tanpa-beza` / `--beza` (TB4). Lihat README 5.11.

## 5. Masalah biasa

| Gejala | Punca & tindakan |
|---|---|
| Status OCR templat kekal *Dalam Giliran* | Worker OCR tidak berjalan. Semak **Status Sistem** → Queue OCR, atau mulakan tugas "DMS PKINK Worker OCR". |
| Status OCR *Ralat* / *Tiada Teks Dikesan* | Fail kabur, imbasan terbalik, atau PDF tanpa kandungan. Muat naik fail yang lebih jelas (PDF daripada Word). |
| Butang **Muat Turun Borang Siap** tiada | Templat aktif kategori belum disusun, atau susunan versi baharu belum disimpan (langkah 3). |
| Nilai pada borang siap terkeluar dari ruang | Kotak terlalu kecil/salah tempat. Laraskan dalam Susun Kedudukan, kemudian semak **Pratonton PDF**. |
| ✓ jatuh pada perkataan tajuk | Kesilapan biasa Cadang Automatik. Lukis semula kotak pilihan di atas kotak/perkataan pilihan yang betul. |
| AI masih menyalin teks bercetak | Pastikan templat **Aktif** dan sama versi dengan borang yang diimbas. Versi berbeza (susun atur berubah) tidak dipadankan. |
| Medan berisi dianggap kosong (TB4) | Kotak medan tidak meliputi tempat tulisan sebenar. Besarkan kotak supaya meliputi seluruh ruang tulisan. |
