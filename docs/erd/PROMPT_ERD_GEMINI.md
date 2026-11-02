Anda ialah pakar reka bentuk pangkalan data. Hasilkan ERD (Entity Relationship Diagram) yang LENGKAP dan TEPAT bagi sistem di bawah. Skema ini dieksport terus daripada pangkalan data MySQL/MariaDB sebenar (INFORMATION_SCHEMA), jadi ia adalah sumber kebenaran: JANGAN tambah, buang, namakan semula atau teka mana-mana jadual, lajur atau hubungan.

## Konteks sistem
Sistem Arkib Digital (DMS) PKINK (Perbadanan Kemajuan Iktisad Negeri Kelantan): Laravel 13, MySQL. Pengurusan dokumen dan e-borang dengan aliran kelulusan, OCR/AI dan audit. Nama jadual/lajur dalam Bahasa Melayu.

Konsep penting yang mesti jelas dalam ERD:
1. **Model EAV (Entity-Attribute-Value) untuk borang dinamik:** `jenis_dokumen` (kategori/borang) → `dokumen_field` (definisi medan setiap kategori) → `dokumen_data` (nilai: satu baris bagi setiap pasangan dokumen + medan). Satu `dokumen` tergolong dalam satu `jenis_dokumen`, dan nilainya disimpan dalam `dokumen_data`. Tiada lajur khusus bagi setiap jenis borang.
2. **Kunci primer tidak standard:** setiap jadual menggunakan nama PK sendiri (`user_id`, `dokumen_id`, `roles_id`, ...), bukan `id`.
3. **Perangkap penamaan:** jadual simpang `user_roles` menggunakan lajur `role_id`, yang merujuk `roles.roles_id` (PK roles ialah `roles_id`, bukan `role_id`). Lukis hubungan ini dengan betul.
4. **Peranan (RBAC):** pengguna boleh ada banyak peranan melalui `user_roles` (Many-to-Many users ↔ roles). 8 peranan: SUPERADMIN, ADMIN, KERANI, PENGURUS, STAFF, PENYOKONG, PELULUS, AUDIT.
5. **Aliran kelulusan:** satu `borang` bagi setiap `dokumen` (1:1 secara logik, dikuatkuasakan oleh aplikasi; DB tiada UNIQUE pada `borang.dokumen_id`). `borang` merujuk `users` sebanyak 4 kali dengan peranan berbeza: pemohon, pegawai penyokong, pegawai pelulus, pegawai penolak. `borang_sejarah` ialah sejarah setiap tindakan.
6. **Soft delete:** jadual yang ada `deleted_at` (bahagian, users, jenis_dokumen, dokumen_field, dokumen, templat_borang) tidak dipadam secara fizikal.
7. **Keunikan melalui lajur dijana (GENERATED):** cth. `users.email_aktif` = email jika belum dipadam, dengan UNIQUE. Tunjukkan lajur dijana sebagai atribut biasa dengan komen "dijana" dan tanda UK.

## Skema penuh (22 jadual yang dimasukkan ke dalam ERD)
Format: `nama_lajur jenis [tanda]`, diikuti `UNIQUE (...)` dan `FK (lajur) -> jadual(lajur) ON DELETE ...`.

