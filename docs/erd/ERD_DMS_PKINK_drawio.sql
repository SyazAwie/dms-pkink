CREATE TABLE bahagian (
  bahagian_id BIGINT NOT NULL,
  kod_bahagian VARCHAR(50) NOT NULL,
  nama_bahagian VARCHAR(255) NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  kod_bahagian_aktif VARCHAR(50),
  PRIMARY KEY (bahagian_id)
);

CREATE TABLE users (
  user_id BIGINT NOT NULL,
  ic_pekerja VARCHAR(20) NOT NULL,
  nama_staff VARCHAR(255) NOT NULL,
  jawatan VARCHAR(150),
  email VARCHAR(255) NOT NULL,
  no_telefon VARCHAR(20),
  password VARCHAR(255) NOT NULL,
  perlu_tukar_kata_laluan TINYINT NOT NULL,
  kata_laluan_tamat_pada TIMESTAMP,
  dua_faktor_rahsia TEXT,
  dua_faktor_kod_pemulihan TEXT,
  dua_faktor_disahkan_pada TIMESTAMP,
  dikunci_hingga TIMESTAMP,
  sebab_kunci VARCHAR(255),
  bahagian_id BIGINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  remember_token VARCHAR(100),
  email_aktif VARCHAR(255),
  ic_pekerja_aktif VARCHAR(20),
  PRIMARY KEY (user_id),
  FOREIGN KEY (bahagian_id) REFERENCES bahagian(bahagian_id)
);

CREATE TABLE roles (
  roles_id BIGINT NOT NULL,
  kod_roles VARCHAR(50) NOT NULL,
  nama_roles VARCHAR(100) NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (roles_id)
);

