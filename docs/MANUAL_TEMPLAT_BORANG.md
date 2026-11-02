# Manual Pentadbir: Templat Borang Kosong

Pengguna sasaran: **Super Admin dan Pentadbir Sistem**, Sistem Arkib Digital (DMS) PKINK. Kerani Arkib boleh melihat templat tetapi tidak boleh mengubahnya.

Templat borang kosong ialah salinan borang yang **belum diisi** bagi satu kategori dokumen. Sistem menggunakan templat untuk empat tujuan:

| Kegunaan | Fungsi berkaitan | Perlu disusun? |
|---|---|---|
| Membezakan teks bercetak pada borang dengan tulisan pengisi, supaya AI tidak menyalin teks bercetak sebagai nilai | Imbas & Arkib, Imbas & Hantar | Tidak |
| Mencadangkan kategori yang betul apabila borang dimuat naik di bawah kategori yang salah | Skrin Semak & Sahkan | Tidak |
| **Borang siap** untuk dimuat turun: nilai ditulis pada borang asal, beserta cop status dan kod QR | Butiran Dokumen → Muat Turun Borang Siap | **Ya** |
| Perbandingan imej: kotak medan tanpa tulisan dianggap kosong, dan tulisan dibaca semula tanpa garisan kotak | Imbas & Arkib, Imbas & Hantar (tetapan `OCR_BEZA_TEMPLAT`, aktif secara lalai) | **Ya** |

---

## 1. Menyediakan fail borang kosong

1. Gunakan fail **asal** borang (Word atau PDF daripada bahagian pemilik borang), bukan imbasan borang yang telah diisi.
2. Simpan fail sebagai **PDF** (dalam Microsoft Word: *Fail → Simpan Sebagai → PDF*). PDF yang dijana daripada Word memberikan hasil paling jelas. Imbasan borang kosong (JPG, PNG atau PDF) juga boleh digunakan, dengan syarat imbasan lurus, terang dan tanpa contengan.
3. Pastikan versi borang **sama** dengan versi yang digunakan oleh kakitangan pada masa ini. Jika borang mempunyai beberapa versi (contohnya versi 2024 dan versi 2026), muat naik setiap versi sebagai templat berasingan (Bahagian 2).
4. Saiz fail maksimum ialah 10MB. Fail melalui semakan keselamatan yang sama seperti muat naik biasa (tiada arahan `/Launch` dan tiada fail terbenam).

> Fail templat disimpan dalam folder `Templat/` dalam folder utama sistem dan termasuk dalam sandaran harian.

## 2. Memuat naik templat

1. Buka menu **Kategori Dokumen**, pilih kategori berkenaan dan klik **Edit**.
2. Pada panel **TEMPLAT BORANG KOSONG** di sebelah kanan, pilih fail, isi ruangan *Catatan* (pilihan, contohnya "versi 2026"), kemudian klik **Muat Naik**.
3. Status OCR templat bertukar kepada **Selesai** dalam beberapa saat (melalui pekerja latar OCR/AI: **Dalam Giliran → Sedang Diproses → Selesai**; jika pekerja latar tidak berjalan, templat diproses serta-merta). Jika status menjadi **Ralat**, padam templat tersebut dan muat naik semula fail yang lebih jelas.
4. Klik **Pratonton** untuk memastikan fail yang betul telah dimuat naik.

Templat juga boleh dimuat naik semasa mencipta kategori baharu (**Cipta Kategori**).

**Versi templat.** Setiap muat naik baharu menjadi versi seterusnya. Versi lama **dikekalkan** supaya imbasan lama masih dapat dikenal pasti. Butang **Aktif / Tidak aktif** menentukan sama ada sesuatu versi digunakan. Butang **Padam** membuang templat daripada senarai; fail asal dikekalkan dalam folder `Templat/` untuk tujuan jejak audit, manakala imej cache templat dibuang.

> Templat tidak boleh diubah semasa sistem berada dalam **mod demo atau mod percubaan**, kerana folder `Templat/` dikongsi dengan sistem sebenar.

## 3. Menyusun kedudukan medan (untuk borang siap dan perbandingan imej)

Langkah ini dilaksanakan **sekali bagi setiap versi templat**.

1. Pada panel templat, klik **Susun Kedudukan** bagi versi berkenaan.
2. Klik **Cadang Automatik**. Sistem membaca label pada borang (contohnya "Nama Pemohon:") dan meletakkan kotak bagi setiap medan pada ruang isian selepas label tersebut.
3. **Semak setiap kotak.** Cadang Automatik kadangkala tersilap, terutamanya bagi:
   - tanda ✓ yang diletakkan pada **perkataan tajuk**, bukan pada kotak pilihan;
   - **lajur jadual yang sempit** (kotak lajur terlalu kecil atau bertindih);
   - kotak pilihan berbentuk **segi empat kecil** atau peluru senarai;
   - tarikh dalam ruang **bertitik** (`....../....../......`).
