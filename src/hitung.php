<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/lib/data.php';
require_login();

$pesan = null;
[$alternatives, $criteria, $values] = load_data();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'simpan_hasil') {
        try {
            simpan_hasil($alternatives, hitung($alternatives, $criteria, $values));
            flash('Hasil hitung tersimpan di tabel hasil.');
            header('Location: hitung.php');
            exit;
        } catch (Throwable $ex) {
            $pesan = 'ERR: ' . $ex->getMessage();
        }
    }
}

$matrix = build_matrix($alternatives, $criteria, $values);
$bobotValid = check_criteria($criteria);
$hasil = hitung($alternatives, $criteria, $values);
$t = $hasil['topsis'];
$s = $hasil['saw'];

page_head('Perhitungan');
?>
<h1>Perhitungan TOPSIS dan SAW</h1>
<p class="lead">Bobot mentah dijumlahkan <?= num($bobotValid['total'], 4) ?> lalu dinormalisasi otomatis di dalam perhitungan (w<sub>j</sub> = w<sub>j</sub> / Σw).</p>
<?php if ($pesan !== null) {
    err(substr($pesan, 5));
} ?>

<h2>1. Matriks keputusan <span class="sub">x<sub>ij</sub></span></h2>
<div class="scroll">
<table>
  <thead>
    <tr>
      <th class="l">Alternatif</th>
      <?php foreach ($criteria as $c): ?>
        <th><?= e($c['kode']) ?><br><span class="tag <?= e($c['tipe']) ?>"><?= e($c['tipe']) ?></span><br><span class="hint">w=<?= num((float) $c['bobot'], 2) ?></span></th>
      <?php endforeach; ?>
    </tr>
    <tr><th class="l">Nama kriteria</th>
      <?php foreach ($criteria as $c): ?><th class="hint"><?= e($c['nama']) ?><br>(<?= e($c['satuan']) ?>)</th><?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($alternatives as $i => $alt): ?>
    <tr>
      <td class="l"><?= e($alt['nama']) ?></td>
      <?php foreach ($criteria as $j => $c): ?><td><?= num($matrix[$i][$j], 2) ?></td><?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>2. Matriks ternormalisasi R <span class="sub">r<sub>ij</sub> = x<sub>ij</sub> / √(Σx<sub>ij</sub>²)</span></h2>
<div class="scroll">
<table>
  <thead><tr><th class="l">Alternatif</th><?php foreach ($criteria as $c): ?><th><?= e($c['kode']) ?></th><?php endforeach; ?></tr></thead>
  <tfoot><tr><td class="l">Pembagi √(Σx²)</td><?php foreach ($criteria as $j => $c): ?><td><?= num($t['divisor'][$j], 4) ?></td><?php endforeach; ?></tr></tfoot>
  <tbody>
  <?php foreach ($alternatives as $i => $alt): ?>
    <tr><td class="l"><?= e($alt['nama']) ?></td><?php foreach ($criteria as $j => $c): ?><td><?= num($t['r'][$i][$j], 4) ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>3. Matriks terbobot Y <span class="sub">y<sub>ij</sub> = w<sub>j</sub> · r<sub>ij</sub></span></h2>
<div class="scroll">
<table>
  <thead><tr><th class="l">Alternatif</th><?php foreach ($criteria as $c): ?><th><?= e($c['kode']) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($alternatives as $i => $alt): ?>
    <tr><td class="l"><?= e($alt['nama']) ?></td><?php foreach ($criteria as $j => $c): ?><td><?= num($t['y'][$i][$j], 4) ?></td><?php endforeach; ?></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td class="l">A+ (ideal positif)</td><?php foreach ($criteria as $j => $c): ?><td><?= num($t['aplus'][$j], 4) ?></td><?php endforeach; ?></tr>
    <tr><td class="l">A− (ideal negatif)</td><?php foreach ($criteria as $j => $c): ?><td><?= num($t['aminus'][$j], 4) ?></td><?php endforeach; ?></tr>
  </tfoot>
