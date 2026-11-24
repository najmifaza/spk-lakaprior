"""Bangun data/*.csv dan db/seed.sql untuk kasus Kabupaten Sukoharjo.

Semua angka di bawah dikutip apa adanya dari sumber terbuka (lihat SUMBER).
Jalankan ulang setiap kali data mentah berubah: python scripts/build_seed.py
"""

import csv
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SUMBER_LAKA = 'Satlantas Polres Sukoharjo via Satu Data Sukoharjo (data.sukoharjokab.go.id)'
SUMBER_PENDUDUK = 'Dinas Kependudukan dan Pencatatan Sipil (DKB) via Satu Data Sukoharjo'

# kecamatan: (laka 2019, 2020, 2021), (MD 2019, 2020, 2021), (LB 2019, 2020, 2021), (LR 2019, 2020, 2021)
LAKA = {
    'Weru':       ((34, 30, 40), (5, 2, 4), (0, 0, 2), (39, 38, 50)),
    'Bulu':       ((30, 26, 20), (5, 2, 3), (0, 0, 0), (30, 31, 28)),
    'Tawangsari': ((74, 55, 49), (7, 4, 2), (1, 0, 0), (92, 74, 67)),
    'Sukoharjo':  ((203, 142, 179), (23, 15, 22), (0, 1, 1), (239, 162, 201)),
    'Nguter':     ((66, 42, 58), (13, 6, 6), (0, 0, 0), (73, 48, 77)),
    'Bendosari':  ((119, 73, 105), (6, 7, 8), (0, 0, 0), (148, 83, 133)),
    'Polokarto':  ((76, 79, 101), (7, 5, 9), (0, 0, 0), (96, 96, 119)),
    'Mojolaban':  ((104, 98, 103), (4, 6, 4), (1, 0, 0), (112, 121, 113)),
    'Grogol':     ((164, 138, 167), (18, 8, 11), (0, 0, 0), (183, 161, 188)),
    'Baki':       ((81, 73, 72), (4, 6, 6), (0, 0, 0), (95, 81, 83)),
    'Gatak':      ((96, 55, 61), (10, 3, 10), (0, 0, 0), (108, 65, 75)),
    'Kartasura':  ((232, 183, 180), (9, 18, 18), (0, 0, 0), (264, 224, 198)),
}

# DKB 2019 per kecamatan (jiwa) + luas wilayah 2022 (hektar)
PENDUDUK_2019 = {
    'Weru': 58766, 'Bulu': 38557, 'Tawangsari': 57207, 'Sukoharjo': 97305,
    'Nguter': 56439, 'Bendosari': 64282, 'Polokarto': 86300, 'Mojolaban': 92196,
    'Grogol': 121174, 'Baki': 70308, 'Gatak': 53321, 'Kartasura': 110548,
}
LUAS_HEKTAR = {
    'Weru': 4198, 'Bulu': 4386, 'Tawangsari': 3998, 'Sukoharjo': 4458,
    'Nguter': 5488, 'Bendosari': 5299, 'Polokarto': 6218, 'Mojolaban': 3554,
    'Grogol': 3000, 'Baki': 2197, 'Gatak': 1947, 'Kartasura': 1923,
}

BOBOT = {'C1': 0.20, 'C2': 0.20, 'C3': 0.10, 'C4': 0.10, 'C5': 0.15, 'C6': 0.15, 'C7': 0.10}
KRITERIA = [
    ('C1', 'Jumlah kejadian kecelakaan lalu lintas 2019-2021', 'kejadian', 'benefit', BOBOT['C1']),
    ('C2', 'Korban meninggal dunia', 'jiwa', 'benefit', BOBOT['C2']),
    ('C3', 'Korban luka berat', 'jiwa', 'benefit', BOBOT['C3']),
    ('C4', 'Korban luka ringan', 'jiwa', 'benefit', BOBOT['C4']),
    ('C5', 'Jumlah penduduk 2019', 'jiwa', 'benefit', BOBOT['C5']),
    ('C6', 'Kepadatan penduduk', 'jiwa/km2', 'benefit', BOBOT['C6']),
    ('C7', 'Luas wilayah', 'km2', 'cost', BOBOT['C7']),
]


def matriks():
    rows = []
    for nama, (laka, md, lb, lr) in LAKA.items():
        luas_km2 = LUAS_HEKTAR[nama] / 100
        penduduk = PENDUDUK_2019[nama]
        rows.append({
            'kecamatan': nama,
            'laka': laka, 'md': md, 'lb': lb, 'lr': lr,
            'jumlah_laka': sum(laka), 'korban_md': sum(md),
            'korban_lb': sum(lb), 'korban_lr': sum(lr),
            'penduduk': penduduk, 'luas_km2': luas_km2,
            'kepadatan': penduduk / luas_km2,
        })
    return rows


def nilai(rows):
    """[C1..C7] per alternatif."""
    return [[r['jumlah_laka'], r['korban_md'], r['korban_lb'], r['korban_lr'],
             r['penduduk'], r['kepadatan'], r['luas_km2']] for r in rows]


def topsis(x, kriteria):
    bobot = [k[4] / sum(k[4] for k in kriteria) for k in kriteria]
    n_alt, n_krit = len(x), len(kriteria)
    bagi = [math.sqrt(sum(x[i][j] ** 2 for i in range(n_alt))) for j in range(n_krit)]
    r = [[x[i][j] / bagi[j] for j in range(n_krit)] for i in range(n_alt)]
    y = [[r[i][j] * bobot[j] for j in range(n_krit)] for i in range(n_alt)]
    apl = [max(y[i][j] for i in range(n_alt)) if kriteria[j][3] == 'benefit' else min(y[i][j] for i in range(n_alt)) for j in range(n_krit)]
    ami = [min(y[i][j] for i in range(n_alt)) if kriteria[j][3] == 'benefit' else max(y[i][j] for i in range(n_alt)) for j in range(n_krit)]
    dp = [math.sqrt(sum((y[i][j] - apl[j]) ** 2 for j in range(n_krit))) for i in range(n_alt)]
    dm = [math.sqrt(sum((y[i][j] - ami[j]) ** 2 for j in range(n_krit))) for i in range(n_alt)]
    ci = [dm[i] / (dp[i] + dm[i]) for i in range(n_alt)]
    return dp, dm, ci


