<?php
declare(strict_types=1);

session_start();

// Ganti sesuai konfigurasi XAMPP. Password root XAMPP default: kosong.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'spk_lakaprior';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Angka format Indonesia: 1.234,5678 */
function num(float $v, int $dec = 4): string
{
    return number_format($v, $dec, ',', '.');
}

/** Persentase untuk atribut CSS: wajib titik desimal, koma membuat deklarasinya tidak sah. */
function pct(float $v): string
{
    return number_format($v, 1, '.', '');
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (user() === null) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('CSRF token tidak cocok. Muat ulang halaman.');
    }
}

/** flash('pesan') untuk menulis, flash() untuk membaca sekali. */
function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $out = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $out;
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

/** Angka dari input form (terima koma maupun titik sebagai desimal). */
function post_num(string $key): float
{
    $raw = str_replace(',', '.', post($key));
    if ($raw === '' || !is_numeric($raw)) {
        throw new InvalidArgumentException("Nilai '$key' harus angka.");
    }
    return (float) $raw;
}
