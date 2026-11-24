<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/lib/data.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
                flash('Nilai alternatif tersimpan.');
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
                flash('Bobot dan tipe kriteria tersimpan.');
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
                $st = db()->prepare('INSERT INTO penilaian (kecamatan_id, kriteria_id, nilai)
                                     SELECT :id, id, 0 FROM kriteria');
                $st->execute(['id' => $id]);
                flash("Alternatif '$nama' ditambahkan. Isi nilainya di tabel nilai alternatif.");
                break;

            case 'hapus_alternatif':
                $id = (int) post('kecamatan_id');
                db()->prepare('DELETE FROM kecamatan WHERE id = :id')->execute(['id' => $id]);
                flash('Alternatif dihapus.');
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
                flash("Kriteria '$kode' ditambahkan dengan nilai 0 untuk semua alternatif.");
                break;

            case 'hapus_kriteria':
                db()->prepare('DELETE FROM kriteria WHERE id = :id')->execute(['id' => (int) post('kriteria_id')]);
                flash('Kriteria dihapus.');
                break;
        }
    } catch (Throwable $ex) {
        flash('ERR: ' . $ex->getMessage());
    }
    header('Location: data.php');
    exit;
}

[$alternatives, $criteria, $values] = load_data();
$bobotValid = check_criteria($criteria);

page_head('Data');
?>
<h1>Data alternatif dan kriteria</h1>
<p class="lead">Ubah di sini, lalu buka halaman Perhitungan. Semua perubahan langsung dipakai saat hitung ulang.</p>

<h2>Tabel nilai alternatif <span class="sub">x<sub>ij</sub> — satu baris per kecamatan</span></h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="simpan_nilai">
  <div class="scroll">
  <table>
    <thead>
      <tr><th class="l">Kecamatan</th><?php foreach ($criteria as $c): ?><th><?= e($c['kode']) ?><br><span class="hint"><?= e($c['satuan']) ?></span></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($alternatives as $alt): ?>
      <tr>
        <td class="l"><?= e($alt['nama']) ?></td>
        <?php foreach ($criteria as $c): $key = $alt['id'] . '|' . $c['id']; ?>
          <td><input type="number" step="any" name="nilai[<?= (int) $alt['id'] ?>][<?= (int) $c['id'] ?>]"
                     value="<?= e((string) ($values[$key] ?? '0')) ?>"></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <button type="submit">Simpan semua nilai</button>
</form>

<h2>Kriteria dan bobot <span class="sub">total sekarang <?= num($bobotValid['total'], 4) ?></span></h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="simpan_bobot">
  <div class="scroll">
  <table>
    <thead><tr><th class="l">Kode</th><th class="l">Nama</th><th class="l">Satuan</th><th class="l">Tipe</th><th>Bobot</th></tr></thead>
    <tbody>
    <?php foreach ($criteria as $c): ?>
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
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td class="l" colspan="4">Total</td><td><?= num($bobotValid['total'], 4) ?></td></tr></tfoot>
  </table>
  </div>
  <button type="submit">Simpan bobot</button>
</form>

<h2>Tambah / hapus</h2>
<div class="grid">
  <div class="panel">
    <h3>Tambah alternatif (kecamatan)</h3>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="tambah_alternatif">
      <label>Nama kecamatan <input name="nama" required></label>
      <label>Luas wilayah (km2) <input type="number" step="any" name="luas_km2" required></label>
      <label>Kepadatan penduduk (jiwa/km2) <input type="number" step="any" name="kepadatan" required></label>
      <label>Jumlah penduduk (jiwa) <input type="number" step="any" name="penduduk" required></label>
      <button type="submit">Tambah alternatif</button>
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
          <option value="benefit">benefit (besar = prioritas tinggi)</option>
          <option value="cost">cost (kecil = prioritas tinggi)</option>
        </select>
      </label>
      <label>Bobot <input type="number" step="any" name="bobot" value="0.1" required></label>
      <button type="submit">Tambah kriteria</button>
    </form>
  </div>

  <div class="panel">
    <h3>Hapus alternatif</h3>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="hapus_alternatif">
      <select name="kecamatan_id">
        <?php foreach ($alternatives as $alt): ?>
          <option value="<?= (int) $alt['id'] ?>"><?= e($alt['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="danger">Hapus</button>
    </form>
    <p class="hint">Nilai alternatif ikut terhapus (foreign key ON DELETE CASCADE).</p>
  </div>

  <div class="panel">
    <h3>Hapus kriteria</h3>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="hapus_kriteria">
      <select name="kriteria_id">
        <?php foreach ($criteria as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= e($c['kode']) ?> — <?= e($c['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="danger">Hapus</button>
    </form>
  </div>
</div>
<?php page_foot(); ?>
