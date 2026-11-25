<?php
declare(strict_types=1);

/**
 * Mesin perhitungan TOPSIS + SAW. Fungsi murni: tidak menyentuh database,
 * supaya bisa diuji lewat scripts/selfcheck.php tanpa MySQL.
 *
 * Konvensi: $matrix[$i][$j] = nilai alternatif ke-$i pada kriteria ke-$j.
 * $criteria[$j] = ['kode'=>..,'nama'=>..,'tipe'=>'benefit'|'cost','bobot'=>float]
 */

/** @return array daftar bobot dinormalisasi + validasi */
function check_criteria(array $criteria): array
{
    $total = 0.0;
    foreach ($criteria as $j => $c) {
        if (!in_array($c['tipe'], ['benefit', 'cost'], true)) {
            throw new RuntimeException("Kriteria {$c['kode']}: tipe harus benefit atau cost.");
        }
        if ((float) $c['bobot'] <= 0) {
            throw new RuntimeException("Kriteria {$c['kode']}: bobot harus lebih dari 0.");
        }
        $total += (float) $c['bobot'];
    }
    if ($total <= 0) {
        throw new RuntimeException('Total bobot harus lebih dari 0.');
    }
    return ['total' => $total, 'seimbang' => abs($total - 1.0) < 1e-6];
}

/** Validasi kolom cost agar normalisasi SAW min/x tidak 0/0. */
function check_zero_cost(array $matrix, array $criteria): void
{
    foreach ($criteria as $j => $c) {
        if ($c['tipe'] !== 'cost') {
            continue;
        }
        $min = null;
        foreach ($matrix as $row) {
            $min = $min === null ? $row[$j] : min($min, $row[$j]);
        }
        if ($min !== null && abs((float) $min) < 1e-12) {
            // ponytail: SAW min/x pecah di baris bernilai 0; TOPSIS tidak.
            throw new RuntimeException(
                "Kriteria {$c['kode']} ({$c['nama']}) bertipe cost tapi ada alternatif bernilai 0. " .
                'Ubah tipenya jadi benefit atau ganti kriteria, normalisasi SAW min/x akan 0/0.'
            );
        }
    }
}

/**
 * TOPSIS lengkap beserta seluruh tabel antarhasil.
 * @return array{divisor:float[],r:array,y:array,aplus:float[],aminus:float[],dplus:float[],dminus:float[],ci:float[],peringkat:int[]}
 */
function topsis(array $matrix, array $criteria): array
{
    $valid = check_criteria($criteria);
    $weights = array_map(fn(float $w) => $w / $valid['total'], array_column($criteria, 'bobot'));
    $nAlt = count($matrix);
    $nCrit = count($criteria);

    // 1. pembagi akar jumlah kuadrat kolom + 2. matriks ternormalisasi R
    $divisor = [];
    $r = [];
    for ($j = 0; $j < $nCrit; $j++) {
        $sum = 0.0;
        foreach ($matrix as $row) {
            $sum += $row[$j] ** 2;
        }
        $divisor[$j] = sqrt($sum);
        if ($divisor[$j] < 1e-12) {
            throw new RuntimeException("Kriteria {$criteria[$j]['kode']}: semua nilai 0, tidak bisa dinormalisasi.");
        }
    }
    foreach ($matrix as $i => $row) {
        foreach ($row as $j => $x) {
            $r[$i][$j] = $x / $divisor[$j];
        }
    }

    // 3. matriks terbobot Y
    $y = [];
    foreach ($r as $i => $row) {
        foreach ($row as $j => $v) {
            $y[$i][$j] = $v * $weights[$j];
        }
    }

    // 4. solusi ideal positif (A+) dan negatif (A-)
    $aplus = [];
    $aminus = [];
    for ($j = 0; $j < $nCrit; $j++) {
        $col = array_column($y, $j);
        $benefit = $criteria[$j]['tipe'] === 'benefit';
        $aplus[$j] = $benefit ? max($col) : min($col);
        $aminus[$j] = $benefit ? min($col) : max($col);
    }

    // 5. jarak ke A+ dan A- lalu 6. nilai preferensi Ci
    $dplus = [];
    $dminus = [];
    $ci = [];
    for ($i = 0; $i < $nAlt; $i++) {
        $sp = 0.0;
        $sm = 0.0;
        for ($j = 0; $j < $nCrit; $j++) {
            $sp += ($y[$i][$j] - $aplus[$j]) ** 2;
            $sm += ($y[$i][$j] - $aminus[$j]) ** 2;
        }
        $dplus[$i] = sqrt($sp);
        $dminus[$i] = sqrt($sm);
        $denom = $dplus[$i] + $dminus[$i];
        $ci[$i] = $denom < 1e-12 ? 0.0 : $dminus[$i] / $denom;
    }

    arsort($ci);
    $peringkat = peringkat($ci);

    return compact('divisor', 'r', 'y', 'aplus', 'aminus', 'dplus', 'dminus', 'ci', 'peringkat');
}

/**
 * SAW: normalisasi (benefit x/max, cost min/x) lalu penjumlahan terbobot.
 * @return array{r:array,v:float[],peringkat:int[]}
 */
function saw(array $matrix, array $criteria): array
{
    $valid = check_criteria($criteria);
    check_zero_cost($matrix, $criteria);
    $weights = array_map(fn(float $w) => $w / $valid['total'], array_column($criteria, 'bobot'));
    $nCrit = count($criteria);

    $r = [];
    foreach ($matrix as $i => $row) {
        foreach ($row as $j => $x) {
            $col = array_column($matrix, $j);
            $r[$i][$j] = $criteria[$j]['tipe'] === 'benefit'
                ? $x / max($col)
                : min($col) / $x;
        }
    }

    $v = [];
    foreach ($r as $i => $row) {
        $v[$i] = 0.0;
        foreach ($row as $j => $val) {
            $v[$i] += $val * $weights[$j];
        }
    }

    $sorted = $v;
    arsort($sorted);

    return ['r' => $r, 'v' => $v, 'peringkat' => peringkat($sorted)];
}

/** Nilai besar = peringkat 1. Urutan kunci mengikuti array yang sudah diurutkan. */
function peringkat(array $sortedDesc): array
{
    $out = [];
    $rank = 0;
    foreach ($sortedDesc as $i => $_) {
        $out[$i] = ++$rank;
    }
    return $out;
}

/** Ambil matriks nilai dari tabel penilaian: $values[alternatif_id][kriteria_id]. */
function build_matrix(array $alternatives, array $criteria, array $values): array
{
    $matrix = [];
    foreach ($alternatives as $i => $alt) {
        foreach ($criteria as $j => $crit) {
            $key = $alt['id'] . '|' . $crit['id'];
            if (!isset($values[$key])) {
                throw new RuntimeException("Nilai kosong untuk {$alt['nama']} - {$crit['kode']}.");
            }
            $matrix[$i][$j] = (float) $values[$key];
        }
    }
    return $matrix;
}
