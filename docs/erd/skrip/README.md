# Jana semula ERD (alat pembangun; tidak dimasukkan ke dalam pakej pemasangan)

Skrip hanya MEMBACA skema (INFORMATION_SCHEMA). Jalankan dari akar projek selepas skema berubah:

```
php docs/erd/skrip/jana_dbml.php . docs/erd/ERD_DMS_PKINK.dbml
php docs/erd/skrip/jana_drawio.php . docs/erd/ERD_DMS_PKINK.drawio
php docs/erd/skrip/jana_sql_drawio.php . docs/erd/ERD_DMS_PKINK_drawio.sql
```

- Lalai pangkalan data: `dms_pkink_db`. Untuk skema pakej pemasangan (contohnya selepas lajur
  lama digugurkan), tetapkan dahulu `ERD_DB=dms_pkink_pemasangan` (PowerShell: `$env:ERD_DB='dms_pkink_pemasangan'`).
- Laluan output diberi sebagai argumen, bukan `>`: dalam PowerShell 5.1, `>` menulis UTF-16.
- Jika jadual domain baharu ditambah, kemas kini senarai kumpulan/susunan dalam setiap skrip
  (`jana_sql_drawio.php` berhenti dengan ralat jika senarai tidak sepadan), dan bilangan jadual/lajur
  dalam `docs/erd/README.md`.
