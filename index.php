<?php
require __DIR__ . '/app/bootstrap.php';

if (!has_installed()) {
    redirect('install.php');
}

require_login();

$pdo = db();
$employee = current_employee();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

function totals_between(string $from, string $to): array
{
    $stmt = db()->prepare("SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM transactions
        WHERE transaction_date BETWEEN :from AND :to");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $row = $stmt->fetch();

    return [
        'income' => (float)$row['income'],
        'expense' => (float)$row['expense'],
        'net' => (float)$row['income'] - (float)$row['expense'],
    ];
}

$todayTotals = totals_between($today, $today);
$monthTotals = totals_between($monthStart, $monthEnd);

$employees = $pdo->query('SELECT id, name FROM employees WHERE is_active = 1 ORDER BY id ASC')->fetchAll();

$employeeSummaryStmt = $pdo->prepare("SELECT
    e.id,
    e.name,
    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense
    FROM employees e
    LEFT JOIN transactions t ON t.employee_id = e.id
        AND t.transaction_date BETWEEN :from AND :to
    WHERE e.is_active = 1
    GROUP BY e.id, e.name
    ORDER BY e.id ASC");
$employeeSummaryStmt->execute([':from' => $monthStart, ':to' => $monthEnd]);
$employeeSummary = $employeeSummaryStmt->fetchAll();

$period = $_GET['period'] ?? 'month';
$typeFilter = $_GET['type'] ?? 'all';
$employeeFilter = (int)($_GET['employee_id'] ?? 0);

$where = [];
$params = [];

if ($period === 'today') {
    $where[] = 't.transaction_date = :today';
    $params[':today'] = $today;
} elseif ($period === 'month') {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $monthStart;
    $params[':to'] = $monthEnd;
}

if (in_array($typeFilter, ['income', 'expense'], true)) {
    $where[] = 't.type = :type';
    $params[':type'] = $typeFilter;
}

if ($employeeFilter > 0) {
    $where[] = 't.employee_id = :employee_id';
    $params[':employee_id'] = $employeeFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT t.*, e.name AS employee_name
    FROM transactions t
    JOIN employees e ON e.id = t.employee_id
    $whereSql
    ORDER BY t.transaction_date DESC, t.id DESC
    LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$editTransaction = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = :id AND employee_id = :employee_id');
    $stmt->execute([
        ':id' => (int)$_GET['edit'],
        ':employee_id' => $employee['id'],
    ]);
    $editTransaction = $stmt->fetch() ?: null;
}

$categoriesIncome = ['تسجيل تابي', 'تسجيل تمارا', 'خدمة حكومية', 'تصميم إعلان', 'استشارة', 'عمولة', 'أخرى'];
$categoriesExpense = ['إيجار', 'إنترنت', 'إعلان ممول', 'طباعة', 'بنزين', 'أدوات مكتب', 'أخرى'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>مرحبًا <?= e($employee['name']) ?>، الجميع يشاهد الأرقام، والتعديل والحذف لعملياتك فقط.</p>
    </div>
    <a class="btn light" href="logout.php">خروج</a>
</header>

<main class="container">
    <section class="cards">
        <article class="card">
            <span>دخل اليوم</span>
            <strong><?= money($todayTotals['income']) ?></strong>
        </article>
        <article class="card">
            <span>مصروف اليوم</span>
            <strong><?= money($todayTotals['expense']) ?></strong>
        </article>
        <article class="card">
            <span>صافي اليوم</span>
            <strong><?= money($todayTotals['net']) ?></strong>
        </article>
        <article class="card">
            <span>دخل الشهر</span>
            <strong><?= money($monthTotals['income']) ?></strong>
        </article>
        <article class="card">
            <span>مصروف الشهر</span>
            <strong><?= money($monthTotals['expense']) ?></strong>
        </article>
        <article class="card highlight">
            <span>صافي الشهر</span>
            <strong><?= money($monthTotals['net']) ?></strong>
        </article>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h2><?= $editTransaction ? 'تعديل عملية' : 'إضافة دخل أو مصروف' ?></h2>
            <?php if ($editTransaction): ?>
                <a class="btn light" href="index.php">إلغاء التعديل</a>
            <?php endif; ?>
        </div>

        <form class="entry-form" action="save_transaction.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($editTransaction['id'] ?? '') ?>">

            <label>
                النوع
                <select name="type" required>
                    <?php $currentType = $editTransaction['type'] ?? 'income'; ?>
                    <option value="income" <?= $currentType === 'income' ? 'selected' : '' ?>>دخل</option>
                    <option value="expense" <?= $currentType === 'expense' ? 'selected' : '' ?>>مصروف</option>
                </select>
            </label>

            <label>
                المبلغ
                <input name="amount" type="number" min="0.01" step="0.01" value="<?= e($editTransaction['amount'] ?? '') ?>" required>
            </label>

            <label>
                التصنيف
                <input name="category" list="categories" value="<?= e($editTransaction['category'] ?? '') ?>" required>
                <datalist id="categories">
                    <?php foreach (array_merge($categoriesIncome, $categoriesExpense) as $category): ?>
                        <option value="<?= e($category) ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label>
                التاريخ
                <input name="transaction_date" type="date" value="<?= e($editTransaction['transaction_date'] ?? $today) ?>" required>
            </label>

            <label class="span-2">
                الوصف
                <input name="description" value="<?= e($editTransaction['description'] ?? '') ?>" placeholder="مثال: تسجيل محل جوالات في تمارا">
            </label>

            <button class="btn primary" type="submit"><?= $editTransaction ? 'حفظ التعديل' : 'حفظ العملية' ?></button>
        </form>
    </section>

    <section class="panel">
        <h2>ملخص الموظفين لهذا الشهر</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>الدخل</th>
                        <th>المصروف</th>
                        <th>الصافي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employeeSummary as $row): ?>
                        <?php $net = (float)$row['income'] - (float)$row['expense']; ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="income"><?= money($row['income']) ?></td>
                            <td class="expense"><?= money($row['expense']) ?></td>
                            <td class="<?= $net >= 0 ? 'income' : 'expense' ?>"><?= money($net) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h2>العمليات</h2>
            <form class="filters" method="get">
                <select name="period">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>اليوم</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>هذا الشهر</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>كل العمليات</option>
                </select>
                <select name="employee_id">
                    <option value="0">كل الموظفين</option>
                    <?php foreach ($employees as $row): ?>
                        <option value="<?= e((string)$row['id']) ?>" <?= $employeeFilter === (int)$row['id'] ? 'selected' : '' ?>>
                            <?= e($row['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>دخل ومصروف</option>
                    <option value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>دخل فقط</option>
                    <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>مصروف فقط</option>
                </select>
                <button class="btn light" type="submit">تصفية</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الموظف</th>
                        <th>النوع</th>
                        <th>التصنيف</th>
                        <th>الوصف</th>
                        <th>المبلغ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$transactions): ?>
                        <tr>
                            <td colspan="7" class="empty">لا توجد عمليات حتى الآن.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($transactions as $transaction): ?>
                        <?php $isOwner = (int)$transaction['employee_id'] === (int)$employee['id']; ?>
                        <tr>
                            <td><?= e($transaction['transaction_date']) ?></td>
                            <td><?= e($transaction['employee_name']) ?></td>
                            <td>
                                <span class="badge <?= $transaction['type'] === 'income' ? 'income-bg' : 'expense-bg' ?>">
                                    <?= $transaction['type'] === 'income' ? 'دخل' : 'مصروف' ?>
                                </span>
                            </td>
                            <td><?= e($transaction['category']) ?></td>
                            <td><?= e($transaction['description']) ?></td>
                            <td class="<?= $transaction['type'] === 'income' ? 'income' : 'expense' ?>">
                                <?= money($transaction['amount']) ?>
                            </td>
                            <td>
                                <?php if ($isOwner): ?>
                                    <a class="btn small light" href="index.php?edit=<?= e((string)$transaction['id']) ?>">تعديل</a>
                                    <form action="delete_transaction.php" method="post" class="inline-form" onsubmit="return confirm('حذف العملية؟');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string)$transaction['id']) ?>">
                                        <button class="btn small danger" type="submit">حذف</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">عرض فقط</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
