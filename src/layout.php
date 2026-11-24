<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function page_head(string $title): void
{
    $u = user();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — LAKAPRIOR</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">LAKAPRIOR<span>SPK Prioritas Kecamatan Rawan Kecelakaan — Kabupaten Sukoharjo</span></div>
  <?php if ($u !== null): ?>
  <nav>
    <a href="index.php">Dashboard</a>
    <a href="hitung.php">Perhitungan</a>
    <a href="data.php">Data</a>
    <a href="login.php?action=logout" class="logout">Keluar (<?= e($u['username']) ?>)</a>
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
<footer>Tugas project mata kuliah Sistem Pendukung Keputusan — metode TOPSIS (utama) dan SAW (pembanding).</footer>
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