```
TABLE bahagian
  bahagian_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  kod_bahagian varchar(50) [NOT NULL]
  nama_bahagian varchar(255) [NOT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  kod_bahagian_aktif varchar(50) [GENERATED AS (if(`deleted_at` is null,`kod_bahagian`,NULL))]
  UNIQUE (kod_bahagian_aktif)

TABLE borang
  borang_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  dokumen_id bigint(20) unsigned [NOT NULL]
  pemohon_id bigint(20) unsigned [NOT NULL]
  pegawai_penyokong_id bigint(20) unsigned [DEFAULT NULL]
  pegawai_pelulus_id bigint(20) unsigned [DEFAULT NULL]
  pegawai_penolak_id bigint(20) unsigned [DEFAULT NULL]
  status_permohonan varchar(50) [NOT NULL, DEFAULT 'Archived']
  tarikh_permohonan datetime [DEFAULT current_timestamp()]
  tarikh_sokongan datetime [DEFAULT NULL]
  tarikh_kelulusan datetime [DEFAULT NULL]
  tarikh_tolak datetime [DEFAULT NULL]
  ulasan_penyokong text [DEFAULT NULL]
  ulasan_pelulus text [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  FK (dokumen_id) -> dokumen(dokumen_id) ON DELETE CASCADE
  FK (pegawai_pelulus_id) -> users(user_id) ON DELETE SET NULL
  FK (pemohon_id) -> users(user_id) ON DELETE CASCADE
  FK (pegawai_penolak_id) -> users(user_id) ON DELETE SET NULL
  FK (pegawai_penyokong_id) -> users(user_id) ON DELETE SET NULL

TABLE borang_sejarah
  borang_sejarah_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  borang_id bigint(20) unsigned [NOT NULL]
  tindakan varchar(30) [NOT NULL]
  status_selepas varchar(50) [NOT NULL]
  user_id bigint(20) unsigned [DEFAULT NULL]
  bagi_pihak_id bigint(20) unsigned [DEFAULT NULL]
  ulasan text [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  FK (bagi_pihak_id) -> users(user_id) ON DELETE SET NULL
  FK (borang_id) -> borang(borang_id) ON DELETE CASCADE
  FK (user_id) -> users(user_id) ON DELETE SET NULL

TABLE delegasi_kelulusan
  delegasi_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  pemberi_id bigint(20) unsigned [NOT NULL]
  penerima_id bigint(20) unsigned [NOT NULL]
  kod_peranan varchar(20) [NOT NULL]
  tarikh_mula date [NOT NULL]
  tarikh_tamat date [NOT NULL]
  sebab varchar(255) [DEFAULT NULL]
  dicipta_oleh bigint(20) unsigned [DEFAULT NULL]
  dibatalkan_pada timestamp [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  FK (dicipta_oleh) -> users(user_id) ON DELETE SET NULL
  FK (pemberi_id) -> users(user_id) ON DELETE CASCADE
  FK (penerima_id) -> users(user_id) ON DELETE CASCADE

TABLE dokumen
  dokumen_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  jenis_dokumen_id bigint(20) unsigned [NOT NULL]
  bahagian_id bigint(20) unsigned [DEFAULT NULL]
  no_rujukan varchar(100) [NOT NULL]
  tarikh_dokumen date [NOT NULL]
  perkara varchar(255) [DEFAULT NULL]
  status varchar(50) [NOT NULL, DEFAULT 'Archived']
  tarikh_tamat_simpanan date [DEFAULT NULL]
  status_pelupusan varchar(30) [DEFAULT NULL]
  tarikh_pelupusan datetime [DEFAULT NULL]
  pelupusan_oleh bigint(20) unsigned [DEFAULT NULL]
  catatan_pelupusan varchar(255) [DEFAULT NULL]
  created_by bigint(20) unsigned [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  UNIQUE (no_rujukan)
  FK (bahagian_id) -> bahagian(bahagian_id) ON DELETE SET NULL
  FK (created_by) -> users(user_id) ON DELETE SET NULL
  FK (jenis_dokumen_id) -> jenis_dokumen(jenis_dokumen_id) ON DELETE RESTRICT
  FK (pelupusan_oleh) -> users(user_id) ON DELETE SET NULL

TABLE dokumen_data
  dokumen_data_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  dokumen_id bigint(20) unsigned [NOT NULL]
  dokumen_field_id bigint(20) unsigned [NOT NULL]
  nilai_data longtext [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  UNIQUE (dokumen_id,dokumen_field_id)
  FK (dokumen_id) -> dokumen(dokumen_id) ON DELETE CASCADE
  FK (dokumen_field_id) -> dokumen_field(dokumen_field_id) ON DELETE RESTRICT

TABLE dokumen_field
  dokumen_field_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  jenis_dokumen_id bigint(20) unsigned [NOT NULL]
  nama_field varchar(100) [NOT NULL]
  kod_field varchar(100) [NOT NULL]
  jenis_data varchar(50) [NOT NULL, DEFAULT 'text']
  pilihan longtext [DEFAULT NULL]
  tetapan longtext [DEFAULT NULL]
  is_required tinyint(1) [NOT NULL, DEFAULT 0]
  susunan int(11) [NOT NULL, DEFAULT 1]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  kod_field_aktif varchar(160) [GENERATED AS (if(`deleted_at` is null,concat(`jenis_dokumen_id`,'|',`kod_field`),NULL))]
  UNIQUE (kod_field_aktif)
  FK (jenis_dokumen_id) -> jenis_dokumen(jenis_dokumen_id) ON DELETE CASCADE

TABLE dokumen_ocr
  dokumen_ocr_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  dokumen_scan_id bigint(20) unsigned [NOT NULL]
  teks_dibaca longtext [DEFAULT NULL]
  skor_padanan decimal(5,2) [DEFAULT NULL]
  status_semakan varchar(50) [NOT NULL, DEFAULT 'Belum Disemak']
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  FK (dokumen_scan_id) -> dokumen_scan(dokumen_scan_id) ON DELETE CASCADE

TABLE dokumen_scan
  dokumen_scan_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  dokumen_id bigint(20) unsigned [NOT NULL]
  nama_fail varchar(255) [NOT NULL]
  lokasi_fail varchar(500) [NOT NULL]
  saiz_fail bigint(20) unsigned [DEFAULT NULL]
  jenis_mime varchar(100) [DEFAULT NULL]
  hash_sha256 char(64) [DEFAULT NULL]
  tarikh_scan datetime [NOT NULL, DEFAULT current_timestamp()]
  status_ocr varchar(50) [NOT NULL, DEFAULT 'Pending']
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  FK (dokumen_id) -> dokumen(dokumen_id) ON DELETE CASCADE

TABLE ip_disekat
  ip_disekat_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  alamat_ip varchar(45) [NOT NULL]
  sebab varchar(255) [DEFAULT NULL]
  automatik tinyint(1) [NOT NULL, DEFAULT 0]
  disekat_oleh bigint(20) unsigned [DEFAULT NULL]
  tamat_pada timestamp [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  FK (disekat_oleh) -> users(user_id) ON DELETE SET NULL

TABLE jenis_dokumen
  jenis_dokumen_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  kod_dokumen varchar(50) [NOT NULL]
  nama_dokumen varchar(255) [NOT NULL]
  kategori varchar(100) [DEFAULT NULL]
  tempoh_simpanan_tahun smallint(5) unsigned [DEFAULT NULL]
  tindakan_tamat_tempoh varchar(30) [NOT NULL, DEFAULT 'Semak Semula']
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  kod_dokumen_aktif varchar(50) [GENERATED AS (if(`deleted_at` is null,`kod_dokumen`,NULL))]
  UNIQUE (kod_dokumen_aktif)

TABLE log_audit
  log_audit_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  user_id bigint(20) unsigned [DEFAULT NULL]
  tindakan varchar(100) [NOT NULL]
  nama_jadual varchar(100) [NOT NULL]
  rekod_id bigint(20) unsigned [DEFAULT NULL]
  data_lama longtext [DEFAULT NULL]
  data_baharu longtext [DEFAULT NULL]
  alamat_ip varchar(45) [DEFAULT NULL]
  peranti_pengguna text [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  FK (user_id) -> users(user_id) ON DELETE SET NULL

TABLE log_masuk
  log_masuk_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  user_id bigint(20) unsigned [DEFAULT NULL]
  berjaya tinyint(1) [NOT NULL, DEFAULT 0]
  sebab varchar(50) [DEFAULT NULL]
  alamat_ip varchar(45) [DEFAULT NULL]
  peranti_pengguna text [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  FK (user_id) -> users(user_id) ON DELETE SET NULL

TABLE notifications
  id char(36) [PK]
  type varchar(255) [NOT NULL]
  notifiable_type varchar(255) [NOT NULL]
  notifiable_id bigint(20) unsigned [NOT NULL]
  data text [NOT NULL]
  read_at timestamp [DEFAULT NULL]
  created_at timestamp [DEFAULT NULL]
  updated_at timestamp [DEFAULT NULL]

TABLE pembetulan_ai
  pembetulan_ai_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  dokumen_id bigint(20) unsigned [NOT NULL]
  jenis_dokumen_id bigint(20) unsigned [NOT NULL]
  dokumen_field_id bigint(20) unsigned [NOT NULL]
  nilai_ai text [DEFAULT NULL]
  nilai_disimpan text [DEFAULT NULL]
  sama tinyint(1) [NOT NULL, DEFAULT 0]
  model_ai varchar(100) [DEFAULT NULL]
  user_id bigint(20) unsigned [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  FK (dokumen_id) -> dokumen(dokumen_id) ON DELETE CASCADE
  FK (dokumen_field_id) -> dokumen_field(dokumen_field_id) ON DELETE CASCADE
  FK (jenis_dokumen_id) -> jenis_dokumen(jenis_dokumen_id) ON DELETE CASCADE
  FK (user_id) -> users(user_id) ON DELETE SET NULL

TABLE pendaftaran_staf
  pendaftaran_staf_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  ic_pekerja varchar(20) [NOT NULL]
  nama_staff varchar(255) [NOT NULL]
  email varchar(255) [NOT NULL]
  bahagian_id bigint(20) unsigned [NOT NULL]
  status varchar(20) [NOT NULL, DEFAULT 'Menunggu']
  sebab_tolak text [DEFAULT NULL]
  disemak_oleh bigint(20) unsigned [DEFAULT NULL]
  disemak_pada timestamp [DEFAULT NULL]
  user_id bigint(20) unsigned [DEFAULT NULL]
  alamat_ip varchar(45) [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  ic_menunggu varchar(20) [GENERATED AS (if(`status` = 'Menunggu',`ic_pekerja`,NULL))]
  email_menunggu varchar(255) [GENERATED AS (if(`status` = 'Menunggu',`email`,NULL))]
  UNIQUE (email_menunggu)
  UNIQUE (ic_menunggu)
  FK (bahagian_id) -> bahagian(bahagian_id) ON DELETE RESTRICT
  FK (disemak_oleh) -> users(user_id) ON DELETE RESTRICT
  FK (user_id) -> users(user_id) ON DELETE RESTRICT

TABLE push_subscriptions
  id bigint(20) unsigned [PK, AUTO_INCREMENT]
  subscribable_type varchar(255) [NOT NULL]
  subscribable_id bigint(20) unsigned [NOT NULL]
  endpoint varchar(1024) [NOT NULL]
  public_key varchar(255) [DEFAULT NULL]
  auth_token varchar(255) [DEFAULT NULL]
  content_encoding varchar(255) [DEFAULT NULL]
  created_at timestamp [DEFAULT NULL]
  updated_at timestamp [DEFAULT NULL]
  UNIQUE (endpoint)

TABLE roles
  roles_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  kod_roles varchar(50) [NOT NULL]
  nama_roles varchar(100) [NOT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  UNIQUE (kod_roles)

TABLE sessions
  id varchar(255) [PK]
  user_id bigint(20) unsigned [DEFAULT NULL]
  ip_address varchar(45) [DEFAULT NULL]
  user_agent text [DEFAULT NULL]
  payload longtext [NOT NULL]
  last_activity int(11) [NOT NULL]
  FK (user_id) -> users(user_id) ON DELETE SET NULL

TABLE templat_borang
  templat_borang_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  jenis_dokumen_id bigint(20) unsigned [NOT NULL]
  versi smallint(5) unsigned [NOT NULL, DEFAULT 1]
  nama_asal varchar(255) [NOT NULL]
  lokasi_fail varchar(500) [NOT NULL]
  saiz_fail bigint(20) unsigned [DEFAULT NULL]
  jenis_mime varchar(100) [DEFAULT NULL]
  hash_sha256 char(64) [DEFAULT NULL]
  bilangan_muka smallint(5) unsigned [DEFAULT NULL]
  ocr_json longtext [DEFAULT NULL]
  kedudukan_medan longtext [DEFAULT NULL]
  status_ocr varchar(50) [NOT NULL, DEFAULT 'Pending']
  aktif tinyint(1) [NOT NULL, DEFAULT 1]
  catatan varchar(255) [DEFAULT NULL]
  created_by bigint(20) unsigned [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  FK (jenis_dokumen_id) -> jenis_dokumen(jenis_dokumen_id) ON DELETE CASCADE

TABLE users
  user_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  ic_pekerja varchar(20) [NOT NULL]
  nama_staff varchar(255) [NOT NULL]
  jawatan varchar(150) [DEFAULT NULL]
  email varchar(255) [NOT NULL]
  no_telefon varchar(20) [DEFAULT NULL]
  password varchar(255) [NOT NULL]
  perlu_tukar_kata_laluan tinyint(1) [NOT NULL, DEFAULT 0]
  kata_laluan_tamat_pada timestamp [DEFAULT NULL]
  dua_faktor_rahsia text [DEFAULT NULL]
  dua_faktor_kod_pemulihan text [DEFAULT NULL]
  dua_faktor_disahkan_pada timestamp [DEFAULT NULL]
  dikunci_hingga timestamp [DEFAULT NULL]
  sebab_kunci varchar(255) [DEFAULT NULL]
  bahagian_id bigint(20) unsigned [DEFAULT NULL]
  created_at timestamp [DEFAULT current_timestamp()]
  updated_at timestamp [DEFAULT current_timestamp()]
  deleted_at timestamp [DEFAULT NULL]
  remember_token varchar(100) [DEFAULT NULL]
  email_aktif varchar(255) [GENERATED AS (if(`deleted_at` is null,`email`,NULL))]
  ic_pekerja_aktif varchar(20) [GENERATED AS (if(`deleted_at` is null,`ic_pekerja`,NULL))]
  UNIQUE (email_aktif)
  UNIQUE (ic_pekerja_aktif)
  FK (bahagian_id) -> bahagian(bahagian_id) ON DELETE RESTRICT

TABLE user_roles
  user_roles_id bigint(20) unsigned [PK, AUTO_INCREMENT]
  user_id bigint(20) unsigned [NOT NULL]
  role_id bigint(20) unsigned [NOT NULL]
  UNIQUE (user_id,role_id)
  FK (role_id) -> roles(roles_id) ON DELETE CASCADE
  FK (user_id) -> users(user_id) ON DELETE CASCADE
```

