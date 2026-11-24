<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/mcdm.php';

/** Ambil alternatif + kriteria + nilai dari database. */
function load_data(): array
{
    $alternatives = db()->query('SELECT * FROM kecamatan ORDER BY id')->fetchAll();
    $criteria = db()->query('SELECT * FROM kriteria ORDER BY id')->fetchAll();
    $values = [];
    foreach (db()->query('SELECT * FROM penilaian')->fetchAll() as $row) {
        $values[$row['kecamatan_id'] . '|' . $row['kriteria_id']] = $row['nilai'];
    }
    return [$alternatives, $criteria, $values];
}

/** Hitung TOPSIS + SAW sekaligus. */
function hitung(array $alternatives, array $criteria, array $values): array
{
    $matrix = build_matrix($alternatives, $criteria, $values);
    return ['matrix' => $matrix, 'topsis' => topsis($matrix, $criteria), 'saw' => saw($matrix, $criteria)];
}

/** Simpan hasil hitung terakhir ke tabel hasil (dipakai halaman perhitungan). */
function simpan_hasil(array $alternatives, array $hasil): void
{
    $st = db()->prepare(
        'INSERT INTO hasil (kecamatan_id, skor_topsis, peringkat_topsis, skor_saw, peringkat_saw)
         VALUES (:id, :ct, :pt, :cs, :ps)
         ON DUPLICATE KEY UPDATE skor_topsis=:ct2, peringkat_topsis=:pt2,
                                 skor_saw=:cs2, peringkat_saw=:ps2, dihitung_pada=NOW()'
    );
    foreach ($alternatives as $i => $alt) {
        $st->execute([
            'id' => $alt['id'],
            'ct' => $hasil['topsis']['ci'][$i], 'pt' => $hasil['topsis']['peringkat'][$i],
            'cs' => $hasil['saw']['v'][$i], 'ps' => $hasil['saw']['peringkat'][$i],
            'ct2' => $hasil['topsis']['ci'][$i], 'pt2' => $hasil['topsis']['peringkat'][$i],
            'cs2' => $hasil['saw']['v'][$i], 'ps2' => $hasil['saw']['peringkat'][$i],
        ]);
    }
}

/** Tabel penilaian lengkap untuk satu alternatif (dipakai form edit). */
function nilai_alternatif(int $kecamatanId): array
{
    $st = db()->prepare(
        'SELECT kriteria_id, nilai FROM penilaian WHERE kecamatan_id = :id'
    );
    $st->execute(['id' => $kecamatanId]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $out[(int) $row['kriteria_id']] = $row['nilai'];
    }
    return $out;
}
