<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/lib/data.php';
require_login();

$tabs = nav_tabs();
$tab = (string) ($_GET['tab'] ?? 'keputusan');
if (!isset($tabs[$tab])) {
    $tab = 'keputusan';
}

/** Keadaan kosong atau galat: sebut sebabnya, lalu satu langkah berikutnya. */
function blok_kosong(array $masalah): void
{
    ?>
<div class="kosong">
  <h2>Hasil belum bisa ditampilkan</h2>
  <?php if ($masalah === []): ?>
    <p>Data kecamatan atau kriteria masih kosong.</p>
  <?php else: ?>
    <ul><?php foreach ($masalah as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <p>Periksa <a href="index.php?tab=data">tab Data dan bobot</a>. Kalau database masih kosong, import <code>db/schema.sql</code> lalu <code>db/seed.sql</code> lebih dulu.</p>
</div>
<?php
}

$alternatif = $kriteria = $nilai = null;
$galat_db = null;
try {
    [$alternatif, $kriteria, $nilai] = load_data();
} catch (Throwable $ex) {
    $galat_db = $ex->getMessage();
}

$bobot = ['total' => 0.0, 'seimbang' => false];
$masalah = [];
$hasil = null;

if ($galat_db !== null) {
    $masalah[] = 'Tidak bisa membaca database: ' . $galat_db;
} else {
    // Tiap aksi kembali ke tab asalnya, supaya pengguna tidak kehilangan konteks.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $balik = $tab;
        $pesan = null;
        try {
            switch (post('action')) {
                case 'simpan_nilai':
                    $st = db()->prepare('UPDATE penilaian SET nilai = :n WHERE kecamatan_id = :k AND kriteria_id = :c');
                    foreach (($_POST['nilai'] ?? []) as $kid => $perKriteria) {
                        foreach ($perKriteria as $cid => $raw) {
                            $angka = str_replace(',', '.', trim((string) $raw));
                            if ($angka === '' || !is_numeric($angka)) {
                                throw new InvalidArgumentException("Nilai tidak sah untuk kecamatan #$kid kriteria #$cid.");
                            }
                            $st->execute(['n' => (float) $angka, 'k' => (int) $kid, 'c' => (int) $cid]);
                        }
                    }
                    $pesan = 'Nilai alternatif tersimpan.';
                    break;

                case 'simpan_bobot':
                    $st = db()->prepare('UPDATE kriteria SET bobot = :b, tipe = :t WHERE id = :id');
                    foreach (($_POST['bobot'] ?? []) as $cid => $raw) {
                        $angka = str_replace(',', '.', trim((string) $raw));
                        $tipe = (string) ($_POST['tipe'][$cid] ?? 'benefit');
                        if (!is_numeric($angka) || $angka <= 0) {
                            throw new InvalidArgumentException("Bobot kriteria #$cid harus angka lebih dari 0.");
                        }
                        if (!in_array($tipe, ['benefit', 'cost'], true)) {
                            throw new InvalidArgumentException("Tipe kriteria #$cid tidak dikenal.");
                        }
                        $st->execute(['b' => (float) $angka, 't' => $tipe, 'id' => (int) $cid]);
                    }
                    $pesan = 'Bobot dan tipe kriteria tersimpan.';
                    break;

                case 'tambah_alternatif':
                    $nama = post('nama');
                    if ($nama === '') {
                        throw new InvalidArgumentException('Nama kecamatan wajib diisi.');
                    }
                    $id = (int) db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM kecamatan')->fetchColumn();
                    db()->prepare('INSERT INTO kecamatan (id, nama, luas_km2, kepadatan_penduduk, penduduk_jiwa)
                                   VALUES (:id, :n, :l, :k, :p)')
                        ->execute(['id' => $id, 'n' => $nama, 'l' => post_num('luas_km2'),
                                   'k' => post_num('kepadatan'), 'p' => post_num('penduduk')]);
                    db()->prepare('INSERT INTO penilaian (kecamatan_id, kriteria_id, nilai)
                                   SELECT :id, id, 0 FROM kriteria')->execute(['id' => $id]);
                    $pesan = "Kecamatan '$nama' ditambahkan. Isi nilainya di bagian Data mentah per kecamatan.";
                    break;

                case 'hapus_alternatif':
                    db()->prepare('DELETE FROM kecamatan WHERE id = :id')->execute(['id' => (int) post('kecamatan_id')]);
                    $pesan = 'Kecamatan dihapus.';
                    break;

                case 'tambah_kriteria':
                    $kode = strtoupper(post('kode'));
                    if ($kode === '' || post('nama') === '') {
                        throw new InvalidArgumentException('Kode dan nama kriteria wajib diisi.');
                    }
                    db()->prepare('INSERT INTO kriteria (kode, nama, satuan, tipe, bobot)
                                   VALUES (:k, :n, :s, :t, :b)')
                        ->execute(['k' => $kode, 'n' => post('nama'), 's' => post('satuan', '-'),
                                   't' => post('tipe') === 'cost' ? 'cost' : 'benefit', 'b' => post_num('bobot')]);
                    $cid = (int) db()->lastInsertId();
                    db()->prepare('INSERT INTO penilaian (kecamatan_id, kriteria_id, nilai)
                                   SELECT id, :cid, 0 FROM kecamatan')->execute(['cid' => $cid]);
                    $pesan = "Kriteria '$kode' ditambahkan dengan nilai 0 untuk semua kecamatan.";
                    break;

                case 'hapus_kriteria':
                    db()->prepare('DELETE FROM kriteria WHERE id = :id')->execute(['id' => (int) post('kriteria_id')]);
                    $pesan = 'Kriteria dihapus.';
                    break;

                case 'simpan_hasil':
                    simpan_hasil($alternatif, hitung($alternatif, $kriteria, $nilai));
                    $balik = 'perhitungan';
                    $pesan = 'Hasil hitung tersimpan di tabel hasil.';
                    break;
            }
        } catch (Throwable $ex) {
            $pesan = 'Gagal: ' . $ex->getMessage();
        }
        if ($pesan !== null) {
            flash($pesan);
        }
        header('Location: index.php?tab=' . $balik);
        exit;
    }

    try {
        $bobot = check_criteria($kriteria);
    } catch (Throwable $ex) {
        $masalah[] = $ex->getMessage();
    }
    if ($bobot['total'] > 0 && !$bobot['seimbang']) {
        $masalah[] = 'Total bobot ' . num($bobot['total'], 4) . ', belum 1,0000. Perhitungan tetap jalan karena bobot dinormalisasi otomatis, tetapi sebaiknya dirapikan di bagian Bobot kriteria.';
    }

    $kosong = $alternatif === [] || $kriteria === [];

    if ($tab !== 'data' && !$kosong) {
        try {
            $matrix = build_matrix($alternatif, $kriteria, $nilai);
            check_zero_cost($matrix, $kriteria);
            $hasil = ['matrix' => $matrix, 'topsis' => topsis($matrix, $kriteria), 'saw' => saw($matrix, $kriteria)];
        } catch (Throwable $ex) {
            $masalah[] = $ex->getMessage();
        }
    }
}

page_head($tabs[$tab], $tab);
foreach ($masalah as $m) {
    err($m);
}
?>

<?php if ($tab === 'keputusan' || $tab === 'perhitungan'): ?>

<?php if ($hasil === null): ?>
<?php blok_kosong($masalah); ?>

<?php elseif ($tab === 'keputusan'):
    $t = $hasil['topsis'];
    $s = $hasil['saw'];
    $urut = array_keys($t['peringkat']);
    $juara = $urut[0];
    $juara_saw = array_search(1, $s['peringkat'], true);
    $ci_max = max($t['ci']);
    $beda = 0;
    foreach ($alternatif as $i => $alt) {
        if ($t['peringkat'][$i] !== $s['peringkat'][$i]) {
            $beda++;
        }
    }
    $jumlah = count($alternatif);
?>
<h1>Kecamatan paling rawan kecelakaan di Kabupaten Sukoharjo</h1>
<p class="lead">Perhitungan dari data kecelakaan lalu lintas 2019 sampai 2021 pada <?= $jumlah ?> kecamatan, memakai metode TOPSIS dengan bobot kriteria yang diatur di tab Data dan bobot.</p>

<div class="verdict">
  <p class="rank">Peringkat 1 dari <?= $jumlah ?> kecamatan</p>
  <p class="name"><?= e($alternatif[$juara]['nama']) ?></p>
  <p class="score">Nilai prioritas <?= num($t['ci'][$juara], 4) ?> dari 1,0000</p>
</div>
<p>Nilai prioritas berkisar 0 sampai 1. Makin mendekati 1, makin tinggi prioritas penanganan kecamatan itu.</p>

<h2>Peringkat lengkap <?= $jumlah ?> kecamatan <span class="sub">metode TOPSIS</span></h2>
<div class="scroll">
<table>
  <thead>
    <tr><th>Peringkat</th><th class="l">Kecamatan</th><th>Nilai prioritas</th><th class="l">Perbandingan nilai</th><th>Peringkat SAW</th></tr>
  </thead>
  <tbody>
  <?php foreach ($urut as $i): ?>
    <tr>
      <td><?= $t['peringkat'][$i] ?></td>
      <td class="l"><?= e($alternatif[$i]['nama']) ?></td>
      <td><?= num($t['ci'][$i], 4) ?></td>
      <td class="l"><span class="bar"><i style="width:<?= pct($t['ci'][$i] / $ci_max * 100) ?>%"></i></span></td>
      <td><?= $s['peringkat'][$i] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint">Batang pada kolom Perbandingan nilai membandingkan besar nilai antar kecamatan. Kolom terakhir memakai metode pembanding SAW (Simple Additive Weighting): <?= e($alternatif[$juara_saw]['nama']) ?> menempati peringkat 1 di metode itu. Dari <?= $jumlah ?> kecamatan, <?= $beda ?> mendapat peringkat berbeda antara kedua metode. Langkah lengkapnya ada di tab Cara hitung.</p>

<?php else:
    $t = $hasil['topsis'];
    $s = $hasil['saw'];
    $matrix = $hasil['matrix'];
    $ci_max = max($t['ci']);
    $v_max = max($s['v']);
    $beda = 0;
?>
<h1>Cara perhitungan</h1>
<p class="lead">TOPSIS dipakai sebagai metode utama. SAW dihitung sebagai pembanding, untuk melihat apakah urutan peringkatnya berubah. Klik tiap langkah untuk membuka tabelnya.</p>

<details>
  <summary>
    <h2 class="step">1. Data asli tiap kecamatan</h2>
    <span class="notasi">matriks keputusan x<sub>ij</sub></span>
  </summary>
  <div class="body">
    <p>Angka apa adanya dari sumber, belum diubah. Satuannya berbeda-beda, jadi belum bisa dibandingkan langsung.</p>
    <div class="scroll">
    <table>
      <thead>
        <tr>
          <th class="l">Kecamatan</th>
          <?php foreach ($kriteria as $c): ?>
            <th><?= e($c['kode']) ?><br><span class="tag <?= e($c['tipe']) ?>"><?= e($c['tipe']) ?></span><br><span class="hint">bobot <?= num((float) $c['bobot'], 2) ?></span></th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <th class="l">Nama kriteria</th>
          <?php foreach ($kriteria as $c): ?><th class="hint"><?= e($c['nama']) ?><br>(<?= e($c['satuan']) ?>)</th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($alternatif as $i => $alt): ?>
        <tr>
          <td class="l"><?= e($alt['nama']) ?></td>
          <?php foreach ($kriteria as $j => $c): ?><td><?= num($matrix[$i][$j], 2) ?></td><?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="hint">Bobot mentah dijumlahkan <?= num($bobot['total'], 4) ?> lalu dinormalisasi otomatis di dalam perhitungan (w<sub>j</sub> = w<sub>j</sub> / jumlah bobot).</p>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">2. Ubah satuan menjadi skala yang setara</h2>
    <span class="notasi">r<sub>ij</sub> = x<sub>ij</sub> / akar(jumlah kuadrat kolom)</span>
  </summary>
  <div class="body">
    <p>Setiap angka dibagi akar jumlah kuadrat sekolomnya. Hasilnya kejadian, jiwa, dan kilometer berada pada skala yang bisa dibandingkan.</p>
    <div class="scroll">
    <table>
      <thead><tr><th class="l">Kecamatan</th><?php foreach ($kriteria as $c): ?><th><?= e($c['kode']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($alternatif as $i => $alt): ?>
        <tr><td class="l"><?= e($alt['nama']) ?></td><?php foreach ($kriteria as $j => $c): ?><td><?= num($t['r'][$i][$j], 4) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td class="l">Pembagi</td><?php foreach ($kriteria as $j => $c): ?><td><?= num($t['divisor'][$j], 4) ?></td><?php endforeach; ?></tr></tfoot>
    </table>
    </div>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">3. Kalikan dengan bobot tiap kriteria</h2>
    <span class="notasi">y<sub>ij</sub> = w<sub>j</sub> x r<sub>ij</sub></span>
  </summary>
  <div class="body">
    <p>Bobot menentukan seberapa besar satu kriteria memengaruhi hasil akhir. Dua baris terbawah adalah titik acuan tiap kriteria: A+ yang terbaik dan A- yang terburuk.</p>
    <div class="scroll">
    <table>
      <thead><tr><th class="l">Kecamatan</th><?php foreach ($kriteria as $c): ?><th><?= e($c['kode']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($alternatif as $i => $alt): ?>
        <tr><td class="l"><?= e($alt['nama']) ?></td><?php foreach ($kriteria as $j => $c): ?><td><?= num($t['y'][$i][$j], 4) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td class="l">A+ (acuan terbaik)</td><?php foreach ($kriteria as $j => $c): ?><td><?= num($t['aplus'][$j], 4) ?></td><?php endforeach; ?></tr>
        <tr><td class="l">A- (acuan terburuk)</td><?php foreach ($kriteria as $j => $c): ?><td><?= num($t['aminus'][$j], 4) ?></td><?php endforeach; ?></tr>
      </tfoot>
    </table>
    </div>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">4. Hitung jarak ke acuan terbaik dan terburuk</h2>
    <span class="notasi">D+ jarak ke A+; D- jarak ke A-; nilai prioritas Ci = D- / (D+ + D-)</span>
  </summary>
  <div class="body">
    <p>Kecamatan yang dekat ke acuan terbaik dan jauh dari acuan terburuk mendapat nilai tertinggi. Kolom Ci inilah yang menjadi urutan di tab Hasil.</p>
    <div class="scroll">
    <table>
      <thead><tr><th class="l">Kecamatan</th><th>D+</th><th>D-</th><th>Nilai prioritas (Ci)</th><th class="l">Perbandingan nilai</th><th>Peringkat</th></tr></thead>
      <tbody>
      <?php foreach ($t['peringkat'] as $i => $rank): ?>
        <tr>
          <td class="l"><?= e($alternatif[$i]['nama']) ?></td>
          <td><?= num($t['dplus'][$i], 4) ?></td>
          <td><?= num($t['dminus'][$i], 4) ?></td>
          <td><?= num($t['ci'][$i], 4) ?></td>
          <td class="l"><span class="bar"><i style="width:<?= pct($t['ci'][$i] / $ci_max * 100) ?>%"></i></span></td>
          <td><?= $rank ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">5. Hitung ulang dengan metode pembanding</h2>
    <span class="notasi">SAW: r = x / max(x) untuk benefit, r = min(x) / x untuk cost; V = jumlah w<sub>j</sub> x r<sub>ij</sub></span>
  </summary>
  <div class="body">
    <p>SAW membagi tiap angka dengan nilai terbesar sekolom (atau terkecil, untuk kriteria cost), lalu mengalikannya dengan bobot dan menjumlahkannya. Cara ini lebih sederhana daripada TOPSIS.</p>
    <div class="scroll">
    <table>
      <thead>
        <tr><th class="l">Kecamatan</th><?php foreach ($kriteria as $c): ?><th>r <?= e($c['kode']) ?></th><?php endforeach; ?><th>V (SAW)</th><th class="l">Perbandingan nilai</th><th>Peringkat</th></tr>
      </thead>
      <tbody>
      <?php foreach ($s['peringkat'] as $i => $rank): ?>
        <tr>
          <td class="l"><?= e($alternatif[$i]['nama']) ?></td>
          <?php foreach ($kriteria as $j => $c): ?><td><?= num($s['r'][$i][$j], 4) ?></td><?php endforeach; ?>
          <td><?= num($s['v'][$i], 4) ?></td>
          <td class="l"><span class="bar"><i style="width:<?= pct($s['v'][$i] / $v_max * 100) ?>%"></i></span></td>
          <td><?= $rank ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">6. Bandingkan urutan kedua metode</h2>
    <span class="notasi">selisih peringkat</span>
  </summary>
  <div class="body">
    <p>Selisih positif berarti TOPSIS menempatkan kecamatan itu lebih rendah daripada SAW. Perbedaan urutan muncul karena keduanya memakai cara normalisasi dan pengukuran jarak yang berbeda.</p>
    <div class="scroll">
    <table>
      <thead><tr><th class="l">Kecamatan</th><th>Peringkat TOPSIS</th><th>Peringkat SAW</th><th>Selisih</th></tr></thead>
      <tbody>
      <?php foreach ($alternatif as $i => $alt):
          $d = $t['peringkat'][$i] - $s['peringkat'][$i];
          if ($d !== 0) {
              $beda++;
          } ?>
        <tr>
          <td class="l"><?= e($alt['nama']) ?></td>
          <td><?= $t['peringkat'][$i] ?></td>
          <td><?= $s['peringkat'][$i] ?></td>
          <td><?= $d > 0 ? '+' . $d : (string) $d ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td class="l">Peringkat berbeda</td><td colspan="3"><?= $beda ?> dari <?= count($alternatif) ?> kecamatan</td></tr></tfoot>
    </table>
    </div>
  </div>
</details>

<form method="post" class="inline">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="simpan_hasil">
  <button type="submit">Simpan hasil ini ke database</button>
</form>
<p class="hint">Hasil tersimpan di tabel <code>hasil</code> supaya bisa dibuka lagi tanpa menghitung ulang.</p>
<?php endif; ?>

<?php else: ?>

<?php if ($galat_db !== null || $kosong): ?>
<?php blok_kosong($masalah); ?>
<?php else: ?>
<h1>Data dan bobot</h1>
<p class="lead">Bobot menentukan seberapa besar tiap kriteria memengaruhi peringkat. Ubah angkanya, simpan, lalu buka tab Hasil.</p>

<h2>Bobot kriteria <span class="sub">total sekarang <?= num($bobot['total'], 4) ?></span></h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="simpan_bobot">
  <div class="scroll">
  <table>
    <thead><tr><th class="l">Kode</th><th class="l">Kriteria</th><th class="l">Satuan</th><th class="l">Tipe</th><th>Bobot</th><th>Porsi</th></tr></thead>
    <tbody>
    <?php foreach ($kriteria as $c): ?>
      <tr>
        <td class="l"><?= e($c['kode']) ?></td>
        <td class="l"><?= e($c['nama']) ?></td>
        <td class="l"><?= e($c['satuan']) ?></td>
        <td class="l">
          <select name="tipe[<?= (int) $c['id'] ?>]">
            <option value="benefit" <?= $c['tipe'] === 'benefit' ? 'selected' : '' ?>>benefit</option>
            <option value="cost" <?= $c['tipe'] === 'cost' ? 'selected' : '' ?>>cost</option>
          </select>
        </td>
        <td><input type="number" step="any" name="bobot[<?= (int) $c['id'] ?>]" value="<?= e((string) $c['bobot']) ?>"></td>
        <td><?= $bobot['total'] > 0 ? num((float) $c['bobot'] / $bobot['total'] * 100, 1) : '-' ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td class="l" colspan="4">Total</td><td><?= num($bobot['total'], 4) ?></td><td><?= $bobot['total'] > 0 ? '100,0' : '-' ?>%</td></tr></tfoot>
  </table>
  </div>
  <p class="hint">Tipe benefit berarti angka besar menaikkan prioritas. Tipe cost berarti angka kecil yang menaikkan prioritas.</p>
  <button type="submit" class="primary">Simpan bobot dan tipe</button>
</form>

<details>
  <summary>
    <h2 class="step">Data mentah per kecamatan</h2>
    <span class="notasi"><?= count($alternatif) * count($kriteria) ?> angka, jarang diubah</span>
  </summary>
  <div class="body">
    <p class="hint">Angka di bawah adalah data asli dari sumber. Ubah hanya kalau ada pembaruan data.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="simpan_nilai">
      <div class="scroll">
      <table>
        <thead>
          <tr><th class="l">Kecamatan</th><?php foreach ($kriteria as $c): ?><th><?= e($c['kode']) ?><br><span class="hint"><?= e($c['satuan']) ?></span></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($alternatif as $alt): ?>
          <tr>
            <td class="l"><?= e($alt['nama']) ?></td>
            <?php foreach ($kriteria as $c): $key = $alt['id'] . '|' . $c['id']; ?>
              <td><input type="number" step="any" name="nilai[<?= (int) $alt['id'] ?>][<?= (int) $c['id'] ?>]" value="<?= e((string) ($nilai[$key] ?? '0')) ?>"></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <button type="submit">Simpan semua nilai</button>
    </form>
  </div>
</details>

<details>
  <summary>
    <h2 class="step">Tambah atau hapus kecamatan dan kriteria</h2>
    <span class="notasi">mengubah isi database</span>
  </summary>
  <div class="body">
    <div class="grid">
      <div class="panel">
        <h3>Tambah kecamatan</h3>
        <form method="post" class="stack">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="tambah_alternatif">
          <label>Nama kecamatan <input name="nama" required></label>
          <label>Luas wilayah (km2) <input type="number" step="any" name="luas_km2" required></label>
          <label>Kepadatan penduduk (jiwa/km2) <input type="number" step="any" name="kepadatan" required></label>
          <label>Jumlah penduduk (jiwa) <input type="number" step="any" name="penduduk" required></label>
          <button type="submit">Tambah kecamatan</button>
        </form>
      </div>

      <div class="panel">
        <h3>Tambah kriteria</h3>
        <form method="post" class="stack">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="tambah_kriteria">
          <label>Kode (mis. C9) <input name="kode" required></label>
          <label>Nama kriteria <input name="nama" required></label>
          <label>Satuan <input name="satuan" placeholder="km / jiwa / kejadian"></label>
          <label>Tipe
            <select name="tipe">
              <option value="benefit">benefit, angka besar menaikkan prioritas</option>
              <option value="cost">cost, angka kecil menaikkan prioritas</option>
            </select>
          </label>
          <label>Bobot <input type="number" step="any" name="bobot" value="0.1" required></label>
          <button type="submit">Tambah kriteria</button>
        </form>
      </div>

      <div class="panel">
        <h3>Hapus kecamatan</h3>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="hapus_alternatif">
          <select name="kecamatan_id">
            <?php foreach ($alternatif as $alt): ?>
              <option value="<?= (int) $alt['id'] ?>"><?= e($alt['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="danger">Hapus</button>
        </form>
        <p class="hint">Nilai kecamatan itu ikut terhapus.</p>
      </div>

      <div class="panel">
        <h3>Hapus kriteria</h3>
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="hapus_kriteria">
          <select name="kriteria_id">
            <?php foreach ($kriteria as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['kode']) ?>, <?= e($c['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="danger">Hapus</button>
        </form>
      </div>
    </div>
  </div>
</details>
<?php endif; ?>

<?php endif; ?>
<?php page_foot(); ?>
