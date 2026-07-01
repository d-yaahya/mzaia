<?php
require __DIR__ . '/app/bootstrap.php';

if (current_employee()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = trim($_POST['code'] ?? '');

    if (!has_installed()) {
        redirect('install.php');
    }

    $employees = db()->query('SELECT * FROM employees WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

    foreach ($employees as $employee) {
        if (password_verify($code, $employee['code_hash'])) {
            $_SESSION['employee'] = [
                'id' => (int) $employee['id'],
                'name' => $employee['name'],
            ];
            redirect('index.php');
        }
    }

    $error = 'رمز الدخول غير صحيح.';
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دخول - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <img class="login-logo" src="assets/logo.jpg" alt="شعار المكتب">
        <h1><?= e(APP_NAME) ?></h1>
        <p class="muted">أدخل رمزك للدخول باسمك تلقائيًا.</p>

        <?php if (!has_installed()): ?>
            <div class="alert warning">لم يتم تثبيت النظام بعد.</div>
            <a class="btn primary full" href="install.php">تثبيت النظام</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <label>
                    رمز الدخول
                    <input name="code" type="password" inputmode="numeric" autocomplete="current-password" autofocus required>
                </label>
                <button class="btn primary full" type="submit">دخول</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
