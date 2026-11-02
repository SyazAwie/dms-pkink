"""
Alat pembangun (TIDAK termasuk dalam pakej): bina pakej pemasangan Sistem Arkib Digital (DMS) PKINK.

  python skrip/dev/bina_pakej.py             pakej penuh: folder + zip + SHA256SUMS.txt
  python skrip/dev/bina_pakej.py --interim   folder sahaja (tanpa PDF & zip), untuk tangkapan skrin / ujian pasang

Sumber: commit HEAD (git archive), jadi hanya fail yang sudah di-commit dimasukkan.
Output: ../Pakej_DMS_PKINK/DMS_PKINK/ dan ../Pakej_DMS_PKINK/DMS_PKINK_v<versi>_<tarikh>.zip

Perlu wujud terlebih dahulu (di luar repo):
  ../Pakej_DMS_PKINK/database/dms_pkink.sql          (skrip/dev/jana_sql_db_pemasangan.php + mysqldump)
  ../Pakej_DMS_PKINK/pdf/*.pdf                         (manual & panduan; tidak perlu bagi --interim)
"""
import datetime
import hashlib
import io
import os
import re
import shutil
import subprocess
import sys
import tarfile
import zipfile
from pathlib import Path

VERSI = '1.0'
AKAR = Path(__file__).resolve().parents[2]
LUAR = AKAR.parent / 'Pakej_DMS_PKINK'
SASARAN = LUAR / 'DMS_PKINK'

# Fail/folder daripada git yang BUKAN sebahagian sistem yang dipasang
DIBUANG = [
    '.editorconfig', '.npmrc', '.gitattributes', '.gitignore', 'AGENTS.md', 'CLAUDE.md', 'README.md', 'phpunit.xml',
    'tests', 'skrip/dev', 'database/factories', 'database/migrations', 'database/seeders', 'database/.gitignore',
    'docs/pakej', 'docs/erd/skrip', 'docs/MANUAL_TEMPLAT_BORANG.md', 'Templat/.gitkeep',
    # docs/PELAN_TINDAK_BALAS_INSIDEN.md DIKEKALKAN: dibaca oleh Pusat Keselamatan (keselamatan.pelan)
]
# Nama fail/folder dalam vendor/ yang dibuang (panduan pembantu AI & CI pembekal pustaka)
NAMA_PEMBEKAL_DIBUANG = {'CLAUDE.md', 'AGENTS.md', 'GEMINI.md', '.claude', '.cursor', '.cursorrules', '.github', '.windsurfrules', 'copilot-instructions.md'}
# PDF yang mesti ada dalam pakej penuh (../Pakej_DMS_PKINK/pdf/)
PDF = ['MANUAL_PENGGUNA_DMS_PKINK.pdf', 'PANDUAN_PEMASANGAN.pdf', 'PANDUAN_UJIAN_KOMPUTER_RIBA.pdf', 'MANUAL_TEMPLAT_BORANG.pdf', 'PELAN_TINDAK_BALAS_INSIDEN.pdf']
# Folder storage yang mesti wujud (kosong)
FOLDER_KOSONG = ['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions',
                 'storage/framework/views', 'storage/framework/testing', 'storage/logs', 'bootstrap/cache']

# Corak yang tidak boleh wujud dalam fail teks pakej (selain vendor/)
DILARANG = {
    r'(?i)\bclaude\b': [], r'(?i)anthropic': [], r'(?i)gemini': [], r'PROGRESS\.md': [],
    r'9901010101\d': [], r'(?i)syazw': [], r'C:[/\\]+Users[/\\]+syazw': [],
    r'(?i)serahan(?! tangan)': [],
    # Senarai kata laluan lemah yang SENGAJA ditolak oleh semakan keselamatan
    r'123456789': ['app/Support/Keselamatan.php'],
}


def jalan(arahan, **kw):
    print('  $', ' '.join(str(a) for a in arahan))
    return subprocess.run(arahan, check=True, **kw)


