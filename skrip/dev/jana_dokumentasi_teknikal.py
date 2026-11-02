"""
Alat pembangun (TIDAK termasuk dalam pakej serahan): jana docs/serahan/DOKUMENTASI_TEKNIKAL.md
daripada README.md pembangunan.

Dikekalkan: pengenalan, spesifikasi, seni bina, skema DB, modul, RBAC, keselamatan, carta alir,
peta fail, had sistem, glosari. Dibuang: nota persekitaran pembangunan, ujian automatik,
panduan pasang pembangunan & hari demo (diganti PANDUAN_PEMASANGAN), sejarah isu, kerja akan
datang, akaun ujian. Kod fasa dalaman dalam kurungan, cth. "(P4)", "(T2)", juga dibuang.

Guna:  python skrip/dev/jana_dokumentasi_teknikal.py
"""
import re
import sys
from pathlib import Path

AKAR = Path(__file__).resolve().parents[2]
SUMBER = AKAR / 'README.md'
SASARAN = AKAR / 'docs' / 'serahan' / 'DOKUMENTASI_TEKNIKAL.md'

# Bahagian ## yang dikekalkan (nombor asal README)
BAHAGIAN = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '12', '14']
# Sub-bahagian ### yang dibuang (tajuk bermula dengan teks ini)
SUB_DIBUANG = ['2.5 ', 'Ujian (`tests/`)', 'Kerja Akan Datang']

# Penggantian teks khusus (lama -> baharu); setiap satu mesti dijumpai
GANTI = [
    ('| **Pelayan Web (Local Dev)** | Laravel Herd (nginx + PHP-FPM) atau `php artisan serve` | Persekitaran pembangunan di Windows |',
     '| **Pelayan Web** | nginx / Apache / IIS + PHP-FPM | Windows atau Linux (lihat Panduan Pemasangan) |'),
    ('Ukuran dengan 7 dokumen ujian (data rekaan, folder `../Ujian_Borang_Diisi/`), OCR + AI:',
     'Ukuran dengan 7 dokumen ujian (data rekaan), OCR + AI:'),
    ('(54 medan; label disahkan dengan membaca imej, `../Ujian_Borang_Diisi/*.label.json`)',
     '(54 medan; label disahkan dengan membaca imej)'),
    ('(P15; lalai `../Ujian_Borang_Diisi`)', '(penilaian ketepatan; perlukan folder dokumen berlabel)'),
    ('# Sistem Arkib Digital (DMS) PKINK', '# Sistem Arkib Digital (DMS) PKINK: Dokumentasi Teknikal'),
    ('Struktur standard Laravel; SQL dalam langkah 13 bahagian 10.', 'Struktur standard Laravel; termasuk dalam `database/dms_pkink.sql`.'),
    ('(T2, lihat 5.8 dan langkah 13 bahagian 10)', '(lihat 5.8 dan Panduan Pemasangan)'),
    ('## 12. Had Sistem & Kerja Akan Datang', '## 12. Had Sistem'),
    ('Skema `dms_pkink_db` diurus **secara manual dalam HeidiSQL** (dump SQL + skrip SQL bernombor). Folder `database/migrations/` hanya mengandungi fail asal Laravel, dan tiada jadual `migrations`. Akibatnya:',
     'Skema dipasang daripada **`database/dms_pkink.sql`** dan diubah hanya melalui skrip SQL. Sistem **tidak** menggunakan migration Laravel (tiada folder `database/migrations` dan tiada jadual `migrations`). Akibatnya:'),
    ('- Perubahan skema diberi sebagai skrip SQL untuk dijalankan dalam HeidiSQL',
     '- Perubahan skema dijalankan sebagai skrip SQL (cth. melalui HeidiSQL, MySQL Workbench atau klien `mysql`) selepas sandaran'),
    ('| Selari dengan MySQL/HeidiSQL |', '| Selari dengan zon masa pelayan MySQL |'),
    (', serta skrip `docs/erd/skrip/` untuk menjana semula selepas skema berubah (lihat `docs/erd/README.md`).',
     ' (lihat `docs/erd/README.md`).'),
    ('**Notifikasi emel** (`NOTIFIKASI_EMEL`, **mati secara lalai**): bila dihidupkan,',
     '**Notifikasi emel** (`NOTIFIKASI_EMEL`): bila dihidupkan,'),
    ('*Dokumen ini dikemas kini sepanjang pembangunan projek (terakhir: 27 Oktober 2026). Untuk persoalan teknikal semasa pembentangan, rujuk nombor bahagian berkaitan di atas.*',
     '*Dokumentasi teknikal untuk pentadbir sistem dan pembangun. Panduan pemasangan: `docs/PANDUAN_PEMASANGAN.pdf`; manual pengguna: `docs/MANUAL_PENGGUNA_DMS_PKINK.pdf`.*'),
    ('| **Pelayan Pangkalan Data (Local Dev)** | XAMPP, **MySQL sahaja** | Apache/PHP XAMPP tidak digunakan |\n', ''),
    ('| **Pengurus Pangkalan Data** | HeidiSQL | Skema diurus manual (tiada migration Laravel, lihat 4.0) |',
     '| **Pengurusan Skema** | Skrip SQL | Tiada migration Laravel (lihat 4.0) |'),
    ('UB-Mannheim build (Windows), dengan pek bahasa Malay (`msa`) + English (`eng`)',
     'Windows: UB-Mannheim build; Linux: `apt install tesseract-ocr tesseract-ocr-msa`. Pek bahasa Malay (`msa`) + English (`eng`)'),
    ('| poppler-windows releases (GitHub) |', '| Windows: poppler-windows (GitHub); Linux: `apt install poppler-utils` |'),
    ('| RAM | 8GB | 16GB+ (pembangunan: 20GB) |', '| RAM | 8GB | 16GB+ |'),
    ('| Storan | 10GB ruang kosong | 20GB+ (model AI ~7GB, pangkalan data, fail imbasan) |',
     '| Storan | 25GB (UAT) | ~100GB produksi (model AI ~7GB; data & sandaran bertambah ikut bilangan dokumen, lihat Panduan Pemasangan) |'),
    ('| GPU | Tidak wajib | NVIDIA (CUDA) 6GB+ (pembangunan: RTX 3050 6GB). Model SEA-LION dimuat penuh dalam GPU |',
     '| GPU | Tidak wajib (AI jauh lebih perlahan) | NVIDIA (CUDA) 6GB+ VRAM. Model SEA-LION dimuat penuh dalam GPU |'),
    ('| Sistem Operasi | Windows 10/11 (persekitaran pembangunan semasa) | |',
     '| Sistem Operasi | Windows Server / Windows 10+ atau Linux (Ubuntu 22.04+) | |'),
    ('- Notifikasi emel sudah dibina tetapi **mati secara lalai** (`NOTIFIKASI_EMEL=false`); hidupkan semasa serahan',
     '- Notifikasi emel aliran kelulusan dikawal oleh `NOTIFIKASI_EMEL` (memerlukan SMTP)'),
    ('(Fasa D: mod demo & mod percubaan, lihat 10.3)', '(mod demo & mod percubaan untuk taklimat/latihan; DB berasingan)'),
]