Jadual infrastruktur Laravel yang TIDAK dimasukkan ke dalam ERD (tiada hubungan domain): `cache`, `cache_locks`, `jobs`, `failed_jobs`, `password_reset_tokens`. Jumlah jadual dalam pangkalan data = 27. Senaraikan 5 jadual ini dalam satu nota kecil sahaja, jangan lukis.

## Semua 35 hubungan kunci asing (WAJIB dilukis semuanya)
Kardinaliti: FK NOT NULL → induk tepat satu (`||`); FK boleh NULL → induk sifar atau satu (`|o`). Sisi anak = sifar atau banyak (`o{`) kecuali dinyatakan.

```
#  | Anak.lajur -> Induk | FK boleh NULL? | Mermaid | Label
1  | borang.dokumen_id -> dokumen | tidak | dokumen ||--o| borang (1:1 logik) | "mempunyai permohonan"
2  | borang.pemohon_id -> users | tidak | users ||--o{ borang | "memohon"
3  | borang.pegawai_penyokong_id -> users | ya | users |o--o{ borang | "menyokong"
4  | borang.pegawai_pelulus_id -> users | ya | users |o--o{ borang | "meluluskan"
5  | borang.pegawai_penolak_id -> users | ya | users |o--o{ borang | "menolak"
6  | borang_sejarah.borang_id -> borang | tidak | borang ||--o{ borang_sejarah | "sejarah tindakan"
7  | borang_sejarah.user_id -> users | ya | users |o--o{ borang_sejarah | "bertindak"
8  | borang_sejarah.bagi_pihak_id -> users | ya | users |o--o{ borang_sejarah | "bagi pihak (delegasi)"
9  | delegasi_kelulusan.pemberi_id -> users | tidak | users ||--o{ delegasi_kelulusan | "mewakilkan"
10 | delegasi_kelulusan.penerima_id -> users | tidak | users ||--o{ delegasi_kelulusan | "menerima delegasi"
11 | delegasi_kelulusan.dicipta_oleh -> users | ya | users |o--o{ delegasi_kelulusan | "mencipta"
12 | dokumen.jenis_dokumen_id -> jenis_dokumen | tidak | jenis_dokumen ||--o{ dokumen | "mengkategorikan"
13 | dokumen.bahagian_id -> bahagian | ya | bahagian |o--o{ dokumen | "memiliki"
14 | dokumen.created_by -> users | ya | users |o--o{ dokumen | "memuat naik"
15 | dokumen.pelupusan_oleh -> users | ya | users |o--o{ dokumen | "melupuskan"
16 | dokumen_data.dokumen_id -> dokumen | tidak | dokumen ||--o{ dokumen_data | "nilai EAV"
17 | dokumen_data.dokumen_field_id -> dokumen_field | tidak | dokumen_field ||--o{ dokumen_data | "nilai bagi medan"
18 | dokumen_field.jenis_dokumen_id -> jenis_dokumen | tidak | jenis_dokumen ||--o{ dokumen_field | "mentakrifkan medan"
19 | dokumen_ocr.dokumen_scan_id -> dokumen_scan | tidak | dokumen_scan ||--o{ dokumen_ocr | "hasil OCR"
20 | dokumen_scan.dokumen_id -> dokumen | tidak | dokumen ||--o{ dokumen_scan | "fail imbasan"
21 | ip_disekat.disekat_oleh -> users | ya | users |o--o{ ip_disekat | "menyekat"
22 | log_audit.user_id -> users | ya | users |o--o{ log_audit | "melakukan"
23 | log_masuk.user_id -> users | ya | users |o--o{ log_masuk | "cubaan log masuk"
24 | pembetulan_ai.dokumen_id -> dokumen | tidak | dokumen ||--o{ pembetulan_ai | "pembetulan AI"
25 | pembetulan_ai.dokumen_field_id -> dokumen_field | tidak | dokumen_field ||--o{ pembetulan_ai | "medan dibetulkan"
26 | pembetulan_ai.jenis_dokumen_id -> jenis_dokumen | tidak | jenis_dokumen ||--o{ pembetulan_ai | "kategori"
27 | pembetulan_ai.user_id -> users | ya | users |o--o{ pembetulan_ai | "membetulkan"
28 | pendaftaran_staf.bahagian_id -> bahagian | tidak | bahagian ||--o{ pendaftaran_staf | "dipohon untuk"
29 | pendaftaran_staf.disemak_oleh -> users | ya | users |o--o{ pendaftaran_staf | "menyemak"
30 | pendaftaran_staf.user_id -> users | ya | users |o--o | pendaftaran_staf
31 | sessions.user_id -> users | ya | users |o--o{ sessions | "sesi"
32 | templat_borang.jenis_dokumen_id -> jenis_dokumen | tidak | jenis_dokumen ||--o{ templat_borang | "templat (berversi)"
33 | users.bahagian_id -> bahagian | ya | bahagian |o--o{ users | "menempatkan"
34 | user_roles.user_id -> users | tidak | users ||--o{ user_roles | "mempunyai peranan"
35 | user_roles.role_id -> roles | tidak | roles ||--o{ user_roles | "diberikan kepada"
```