def main() -> int:
    interim = '--interim' in sys.argv
    print(f'Bina pakej {"(interim) " if interim else ""}-> {SASARAN}')

    sql = LUAR / 'database' / 'dms_pkink.sql'
    if not sql.is_file():
        print(f'TIADA: {sql}', file=sys.stderr)
        return 1
    pdf_hilang = [p for p in PDF if not (LUAR / 'pdf' / p).is_file()]
    if pdf_hilang and not interim:
        print(f'PDF belum ada: {pdf_hilang} (guna --interim untuk binaan sementara)', file=sys.stderr)
        return 1

    # 1. Fail sistem daripada HEAD
    if SASARAN.exists():
        shutil.rmtree(SASARAN)
    SASARAN.mkdir(parents=True)
    arkib = subprocess.run(['git', 'archive', '--format=tar', 'HEAD'], cwd=AKAR, check=True, capture_output=True).stdout
    with tarfile.open(fileobj=io.BytesIO(arkib)) as tar:
        tar.extractall(SASARAN, filter='data')
    commit = subprocess.run(['git', 'rev-parse', '--short', 'HEAD'], cwd=AKAR, check=True, capture_output=True, text=True).stdout.strip()

    for p in DIBUANG:
        sasaran = SASARAN / p
        if sasaran.is_dir():
            shutil.rmtree(sasaran)
        elif sasaran.exists():
            sasaran.unlink()
    for gi in SASARAN.rglob('.gitignore'):
        gi.unlink()
    for f in FOLDER_KOSONG:
        (SASARAN / f).mkdir(parents=True, exist_ok=True)

    # 2. README, dokumentasi, templat, pangkalan data
    shutil.copy2(AKAR / 'docs' / 'pakej' / 'README_PAKEJ.md', SASARAN / 'README.md')
    shutil.copy2(AKAR / 'docs' / 'pakej' / 'DOKUMENTASI_TEKNIKAL.md', SASARAN / 'docs' / 'DOKUMENTASI_TEKNIKAL.md')
    for p in PDF:
        if (LUAR / 'pdf' / p).is_file():
            shutil.copy2(LUAR / 'pdf' / p, SASARAN / 'docs' / p)
    templat = sorted((AKAR / 'Templat').glob('*.pdf'))
    if len(templat) != 7:
        print(f'Templat/: dijangka 7 PDF, dijumpai {len(templat)}', file=sys.stderr)
        return 1
    for t in templat:
        shutil.copy2(t, SASARAN / 'Templat' / t.name)
    (SASARAN / 'database').mkdir(exist_ok=True)
    shutil.copy2(sql, SASARAN / 'database' / 'dms_pkink.sql')

    # 3. Pustaka PHP produksi (tanpa pakej pembangunan)
    composer = shutil.which('composer') or shutil.which('composer.bat')
    jalan([composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--quiet'], cwd=SASARAN)
    for f in (SASARAN / 'bootstrap' / 'cache').glob('*.php'):
        f.unlink()  # dijana semula semasa larian pertama
    # Fail panduan pembantu AI / CI daripada pembekal pustaka (tiada kod; tidak diperlukan semasa larian)
    for p in sorted(SASARAN.joinpath('vendor').rglob('*'), reverse=True):
        if p.name in NAMA_PEMBEKAL_DIBUANG:
            shutil.rmtree(p) if p.is_dir() else p.unlink()

    # 4. Semakan
    ralat = semak(interim)
    if ralat:
        print('\nSEMAKAN GAGAL:', file=sys.stderr)
        for r in ralat:
            print('  - ' + r, file=sys.stderr)
        return 1
    print('Semakan: lulus')

    if interim:
        print(f'Siap (interim, commit {commit}): {SASARAN}')
        return 0

    # 5. Zip + SHA-256
    tarikh = datetime.date.today().strftime('%Y%m%d')
    nama_zip = LUAR / f'DMS_PKINK_v{VERSI}_{tarikh}.zip'
    if nama_zip.exists():
        nama_zip.unlink()
    with zipfile.ZipFile(nama_zip, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for laluan in sorted(SASARAN.rglob('*')):
            rel = Path('DMS_PKINK') / laluan.relative_to(SASARAN)
            if laluan.is_dir():
                if not any(laluan.iterdir()):
                    z.writestr(rel.as_posix() + '/', '')
            else:
                z.write(laluan, rel.as_posix())
    h = hashlib.sha256(nama_zip.read_bytes()).hexdigest()
    # Hujung baris LF: `sha256sum -c` di Linux gagal membaca nama fail jika CRLF
    (LUAR / 'SHA256SUMS.txt').write_bytes(f'{h}  {nama_zip.name}\n'.encode('utf-8'))
    print(f'Siap (commit {commit}): {nama_zip.name} ({nama_zip.stat().st_size / 1048576:.1f} MB)\nSHA-256: {h}')
    return 0


def semak(interim: bool) -> list[str]:
    ralat = []
    for terlarang in ['.env', '.git', '.claude', '.omc', 'PROGRESS.md', 'CLAUDE.md', 'AGENTS.md', 'tests', 'skrip/dev', 'database/migrations', 'docs/pakej']:
        if (SASARAN / terlarang).exists():
            ralat.append(f'tidak sepatutnya wujud: {terlarang}')
    for p in SASARAN.rglob('*'):
        rel = p.relative_to(SASARAN).as_posix()
        if p.is_file() and rel.startswith('storage/'):
            ralat.append(f'fail dalam storage: {rel}')
        if p.is_file() and p.suffix in ('.log', '.sqlite', '.key') :
            ralat.append(f'fail tidak dibenarkan: {rel}')
        if p.is_dir() and p.name == '.git':
            ralat.append(f'folder .git: {rel}')
        if p.name in NAMA_PEMBEKAL_DIBUANG:
            ralat.append(f'fail pembantu AI/CI: {rel}')
    teks = ('.php', '.md', '.txt', '.json', '.js', '.css', '.cmd', '.ps1', '.service', '.conf', '.example', '.sql', '.dbml', '.drawio', '.xml', '.webmanifest', '.htaccess')
    for p in SASARAN.rglob('*'):
        rel = p.relative_to(SASARAN).as_posix()
        if not p.is_file() or rel.startswith('vendor/') or not (p.suffix in teks or p.name in ('artisan', '.env.example', '.htaccess')):
            continue
        isi = p.read_text(encoding='utf-8', errors='ignore')
        for corak, dibenarkan in DILARANG.items():
            if rel in dibenarkan:
                continue
            m = re.search(corak, isi)
            if m:
                ralat.append(f'{rel}: corak "{corak}" -> ...{isi[max(0, m.start() - 40):m.end() + 40]!r}...')
    if not interim:
        for p in PDF:
            if not (SASARAN / 'docs' / p).is_file():
                ralat.append(f'PDF tiada: docs/{p}')
    return ralat


if __name__ == '__main__':
    sys.exit(main())
