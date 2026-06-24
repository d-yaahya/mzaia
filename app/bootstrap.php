<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Riyadh');

const APP_NAME = 'حسابات مزايا المجتمع التجارية';
const DB_PATH = __DIR__ . '/../storage/mzaia.sqlite';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $storageDir = dirname(DB_PATH);
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|null $value): string
{
    return number_format((float) $value, 2) . ' ريال';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('انتهت صلاحية الطلب، ارجع للصفحة وحاول مرة أخرى.');
    }
}

function current_employee(): ?array
{
    return $_SESSION['employee'] ?? null;
}

function require_login(): void
{
    if (!current_employee()) {
        header('Location: login.php');
        exit;
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function has_installed(): bool
{
    return file_exists(DB_PATH);
}
