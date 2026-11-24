<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/lib/data.php';
require_login();

$alternatives = $criteria = $values = $hasil = null;
$masalah = [];

try {
    [$alternatives, $criteria, $values] = load_data();
    $bobot = check_criteria($criteria);
    if (!$bobot['seimbang']) {
        $masalah[] = 'Total bobot = ' . num($bobot['total'], 4) . ' (idealnya 1,0000). Perhitungan tetap jalan karena bobot dinormalisasi otomatis, tapi sebaiknya dibetulkan di halaman Data.';
    }
    try {
        check_zero_cost(build_matrix($alternatives, $criteria, $values), $criteria);
    } catch (RuntimeException $ex) {
        $masalah[] = $ex->getMessage();
    }
    $hasil = hitung($alternatives, $criteria, $values);
} catch (Throwable $ex) {
    $masalah[] = $ex->getMessage();
}

page_head('Dashboard');
?>
<h1>Dashboard</h1>
<p class="lead">Judul: LAKAPRIOR — Sistem Pendukung Keputusan Penentuan Prioritas Kecamatan Rawan Kecelakaan Lalu Lintas di Kabupaten Sukoharjo Menggunakan TOPSIS dan SAW (Studi Kasus Data Kecelakaan 2019–2021).</p>

<?php foreach ($masalah as $m) { err($m); } ?>

<?php if ($hasil !== null): ?>
<div class="cards">
  <div class="card"><b><?= count($alternatives) ?></b><span>alternatif (kecamatan)</span></div>
  <div class="card"><b><?= count($criteria) ?></b><span>kriteria</span></div>
  <div class="card"><b><?= num($hasil['topsis']['ci'][array_key_first($hasil['topsis']['peringkat'])], 4) ?></b><span>Ci tertinggi (peringkat 1 TOPSIS)</span></div>
  <div class="card"><b><?= count(array_filter($hasil['topsis']['peringkat'], fn(int $r) => $r !== $hasil['saw']['peringkat'][array_search($r, $hasil['topsis']['peringkat'], true)])) === 0 ? '0' : count(array_filter(array_keys($hasil['topsis']['peringkat']), fn(int $i) => $hasil['topsis']['peringkat'][$i] !== $hasil['saw']['peringkat'][$i])) ?></b><span>alternatif dengan peringkat TOPSIS ≠ SAW</span></div>
</div>

<h2>Peringkat prioritas <span class="sub">hasil TOPSIS, 5 teratas</span></h2>
<div class="scroll">
<table>
  <thead>
    <tr><th>Peringkat</th><th class="l">Kecamatan</th><th>Ci (TOPSIS)</th><th class="l">Skor</th><th>Peringkat SAW</th></tr>
  </thead>
  <tbody>
  <?php foreach ($hasil['topsis']['peringkat'] as $i => $rank): ?>
    <?php if ($rank > 5) { continue; } $ciMax = max($hasil['topsis']['ci']); ?>
    <tr>
      <td><?= $rank ?></td>
      <td class="l"><?= e($alternatives[$i]['nama']) ?></td>
      <td><?= num($hasil['topsis']['ci'][$i], 4) ?></td>
      <td class="l"><span class="bar"><i style="width:<?= num($hasil['topsis']['ci'][$i] / $ciMax * 100, 1) ?>%"></i></span></td>
      <td><?= $hasil['saw']['peringkat'][$i] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint">Tabel lengkap 16 alternatif, langkah normalisasi, dan matriks keputusan ada di halaman <a href="hitung.php">Perhitungan</a>.</p>
<?php else: ?>
  <p class="lead">Data belum bisa dihitung. Periksa pesan di atas, atau import <code>db/schema.sql</code> dan <code>db/seed.sql</code> lewat phpMyAdmin.</p>
<?php endif; ?>
<?php page_foot(); ?>
