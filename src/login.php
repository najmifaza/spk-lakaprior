<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

if (($_GET['action'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $password = post('password');

    try {
        $st = db()->prepare('SELECT * FROM users WHERE username = :u');
        $st->execute(['u' => $username]);
        $row = $st->fetch();
        // Hash seed.sql memakai SHA2-256 MySQL; user baru sebaiknya dibuat dengan password_hash() PHP.
        $ok = $row !== false && (
            password_verify($password, $row['password_hash'])
            || hash_equals($row['password_hash'], hash('sha256', $password))
        );
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $row['id'], 'username' => $row['username'], 'role' => $row['role']];
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau password salah.';
    } catch (Throwable $ex) {
        $error = 'Gagal terhubung ke database: ' . $ex->getMessage();
    }
}

page_head('Masuk');
?>
<div class="login">
  <h1>Masuk LAKAPRIOR</h1>
  <p class="lead">Sistem Pendukung Keputusan Penentuan Prioritas Kecamatan Rawan Kecelakaan Lalu Lintas di Kabupaten Sukoharjo.</p>
  <?php if ($error !== null) { err($error); } ?>
  <form method="post" class="panel stack">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Username
      <input name="username" autocomplete="username" required>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit">Masuk</button>
    <span class="hint">Akun awal: admin / admin123. Ganti sebelum dipakai orang lain.</span>
  </form>
</div>
<?php page_foot(); ?>
