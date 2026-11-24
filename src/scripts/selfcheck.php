<?php
declare(strict_types=1);

/**
 * Uji mandiri mesin perhitungan tanpa MySQL:
 *   php src/scripts/selfcheck.php
 * Mencocokkan lib/mcdm.php dengan data/hasil_referensi.csv (baseline Python).
 */

require_once __DIR__ . '/../lib/mcdm.php';

$root = dirname(__DIR__, 2);
$alt = array_map('str_getcsv', file($root . '/data/alternatif_kecamatan.csv'));
$lak = array_map('str_getcsv', file($root . '/data/laka_sukoharjo_2019-2021.csv'));
array_shift($alt);
array_shift($lak);

// alternatif_kecamatan.csv: id, kecamatan, luas_km2, penduduk_2019, kepadatan, sumber
// laka_sukoharjo_2019-2021.csv: kecamatan, laka_2019, laka_2020, laka_2021, jumlah_laka, md, lb, lr, sumber
$laka = [];
foreach ($lak as $row) {
    $laka[$row[0]] = $row;
}

$criteria = [
    ['kode' => 'C1', 'nama' => 'Jumlah kejadian kecelakaan', 'tipe' => 'benefit', 'bobot' => 0.20],
    ['kode' => 'C2', 'nama' => 'Korban meninggal dunia', 'tipe' => 'benefit', 'bobot' => 0.20],
    ['kode' => 'C3', 'nama' => 'Korban luka berat', 'tipe' => 'benefit', 'bobot' => 0.10],
    ['kode' => 'C4', 'nama' => 'Korban luka ringan', 'tipe' => 'benefit', 'bobot' => 0.10],
    ['kode' => 'C5', 'nama' => 'Jumlah penduduk', 'tipe' => 'benefit', 'bobot' => 0.15],
    ['kode' => 'C6', 'nama' => 'Kepadatan penduduk', 'tipe' => 'benefit', 'bobot' => 0.15],
    ['kode' => 'C7', 'nama' => 'Luas wilayah', 'tipe' => 'cost', 'bobot' => 0.10],
];

$matrix = [];
$names = [];
foreach ($alt as $row) {
    $names[] = $row[1];
    $l = $laka[$row[1]];
    $matrix[] = [
        (float) $l[4], (float) $l[5], (float) $l[6], (float) $l[7],
        (float) $row[3], (float) $row[4], (float) $row[2],
    ];
}

$t = topsis($matrix, $criteria);
$s = saw($matrix, $criteria);

// baseline dari data/hasil_referensi.csv
$base = [];
$fh = fopen($root . '/data/hasil_referensi.csv', 'r');
fgetcsv($fh);
while (($row = fgetcsv($fh)) !== false) {
    $base[$row[0]] = ['ci' => (float) $row[3], 'pt' => (int) $row[4], 'saw' => (float) $row[5], 'ps' => (int) $row[6]];
}
fclose($fh);

$fail = 0;
$check = function (bool $ok, string $label) use (&$fail): void {
    printf("%-56s %s\n", $label, $ok ? 'OK' : 'GAGAL');
    if (!$ok) {
        $fail++;
    }
};

foreach ($names as $i => $nama) {
    $b = $base[$nama] ?? null;
    if ($b === null) {
        $check(false, "baris baseline ketemu untuk $nama");
        continue;
    }
    $check(abs($t['ci'][$i] - $b['ci']) < 1e-6, "Ci TOPSIS $nama = " . number_format($t['ci'][$i], 6, ',', '.'));
    $check($t['peringkat'][$i] === $b['pt'], "peringkat TOPSIS $nama = {$t['peringkat'][$i]}");
    $check(abs($s['v'][$i] - $b['saw']) < 1e-6, "skor SAW $nama = " . number_format($s['v'][$i], 6, ',', '.'));
    $check($s['peringkat'][$i] === $b['ps'], "peringkat SAW $nama = {$s['peringkat'][$i]}");
}

$check(check_criteria($criteria)['seimbang'], 'total bobot = 1,0000');

$top = $names[array_search(1, $t['peringkat'], true)];
$check($top === 'Sukoharjo', "peringkat 1 TOPSIS = $top");

$beda = 0;
foreach ($names as $i => $_) {
    if ($t['peringkat'][$i] !== $s['peringkat'][$i]) {
        $beda++;
    }
}
$check($beda === 5, "jumlah peringkat TOPSIS != SAW = $beda (harus 5)");

$total = 0;
foreach ($laka as $row) {
    $total += (int) $row[4];
}
$check($total === 3408, "total kejadian 2019-2021 = $total (harus 3.408)");
$check((int) $laka['Kartasura'][4] === 595, 'Kartasura = 595 kejadian (tertinggi di sumber)');

// penjaga: kriteria cost bernilai 0 harus ditolak SAW
try {
    $m = $matrix;
    $m[0][6] = 0.0;
    saw($m, $criteria);
    $check(false, 'SAW menolak kolom cost bernilai 0');
} catch (RuntimeException $ex) {
    $check(true, 'SAW menolak kolom cost bernilai 0');
}

echo $fail === 0 ? "\nSEMUA UJI LULUS\n" : "\n$fail UJI GAGAL\n";
exit($fail === 0 ? 0 : 1);