4. Cara membetulkan: pilih item di sebelah kanan, kemudian **seret pada borang** untuk melukis kotak. Seret kotak untuk mengalihkannya dan seret bucu kanan bawah untuk mengubah saiz. Kekunci <kbd>Delete</kbd> membuang kotak, manakala kekunci anak panah menggerakkan kotak sedikit demi sedikit.
5. Panduan mengikut jenis medan:
   - **Teks, nombor, tarikh dan teks panjang:** satu kotak yang meliputi **seluruh ruang tulisan** (bermula selepas label hingga hujung garisan isian, setinggi tulisan tangan biasa). Kotak yang terlalu sempit akan memotong tulisan pada borang siap dan menyebabkan sistem tersilap menganggap medan itu kosong.
   - **Pilihan (butang radio, senarai juntai bawah, kotak semak, ya/tidak):** satu kotak kecil bagi **setiap pilihan**. Pilih *gaya* **tanda** (✓ pada pilihan yang dipilih) atau **potong** (garisan pada pilihan yang TIDAK dipilih, contohnya "disokong/ ~~tidak disokong~~").
   - **Jadual:** *Baris pertama* menentukan kedudukan dan ketinggian satu baris; kotak setiap lajur hanya menentukan lebar lajur. Baris yang melebihi bilangan baris pada borang dicetak dalam halaman lampiran.
   - **Wang:** tandakan **Tanpa "RM"** jika borang telah mencetak "RM" sebelum ruang isian.
   - **Medan asas** (Tarikh Dokumen, No. Rujukan, Perkara) dan **Cop** (cop status dan kod QR) juga boleh diletakkan.
6. Medan yang **tiada ruang** pada borang (contohnya medan pengesahan yang hanya memerlukan tandatangan) dibiarkan tanpa kotak dan tidak akan dicetak.
7. Klik **Pratonton PDF**. Sistem menjana borang dengan nilai contoh. Pastikan setiap nilai berada pada kedudukan yang betul.
8. Klik **Simpan Susunan**. Tindakan ini direkodkan dalam log audit ("Susun Kedudukan Medan").

**Versi baharu mewarisi susunan.** Apabila versi baharu dimuat naik, susunan versi sebelumnya disalin sebagai permulaan tetapi **belum disahkan**. Panel akan memaparkan *"Borang siap: n medan diwarisi versi lama. Semak & simpan susunan untuk mengaktifkan."* Selagi susunan versi baharu belum disimpan, muat turun borang siap terus menggunakan versi lama.

## 4. Menyemak hasil

1. Buka mana-mana dokumen bagi kategori tersebut → **Butiran Dokumen** → **Muat Turun Borang Siap**. Cop yang dicetak mengikut status semasa (Diisi, Dalam Proses, Disokong, Diluluskan atau Ditolak). Kod QR pengesahan hanya dicetak apabila dokumen **Diluluskan**. Setiap muat turun direkodkan dalam log audit.
2. Imbas satu borang yang telah diisi melalui **Imbas & Arkib**. Skrin semakan sepatutnya:
   - tidak menyalin teks bercetak borang sebagai nilai medan;
   - memaparkan notis cadangan kategori jika borang dimuat naik di bawah kategori yang salah.

## 5. Masalah lazim

| Gejala | Punca dan tindakan |
|---|---|
| Status OCR templat kekal *Dalam Giliran* | Pekerja latar OCR/AI tidak berjalan. Semak **Status Sistem → Pekerja Latar OCR/AI**, atau maklumkan kepada pentadbir pelayan. |
| Status OCR *Ralat* atau *Tiada Teks Dikesan* | Fail kabur, imbasan terbalik atau PDF tanpa kandungan. Muat naik fail yang lebih jelas (sebaiknya PDF daripada Word). |
| Butang **Muat Turun Borang Siap** tidak dipaparkan | Templat aktif bagi kategori tersebut belum disusun, atau susunan versi baharu belum disimpan (Bahagian 3). |
| Nilai pada borang siap terkeluar daripada ruang | Kotak terlalu kecil atau salah kedudukan. Laraskan dalam Susun Kedudukan, kemudian semak semula melalui **Pratonton PDF**. |
| Tanda ✓ berada pada perkataan tajuk | Kesilapan lazim Cadang Automatik. Lukis semula kotak pilihan pada kotak atau perkataan pilihan yang betul. |
| AI masih menyalin teks bercetak | Pastikan templat berstatus **Aktif** dan versinya sama dengan borang yang diimbas. Versi yang berbeza susun aturnya tidak dapat dipadankan. |
| Medan yang telah diisi dianggap kosong | Kotak medan tidak meliputi ruang tulisan sebenar. Besarkan kotak supaya meliputi seluruh ruang tulisan. |