def saw(x, kriteria):
    bobot = [k[4] / sum(k[4] for k in kriteria) for k in kriteria]
    n_alt, n_krit = len(x), len(kriteria)
    r = []
    for i in range(n_alt):
        baris = []
        for j in range(n_krit):
            kolom = [x[k][j] for k in range(n_alt)]
            baris.append(x[i][j] / max(kolom) if kriteria[j][3] == 'benefit' else min(kolom) / x[i][j])
        r.append(baris)
    return [sum(r[i][j] * bobot[j] for j in range(n_krit)) for i in range(n_alt)]


def peringkat(skor):
    urut = sorted(range(len(skor)), key=lambda i: -skor[i])
    out = [0] * len(skor)
    for pos, i in enumerate(urut, 1):
        out[i] = pos
    return out


def tulis_csv():
    rows = matriks()
    with (ROOT / 'data/alternatif_kecamatan.csv').open('w', newline='', encoding='utf-8') as f:
        w = csv.writer(f)
        w.writerow(['id', 'kecamatan', 'luas_km2', 'penduduk_2019', 'kepadatan_penduduk_jiwa_km2', 'sumber'])
        for i, r in enumerate(rows, 1):
            w.writerow([i, r['kecamatan'], f"{r['luas_km2']:.2f}", r['penduduk'],
                        f"{r['kepadatan']:.2f}", f'{SUMBER_PENDUDUK}; {SUMBER_LAKA}'])

    with (ROOT / 'data/laka_sukoharjo_2019-2021.csv').open('w', newline='', encoding='utf-8') as f:
        w = csv.writer(f)
        w.writerow(['kecamatan', 'laka_2019', 'laka_2020', 'laka_2021', 'jumlah_laka',
                    'korban_md', 'korban_lb', 'korban_lr', 'sumber'])
        for r in rows:
            w.writerow([r['kecamatan'], *r['laka'], r['jumlah_laka'], r['korban_md'],
                        r['korban_lb'], r['korban_lr'], SUMBER_LAKA])

    x = nilai(rows)
    dp, dm, ci = topsis(x, KRITERIA)
    v = saw(x, KRITERIA)
    pt, ps = peringkat(ci), peringkat(v)
    with (ROOT / 'data/hasil_referensi.csv').open('w', newline='', encoding='utf-8') as f:
        w = csv.writer(f)
        w.writerow(['kecamatan', 'D_plus', 'D_minus', 'skor_topsis', 'peringkat_topsis', 'skor_saw', 'peringkat_saw'])
        for i, r in enumerate(rows):
            w.writerow([r['kecamatan'], f'{dp[i]:.6f}', f'{dm[i]:.6f}', f'{ci[i]:.6f}', pt[i], f'{v[i]:.6f}', ps[i]])
    return rows, x, ci, v, pt, ps


def tulis_seed(rows, x):
    q = []
    q.append('-- Seed Kabupaten Sukoharjo. Dihasilkan scripts/build_seed.py, jangan diedit manual.')
    q.append('-- Sumber data: lihat data/*.csv dan README.md (semua data terbuka, bukan simulasi).')
    q.append('SET NAMES utf8mb4;')
    q.append('')
    q.append("INSERT INTO users (username, password_hash, role) VALUES")
    q.append("('admin', SHA2('admin123', 256), 'admin')")
    q.append('ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);')
    q.append('')
    q.append('INSERT INTO kecamatan (id, nama, luas_km2, penduduk_jiwa, kepadatan_penduduk) VALUES')
    baris = [f"({i}, '{r['kecamatan']}', {r['luas_km2']:.2f}, {r['penduduk']}, {r['kepadatan']:.2f})"
             for i, r in enumerate(rows, 1)]
    q.append(',\n'.join(baris) + ';')
    q.append('')
    q.append('INSERT INTO kriteria (id, kode, nama, satuan, tipe, bobot) VALUES')
    q.append(',\n'.join(f"({i}, '{k[0]}', '{k[1]}', '{k[2]}', '{k[3]}', {k[4]})"
                        for i, k in enumerate(KRITERIA, 1)) + ';')
    q.append('')
    q.append('INSERT INTO penilaian (kecamatan_id, kriteria_id, nilai) VALUES')
    nilai_rows = []
    for i, row in enumerate(x, 1):
        for j, val in enumerate(row, 1):
            nilai_rows.append(f'({i}, {j}, {val:.4f})')
    q.append(',\n'.join(nilai_rows) + ';')
    q.append('')
    (ROOT / 'db/seed.sql').write_text('\n'.join(q), encoding='utf-8')


if __name__ == '__main__':
    rows, x, ci, v, pt, ps = tulis_csv()
    tulis_seed(rows, x)
    print(f"{len(rows)} alternatif, {len(KRITERIA)} kriteria, {len(rows) * len(KRITERIA)} nilai")
    for i, r in enumerate(rows):
        print(f"  {pt[i]:>2}. {r['kecamatan']:<11} Ci={ci[i]:.4f} SAW={v[i]:.4f} (SAW #{ps[i]})")
    print('beda peringkat TOPSIS vs SAW:', sum(1 for i in range(len(rows)) if pt[i] != ps[i]))