CREATE TABLE user_roles (
  user_roles_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  role_id BIGINT NOT NULL,
  PRIMARY KEY (user_roles_id),
  FOREIGN KEY (role_id) REFERENCES roles(roles_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE pendaftaran_staf (
  pendaftaran_staf_id BIGINT NOT NULL,
  ic_pekerja VARCHAR(20) NOT NULL,
  nama_staff VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  bahagian_id BIGINT NOT NULL,
  status VARCHAR(20) NOT NULL,
  sebab_tolak TEXT,
  disemak_oleh BIGINT,
  disemak_pada TIMESTAMP,
  user_id BIGINT,
  alamat_ip VARCHAR(45),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  ic_menunggu VARCHAR(20),
  email_menunggu VARCHAR(255),
  PRIMARY KEY (pendaftaran_staf_id),
  FOREIGN KEY (bahagian_id) REFERENCES bahagian(bahagian_id),
  FOREIGN KEY (disemak_oleh) REFERENCES users(user_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE delegasi_kelulusan (
  delegasi_id BIGINT NOT NULL,
  pemberi_id BIGINT NOT NULL,
  penerima_id BIGINT NOT NULL,
  kod_peranan VARCHAR(20) NOT NULL,
  tarikh_mula DATE NOT NULL,
  tarikh_tamat DATE NOT NULL,
  sebab VARCHAR(255),
  dicipta_oleh BIGINT,
  dibatalkan_pada TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (delegasi_id),
  FOREIGN KEY (dicipta_oleh) REFERENCES users(user_id),
  FOREIGN KEY (pemberi_id) REFERENCES users(user_id),
  FOREIGN KEY (penerima_id) REFERENCES users(user_id)
);

CREATE TABLE jenis_dokumen (
  jenis_dokumen_id BIGINT NOT NULL,
  kod_dokumen VARCHAR(50) NOT NULL,
  nama_dokumen VARCHAR(255) NOT NULL,
  kategori VARCHAR(100),
  tempoh_simpanan_tahun SMALLINT,
  tindakan_tamat_tempoh VARCHAR(30) NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  kod_dokumen_aktif VARCHAR(50),
  PRIMARY KEY (jenis_dokumen_id)
);

CREATE TABLE dokumen_field (
  dokumen_field_id BIGINT NOT NULL,
  jenis_dokumen_id BIGINT NOT NULL,
  nama_field VARCHAR(100) NOT NULL,
  kod_field VARCHAR(100) NOT NULL,
  jenis_data VARCHAR(50) NOT NULL,
  pilihan LONGTEXT,
  tetapan LONGTEXT,
  is_required TINYINT NOT NULL,
  susunan INT NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  kod_field_aktif VARCHAR(160),
  PRIMARY KEY (dokumen_field_id),
  FOREIGN KEY (jenis_dokumen_id) REFERENCES jenis_dokumen(jenis_dokumen_id)
);

CREATE TABLE templat_borang (
  templat_borang_id BIGINT NOT NULL,
  jenis_dokumen_id BIGINT NOT NULL,
  versi SMALLINT NOT NULL,
  nama_asal VARCHAR(255) NOT NULL,
  lokasi_fail VARCHAR(500) NOT NULL,
  saiz_fail BIGINT,
  jenis_mime VARCHAR(100),
  hash_sha256 CHAR(64),
  bilangan_muka SMALLINT,
  ocr_json LONGTEXT,
  kedudukan_medan LONGTEXT,
  status_ocr VARCHAR(50) NOT NULL,
  aktif TINYINT NOT NULL,
  catatan VARCHAR(255),
  created_by BIGINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  PRIMARY KEY (templat_borang_id),
  FOREIGN KEY (jenis_dokumen_id) REFERENCES jenis_dokumen(jenis_dokumen_id)
);

CREATE TABLE dokumen (
  dokumen_id BIGINT NOT NULL,
  jenis_dokumen_id BIGINT NOT NULL,
  bahagian_id BIGINT,
  no_rujukan VARCHAR(100) NOT NULL,
  tarikh_dokumen DATE NOT NULL,
  perkara VARCHAR(255),
  tarikh_tamat_simpanan DATE,
  status_pelupusan VARCHAR(30),
  tarikh_pelupusan DATETIME,
  pelupusan_oleh BIGINT,
  catatan_pelupusan VARCHAR(255),
  created_by BIGINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  PRIMARY KEY (dokumen_id),
  FOREIGN KEY (bahagian_id) REFERENCES bahagian(bahagian_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id),
  FOREIGN KEY (jenis_dokumen_id) REFERENCES jenis_dokumen(jenis_dokumen_id),
  FOREIGN KEY (pelupusan_oleh) REFERENCES users(user_id)
);

CREATE TABLE dokumen_data (
  dokumen_data_id BIGINT NOT NULL,
  dokumen_id BIGINT NOT NULL,
  dokumen_field_id BIGINT NOT NULL,
  nilai_data LONGTEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (dokumen_data_id),
  FOREIGN KEY (dokumen_field_id) REFERENCES dokumen_field(dokumen_field_id),
  FOREIGN KEY (dokumen_id) REFERENCES dokumen(dokumen_id)
);

CREATE TABLE dokumen_scan (
  dokumen_scan_id BIGINT NOT NULL,
  dokumen_id BIGINT NOT NULL,
  nama_fail VARCHAR(255) NOT NULL,
  lokasi_fail VARCHAR(500) NOT NULL,
  saiz_fail BIGINT,
  jenis_mime VARCHAR(100),
  hash_sha256 CHAR(64),
  tarikh_scan DATETIME NOT NULL,
  status_ocr VARCHAR(50) NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (dokumen_scan_id),
  FOREIGN KEY (dokumen_id) REFERENCES dokumen(dokumen_id)
);

CREATE TABLE dokumen_ocr (
  dokumen_ocr_id BIGINT NOT NULL,
  dokumen_scan_id BIGINT NOT NULL,
  teks_dibaca LONGTEXT,
  skor_padanan DECIMAL,
  status_semakan VARCHAR(50) NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (dokumen_ocr_id),
  FOREIGN KEY (dokumen_scan_id) REFERENCES dokumen_scan(dokumen_scan_id)
);

CREATE TABLE pembetulan_ai (
  pembetulan_ai_id BIGINT NOT NULL,
  dokumen_id BIGINT NOT NULL,
  jenis_dokumen_id BIGINT NOT NULL,
  dokumen_field_id BIGINT NOT NULL,
  nilai_ai TEXT,
  nilai_disimpan TEXT,
  sama TINYINT NOT NULL,
  model_ai VARCHAR(100),
  user_id BIGINT,
  created_at TIMESTAMP,
  PRIMARY KEY (pembetulan_ai_id),
  FOREIGN KEY (dokumen_field_id) REFERENCES dokumen_field(dokumen_field_id),
  FOREIGN KEY (dokumen_id) REFERENCES dokumen(dokumen_id),
  FOREIGN KEY (jenis_dokumen_id) REFERENCES jenis_dokumen(jenis_dokumen_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE borang (
  borang_id BIGINT NOT NULL,
  dokumen_id BIGINT NOT NULL,
  pemohon_id BIGINT NOT NULL,
  pegawai_penyokong_id BIGINT,
  pegawai_pelulus_id BIGINT,
  pegawai_penolak_id BIGINT,
  status_permohonan VARCHAR(50) NOT NULL,
  tarikh_permohonan DATETIME,
  tarikh_sokongan DATETIME,
  tarikh_kelulusan DATETIME,
  tarikh_tolak DATETIME,
  ulasan_penyokong TEXT,
  ulasan_pelulus TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (borang_id),
  FOREIGN KEY (dokumen_id) REFERENCES dokumen(dokumen_id),
  FOREIGN KEY (pegawai_pelulus_id) REFERENCES users(user_id),
  FOREIGN KEY (pegawai_penolak_id) REFERENCES users(user_id),
  FOREIGN KEY (pegawai_penyokong_id) REFERENCES users(user_id),
  FOREIGN KEY (pemohon_id) REFERENCES users(user_id)
);

CREATE TABLE borang_sejarah (
  borang_sejarah_id BIGINT NOT NULL,
  borang_id BIGINT NOT NULL,
  tindakan VARCHAR(30) NOT NULL,
  status_selepas VARCHAR(50) NOT NULL,
  user_id BIGINT,
  bagi_pihak_id BIGINT,
  ulasan TEXT,
  created_at TIMESTAMP,
  PRIMARY KEY (borang_sejarah_id),
  FOREIGN KEY (bagi_pihak_id) REFERENCES users(user_id),
  FOREIGN KEY (borang_id) REFERENCES borang(borang_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE log_audit (
  log_audit_id BIGINT NOT NULL,
  user_id BIGINT,
  tindakan VARCHAR(100) NOT NULL,
  nama_jadual VARCHAR(100) NOT NULL,
  rekod_id BIGINT,
  data_lama LONGTEXT,
  data_baharu LONGTEXT,
  alamat_ip VARCHAR(45),
  peranti_pengguna TEXT,
  created_at TIMESTAMP,
  PRIMARY KEY (log_audit_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE log_masuk (
  log_masuk_id BIGINT NOT NULL,
  user_id BIGINT,
  berjaya TINYINT NOT NULL,
  sebab VARCHAR(50),
  alamat_ip VARCHAR(45),
  peranti_pengguna TEXT,
  created_at TIMESTAMP,
  PRIMARY KEY (log_masuk_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE ip_disekat (
  ip_disekat_id BIGINT NOT NULL,
  alamat_ip VARCHAR(45) NOT NULL,
  sebab VARCHAR(255),
  automatik TINYINT NOT NULL,
  disekat_oleh BIGINT,
  tamat_pada TIMESTAMP,
  created_at TIMESTAMP,
  PRIMARY KEY (ip_disekat_id),
  FOREIGN KEY (disekat_oleh) REFERENCES users(user_id)
);

CREATE TABLE sessions (
  id VARCHAR(255) NOT NULL,
  user_id BIGINT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE notifications (
  id CHAR(36) NOT NULL,
  type VARCHAR(255) NOT NULL,
  notifiable_type VARCHAR(255) NOT NULL,
  notifiable_id BIGINT NOT NULL,
  data TEXT NOT NULL,
  read_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE push_subscriptions (
  id BIGINT NOT NULL,
  subscribable_type VARCHAR(255) NOT NULL,
  subscribable_id BIGINT NOT NULL,
  endpoint VARCHAR(1024) NOT NULL,
  public_key VARCHAR(255),
  auth_token VARCHAR(255),
  content_encoding VARCHAR(255),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  PRIMARY KEY (id)
);