## Hubungan LOGIK tanpa FK dalam DB (lukis sebagai garis putus-putus / non-identifying, dengan label "(logik)")
- `templat_borang.created_by` → `users.user_id` (sifar atau satu pengguna; tiada FK)
- `notifications.notifiable_id` → `users.user_id` apabila `notifiable_type = 'App\Models\User'` (polimorfik Laravel)
- `push_subscriptions.subscribable_id` → `users.user_id` apabila `subscribable_type = 'App\Models\User'` (polimorfik)
- `delegasi_kelulusan.kod_peranan` → `roles.kod_roles` (rujukan ikut kod, bukan PK)
- `log_audit (nama_jadual, rekod_id)` → rekod dalam MANA-MANA jadual (polimorfik). JANGAN lukis garis ke setiap jadual; tulis sebagai nota pada entiti `log_audit`.

## Output yang dikehendaki
Berikan DUA output berikut, setiap satu dalam blok kod berasingan:

### Output 1: Mermaid `erDiagram` (untuk https://mermaid.live)
- Masukkan SEMUA 22 jadual dengan SEMUA lajur, dalam susunan yang sama seperti skema di atas.
- Format atribut: `jenis nama_lajur PK|FK|UK "komen"`. Jenis TANPA kurungan atau panjang (Mermaid tidak menerimanya): guna `bigint`, `varchar`, `char`, `text`, `mediumtext`, `longtext`, `int`, `smallint`, `tinyint`, `decimal`, `date`, `datetime`, `timestamp`. Letakkan butiran (cth. "varchar(255), NOT NULL", "dijana: email jika belum dipadam", "JSON") dalam komen berpetik.
- Lajur yang menjadi FK ditanda `FK`; PK ditanda `PK`; lajur dalam UNIQUE (termasuk lajur dijana) ditanda `UK`. Lajur yang PK dan FK serentak tiada dalam skema ini.
- Lukis semua 35 hubungan FK dengan simbol crow's foot tepat seperti jadual di atas (`||--o{`, `|o--o{`, `||--o|`, `|o--o|`) dan label berpetik. Hubungan logik guna `..` (putus-putus), cth. `users |o..o{ templat_borang : "mencipta (logik)"`.
- Jangan guna aksara khas dalam nama entiti; nama entiti = nama jadual tepat.

