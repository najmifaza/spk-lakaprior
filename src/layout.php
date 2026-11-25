<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Satu sumber daftar tab, dipakai oleh menu dan oleh index.php. */
function nav_tabs(): array
{
    return [
        'keputusan' => 'Hasil',
        'perhitungan' => 'Cara hitung',
        'data' => 'Data dan bobot',
    ];
}

function page_head(string $title, string $activeTab = ''): void
{
    $u = user();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | LAKAPRIOR</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">LAKAPRIOR<span>Prioritas kecamatan rawan kecelakaan, Kabupaten Sukoharjo</span></a>
  <?php if ($u !== null): ?>
  <nav class="tabs">
    <?php foreach (nav_tabs() as $key => $label): ?>
      <a class="tab<?= $key === $activeTab ? ' on' : '' ?>" href="index.php?tab=<?= e($key) ?>"<?= $key === $activeTab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <a class="tab" href="login.php?action=logout">Keluar (<?= e($u['username']) ?>)</a>
  </nav>
  <?php endif; ?>
</header>
<main>
<?php if ($m = flash()): ?>
  <p class="flash"><?= e($m) ?></p>
<?php endif;
}

function page_foot(): void
{
    ?>
</main>
<footer>
  <p class="hint">Tugas project mata kuliah Sistem Pendukung Keputusan. Data kecelakaan, jumlah penduduk, dan luas wilayah bersumber dari portal Satu Data Kabupaten Sukoharjo.</p>
</footer>
</body>
</html>
<?php
}

function err(string $message): void
{
    ?>
  <p class="flash err"><?= e($message) ?></p>
<?php
}