</table>
</div>

<h2>4. Jarak dan nilai preferensi Ci</h2>
<p class="hint">D+ = √Σ(y<sub>ij</sub> − A+<sub>j</sub>)² ; D− = √Σ(y<sub>ij</sub> − A−<sub>j</sub>)² ; Ci = D− / (D+ + D−)</p>
<div class="scroll">
<table>
  <thead><tr><th class="l">Kecamatan</th><th>D+</th><th>D−</th><th>Ci</th><th class="l">Skor</th><th>Peringkat TOPSIS</th></tr></thead>
  <tbody>
  <?php $ciMax = max($t['ci']); foreach ($t['peringkat'] as $i => $rank): ?>
    <tr>
      <td class="l"><?= e($alternatives[$i]['nama']) ?></td>
      <td><?= num($t['dplus'][$i], 4) ?></td>
      <td><?= num($t['dminus'][$i], 4) ?></td>
      <td><b><?= num($t['ci'][$i], 4) ?></b></td>
      <td class="l"><span class="bar"><i style="width:<?= num($t['ci'][$i] / $ciMax * 100, 1) ?>%"></i></span></td>
      <td><?= $rank ?></td>
    </tr>
  <?php endforeach; ?>
  <tfoot><tr><td class="l">Jumlah</td><td colspan="2"></td><td><?= num(array_sum($t['ci']), 4) ?></td><td></td><td></td></tr></tfoot>
  </tbody>
</table>
</div>

<h2>5. SAW sebagai pembanding <span class="sub">benefit r = x/max(x) ; cost r = min(x)/x ; V = Σ w<sub>j</sub>·r<sub>ij</sub></span></h2>
<div class="scroll">
<table>
  <thead><tr><th class="l">Kecamatan</th><?php foreach ($criteria as $c): ?><th>r <?= e($c['kode']) ?></th><?php endforeach; ?><th>V (SAW)</th><th class="l">Skor</th><th>Peringkat SAW</th></tr></thead>
  <tbody>
  <?php $vMax = max($s['v']); foreach ($s['peringkat'] as $i => $rank): ?>
    <tr>
      <td class="l"><?= e($alternatives[$i]['nama']) ?></td>
      <?php foreach ($criteria as $j => $c): ?><td><?= num($s['r'][$i][$j], 4) ?></td><?php endforeach; ?>
      <td><b><?= num($s['v'][$i], 4) ?></b></td>
      <td class="l"><span class="bar alt"><i style="width:<?= num($s['v'][$i] / $vMax * 100, 1) ?>%"></i></span></td>
      <td><?= $rank ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>6. Perbandingan peringkat TOPSIS vs SAW</h2>
<div class="scroll">
<table>
  <thead><tr><th class="l">Kecamatan</th><th>Peringkat TOPSIS</th><th>Peringkat SAW</th><th>Selisih</th></tr></thead>
  <tbody>
  <?php $beda = 0; foreach ($alternatives as $i => $alt):
      $d = $t['peringkat'][$i] - $s['peringkat'][$i];
      if ($d !== 0) { $beda++; } ?>
    <tr>
      <td class="l"><?= e($alt['nama']) ?></td>
      <td><?= $t['peringkat'][$i] ?></td>
      <td><?= $s['peringkat'][$i] ?></td>
      <td><?= ($d > 0 ? '+' : '') . $d ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td class="l">Jumlah alternatif dengan peringkat berbeda</td><td colspan="3"><?= $beda ?> dari <?= count($alternatives) ?></td></tr></tfoot>
</table>
</div>

<form method="post" class="inline" style="margin-top:14px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="simpan_hasil">
  <button type="submit">Simpan hasil ke tabel hasil</button>
</form>
<p class="hint">Hasil ini juga dipakai untuk tabel perbandingan di laporan: hitung manual satu kecamatan lalu cocokkan dengan baris di tabel 4.</p>
<?php page_foot(); ?>