# Corak yang tidak boleh wujud dalam hasil
DILARANG = [r'Claude', r'Gemini', r'PROGRESS\.md', r'Ujian_Borang_Diisi', r'123456789', r'9901010101\d',
            r'PR #\d', r'\bHerd\b', r'OneDrive']


def main() -> int:
    teks = SUMBER.read_text(encoding='utf-8')
    for lama, baharu in GANTI:
        if lama not in teks:
            print(f'Penggantian tidak dijumpai: {lama[:70]}', file=sys.stderr)
            return 1
        teks = teks.replace(lama, baharu)

    # Kepala dokumen: tajuk + baris "Nama Penuh" sahaja
    kepala, _, badan = teks.partition('\n## ')
    baris_kepala = [b for b in kepala.splitlines() if b.startswith('# ') or b.startswith('**Nama Penuh')]
    badan = '## ' + badan

    # Pecah ikut ## dan kekalkan bahagian terpilih
    bahagian = re.split(r'(?m)^(?=## )', badan)
    simpan = []
    for b in bahagian:
        m = re.match(r'## (\d+)\. ', b)
        if not m or m.group(1) not in BAHAGIAN:
            continue
        # Buang sub-bahagian ### yang tidak berkaitan
        sub = re.split(r'(?m)^(?=### )', b)
        sub = [s for s in sub if not any(s.startswith('### ' + t) for t in SUB_DIBUANG)]
        simpan.append(''.join(sub).rstrip('\n -') + '\n')

    # Nombor semula ## dan ### (1., 1.1 ...)
    hasil = []
    toc = []
    for i, b in enumerate(simpan, 1):
        asal = re.match(r'## (\d+)\. ', b).group(1)
        b = re.sub(r'^## \d+\. ', f'## {i}. ', b, count=1)
        b = re.sub(rf'(?m)^### {asal}\.(\d+) ', lambda m: f'### {i}.{m.group(1)} ', b)
        tajuk = b.splitlines()[0][3:]
        sauh = re.sub(r'[^\w\- ]', '', tajuk.lower()).strip().replace(' ', '-')
        toc.append(f'{i}. [{tajuk.split(". ", 1)[1]}](#{sauh})')
        hasil.append(b)
    dokumen = '\n'.join(baris_kepala) + '\n\n---\n\n## Senarai Kandungan\n' + '\n'.join(toc) + '\n\n---\n\n' + '\n---\n\n'.join(hasil)

    # Kod fasa dalaman dalam kurungan: "(P4)", "(T2)", "(TB6)", "(S1–S5)", "(P4, P5)"
    kod = r'(?:P|T|TB|S|K|D|R|H)\d{1,2}'
    dokumen = re.sub(rf' \((?:Fasa )?{kod}(?:\s*[,–/-]\s*{kod})*\)', '', dokumen)

    # Singkatan tidak rasmi ditulis penuh (dokumen untuk staf PKINK)
    dokumen = re.sub(r'\bcth\.\s*', 'contohnya ', dokumen)

    for corak in DILARANG:
        jumpa = re.search(corak, dokumen)
        if jumpa:
            mula = max(0, jumpa.start() - 60)
            print(f'Corak dilarang "{corak}" masih wujud: ...{dokumen[mula:jumpa.end() + 60]}...', file=sys.stderr)
            return 1

    SASARAN.parent.mkdir(parents=True, exist_ok=True)
    SASARAN.write_text(dokumen, encoding='utf-8', newline='\n')
    print(f'Ditulis: {SASARAN.relative_to(AKAR)} ({len(dokumen) // 1024} KB, {len(hasil)} bahagian)')
    return 0


if __name__ == '__main__':
    sys.exit(main())
