<?php
require __DIR__ . '/app/bootstrap.php';

$installed = has_installed();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $employees = [
        ['name' => trim($_POST['employee_1_name'] ?? ''), 'code' => trim($_POST['employee_1_code'] ?? '')],
        ['name' => trim($_POST['employee_2_name'] ?? ''), 'code' => trim($_POST['employee_2_code'] ?? '')],
        ['name' => trim($_POST['employee_3_name'] ?? ''), 'code' => trim($_POST['employee_3_code'] ?? '')],
    ];

    foreach ($employees as $employee) {
        if ($employee['name'] === '' || $employee['code'] === '') {
            $errors[] = 'أدخل اسم ورمز كل موظف.';
            break;
        }

        if (!preg_match('/^[0-9]{4,8}$/', $employee['code'])) {
            $errors[] = 'رمز الدخول يجب أن يكون أرقامًا فقط من 4 إلى 8 خانات.';
            break;
        }
    }

    if (!$errors) {
        $pdo = db();

        $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            code_hash TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('income', 'expense')),
            amount REAL NOT NULL CHECK(amount > 0),
            category TEXT NOT NULL,
            description TEXT,
            transaction_date TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT,
            FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )");

        $count = (int) $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO employees (name, code_hash) VALUES (:name, :code_hash)');
            foreach ($employees as $employee) {
                $stmt->execute([
                    ':name' => $employee['name'],
                    ':code_hash' => password_hash($employee['code'], PASSWORD_DEFAULT),
                ]);
            }
        }

        $success = true;
        $installed = true;
    }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت النظام - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <main class="auth-card wide">
        <h1>تثبيت النظام</h1>
        <p class="muted">أدخل أسماء الموظفين ورموز الدخول. سيتم حفظ الرموز مشفرة داخل قاعدة البيانات.</p>

        <?php if ($success): ?>
            <div class="alert success">تم تثبيت النظام بنجاح. احذف ملف <b>install.php</b> الآن، ثم ادخل من صفحة الدخول.</div>
            <a class="btn primary" href="login.php">الدخول للنظام</a>
        <?php else: ?>
            <?php if ($installed): ?>
                <div class="alert warning">يبدو أن قاعدة البيانات موجودة مسبقًا. إذا كان النظام يعمل، احذف ملف install.php.</div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert danger"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="form-grid">
                <?= csrf_field() ?>

                <label>
                    اسم الموظف الأول
                    <input name="employee_1_name" value="موسى" required>
                </label>
                <label>
                    رمز الموظف الأول
                    <input name="employee_1_code" inputmode="numeric" required>
                </label>

                <label>
                    اسم الموظف الثاني
                    <input name="employee_2_name" value="يحيى" required>
                </label>
                <label>
                    رمز الموظف الثاني
                    <input name="employee_2_code" inputmode="numeric" required>
                </label>

                <label>
                    اسم الموظف الثالث
                    <input name="employee_3_name" value="كريم" required>
                </label>
                <label>
                    رمز الموظف الثالث
                    <input name="employee_3_code" inputmode="numeric" required>
                </label>

                <button class="btn primary full" type="submit">إنشاء قاعدة البيانات</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