### Output 2: DBML (untuk https://dbdiagram.io)
- Semua 22 jadual, semua lajur dengan jenis penuh (cth. `varchar(255)`, `bigint unsigned`), `[pk, increment]`, `[not null]`, `[unique]`, `[default: ...]`, `[note: '...']`.
- Indeks UNIQUE gabungan dalam blok `indexes { (dokumen_id, dokumen_field_id) [unique] }` dan `(user_id, role_id) [unique]`.
- 35 `Ref` dengan arah dan `[delete: cascade | set null | restrict]` tepat seperti skema.
- Hubungan logik sebagai `Note` (DBML tiada garis putus-putus).
- Kumpulkan jadual dengan `TableGroup`:
  - **Organisasi & Akses:** bahagian, users, roles, user_roles, pendaftaran_staf, delegasi_kelulusan
  - **Templat Dokumen (EAV):** jenis_dokumen, dokumen_field, templat_borang
  - **Dokumen Sebenar:** dokumen, dokumen_data
  - **Imbasan, OCR & AI:** dokumen_scan, dokumen_ocr, pembetulan_ai
  - **Aliran Kelulusan:** borang, borang_sejarah
  - **Audit & Keselamatan:** log_audit, log_masuk, ip_disekat
  - **Sokongan Laravel:** sessions, notifications, push_subscriptions

## Semakan sebelum menjawab (wajib)
Sebelum memberi jawapan, semak sendiri dan nyatakan di akhir jawapan:
1. Bilangan entiti = 22 (Mermaid) dan 22 (DBML).
2. Bilangan hubungan FK = 35 dalam kedua-dua output, + 4 hubungan logik bergaris dalam Mermaid.
3. `user_roles.role_id` merujuk `roles.roles_id` (bukan `roles.role_id`).
4. `borang` mempunyai 4 hubungan berasingan ke `users` dan 1 hubungan 1:1 ke `dokumen`.
5. Tiada lajur yang hilang atau dinamakan semula berbanding skema di atas.
Jika mana-mana semakan gagal, betulkan dahulu sebelum menjawab.
