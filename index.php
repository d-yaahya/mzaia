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
$previousMonthStart = date('Y-m-01', strtotime('first day of previous month'));
$previousMonthEnd = date('Y-m-t', strtotime('last day of previous month'));

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
$previousMonthTotals = totals_between($previousMonthStart, $previousMonthEnd);

$employees = $pdo->query('SELECT id, name FROM employees WHERE is_active = 1 ORDER BY id ASC')->fetchAll();
$employeeSummaryPeriod = $_GET['employee_summary_period'] ?? 'month';
$employeeSummaryFrom = $_GET['employee_summary_from'] ?? '';
$employeeSummaryTo = $_GET['employee_summary_to'] ?? '';

if (!function_exists('mzaia_employee_summary_period_range')) {
    function mzaia_employee_summary_period_range($period, $from = '', $to = '') {
        $allowed = ['month', 'previous_month', 'last_3_months', 'year', 'all', 'custom'];

        if (!in_array($period, $allowed, true)) {
            $period = 'month';
        }

        if ($period === 'custom') {
            $fromValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
            $toValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

            if ($fromValid && $toValid) {
                if ($from > $to) {
                    $tmp = $from;
                    $from = $to;
                    $to = $tmp;
                }

                return [$from, $to, 'مدة مخصصة من ' . $from . ' إلى ' . $to, 'custom'];
            }

            return [date('Y-m-01'), date('Y-m-t'), 'هذا الشهر', 'month'];
        }

        switch ($period) {
            case 'previous_month':
                return [
                    date('Y-m-01', strtotime('first day of previous month')),
                    date('Y-m-t', strtotime('last day of previous month')),
                    'الشهر السابق',
                    'previous_month'
                ];

            case 'last_3_months':
                return [
                    date('Y-m-01', strtotime('-2 months')),
                    date('Y-m-t'),
                    'آخر 3 أشهر',
                    'last_3_months'
                ];

            case 'year':
                return [date('Y-01-01'), date('Y-12-31'), 'هذا العام', 'year'];

            case 'all':
                return [null, null, 'كل المدة', 'all'];

            case 'month':
            default:
                return [date('Y-m-01'), date('Y-m-t'), 'هذا الشهر', 'month'];
        }
    }
}

[$employeeSummaryStart, $employeeSummaryEnd, $employeeSummaryLabel, $employeeSummaryPeriod] = mzaia_employee_summary_period_range(
    $employeeSummaryPeriod,
    $employeeSummaryFrom,
    $employeeSummaryTo
);


$employeeSummaryJoin = 't.employee_id = e.id';
$employeeSummaryParams = [];

if ($employeeSummaryStart !== null && $employeeSummaryEnd !== null) {
    $employeeSummaryJoin .= ' AND t.transaction_date BETWEEN :employee_summary_from AND :employee_summary_to';
    $employeeSummaryParams[':employee_summary_from'] = $employeeSummaryStart;
    $employeeSummaryParams[':employee_summary_to'] = $employeeSummaryEnd;
}

$employeeSummaryJoin = 't.employee_id = e.id';
$employeeSummaryParams = [];

if ($employeeSummaryStart !== null && $employeeSummaryEnd !== null) {
    $employeeSummaryJoin .= ' AND t.transaction_date BETWEEN :employee_summary_from AND :employee_summary_to';
    $employeeSummaryParams[':employee_summary_from'] = $employeeSummaryStart;
    $employeeSummaryParams[':employee_summary_to'] = $employeeSummaryEnd;
}

$employeeSummaryJoin = 't.employee_id = e.id';
$employeeSummaryParams = [];

if ($employeeSummaryStart !== null && $employeeSummaryEnd !== null) {
    $employeeSummaryJoin .= ' AND t.transaction_date BETWEEN :employee_summary_from AND :employee_summary_to';
    $employeeSummaryParams[':employee_summary_from'] = $employeeSummaryStart;
    $employeeSummaryParams[':employee_summary_to'] = $employeeSummaryEnd;
}

$employeeSummaryStmt = $pdo->prepare("SELECT
    e.id,
    e.name,
    COUNT(t.id) AS operations_count,
    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS expense
    FROM employees e
    LEFT JOIN transactions t ON {$employeeSummaryJoin}
    WHERE e.is_active = 1
    GROUP BY e.id, e.name
    ORDER BY e.id ASC");
$employeeSummaryStmt->execute($employeeSummaryParams);
$employeeSummary = $employeeSummaryStmt->fetchAll();

$period = $_GET['period'] ?? 'month';
$typeFilter = $_GET['type'] ?? 'all';
$employeeFilter = (int)($_GET['employee_id'] ?? 0);
$movementAccountFilter = $_GET['movement_account'] ?? 'all';

$where = [];
$params = [];

if ($period === 'today') {
    $where[] = 't.transaction_date = :today';
    $params[':today'] = $today;
} elseif ($period === 'month') {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $monthStart;
    $params[':to'] = $monthEnd;
} elseif ($period === 'previous_month') {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $previousMonthStart;
    $params[':to'] = $previousMonthEnd;
}

if (in_array($typeFilter, ['income', 'expense'], true)) {
    $where[] = 't.type = :type';
    $params[':type'] = $typeFilter;
}

if ($employeeFilter > 0) {
    $where[] = 't.employee_id = :employee_id';
    $params[':employee_id'] = $employeeFilter;
}

if ($movementAccountFilter !== 'all') {
    $where[] = "COALESCE(NULLIF(t.movement_account, ''), 'غير محدد') = :movement_account_filter";
    $params[':movement_account_filter'] = $movementAccountFilter;
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
    <link rel="stylesheet" href="assets/style.css?v=movement-account-filter-v2">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <img class="brand-logo" src="assets/logo.jpg" alt="شعار المكتب">
        <div>
            <h1><?= e(APP_NAME) ?></h1>
            <p>مرحبًا <?= e($employee['name']) ?>، الجميع يشاهد الأرقام، والتعديل والحذف لعملياتك فقط.</p>
        </div>
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
    <section class="previous-month-summary previous-month-summary-v1">
        <div>
            <h2>نبذة الشهر السابق</h2>
            <p>ملخص سريع لدخل ومصروف وصافي الشهر الماضي</p>
        </div>
        <div class="previous-month-cards">
            <article>
                <span>دخل الشهر السابق</span>
                <strong class="income"><?= money($previousMonthTotals['income']) ?></strong>
            </article>
            <article>
                <span>مصروف الشهر السابق</span>
                <strong class="expense"><?= money($previousMonthTotals['expense']) ?></strong>
            </article>
            <article>
                <span>صافي الشهر السابق</span>
                <strong class="<?= $previousMonthTotals['net'] >= 0 ? 'income' : 'expense' ?>"><?= money($previousMonthTotals['net']) ?></strong>
            </article>
        </div>
    </section>
<?php include __DIR__ . '/office_summary_widget.php'; ?>
<?php include __DIR__ . '/movement_accounts_summary_widget.php'; ?>


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

            <div class="type-toggle">
    <?php $currentType = $editTransaction['type'] ?? 'income'; ?>
    <span class="field-title">نوع العملية</span>
    <div class="type-options">
        <label class="type-option income-option">
            <input type="radio" name="type" value="income" <?= $currentType === 'income' ? 'checked' : '' ?> required>
            <span>دخل</span>
        </label>
        <label class="type-option expense-option">
            <input type="radio" name="type" value="expense" <?= $currentType === 'expense' ? 'checked' : '' ?> required>
            <span>مصروف</span>
        </label>
    </div>
</div>

            <div class="method-toggle payment-method-toggle-v1">
                <?php $currentPaymentMethod = $editTransaction['payment_method'] ?? 'كاش'; ?>
                <span class="field-title">طريقة الدفع / الصرف</span>
                <div class="method-options" id="paymentMethodOptions">
                    <label class="method-option" data-method-for="all">
                        <input type="radio" name="payment_method" value="كاش" <?= $currentPaymentMethod === 'كاش' ? 'checked' : '' ?> required>
                        <span>كاش</span>
                    </label>
                    <label class="method-option" data-method-for="income">
                        <input type="radio" name="payment_method" value="تحويل" <?= $currentPaymentMethod === 'تحويل' ? 'checked' : '' ?>>
                        <span>تحويل</span>
                    </label>
                    <label class="method-option" data-method-for="income">
                        <input type="radio" name="payment_method" value="شبكة" <?= $currentPaymentMethod === 'شبكة' ? 'checked' : '' ?>>
                        <span>شبكة</span>
                    </label>
                    <label class="method-option" data-method-for="expense">
                        <input type="radio" name="payment_method" value="من الحساب" <?= $currentPaymentMethod === 'من الحساب' ? 'checked' : '' ?>>
                        <span>من الحساب</span>
                    </label>
                </div>
            </div>

            <div class="movement-account-box">
                <?php $selectedMovementAccount = $editTransaction['movement_account'] ?? ''; ?>
                <span class="field-title">حساب الحركة</span>
                <select name="movement_account" class="movement-account-select" required>
                    <option value="">اختر حساب الحركة</option>
                    <option value="حساب المكتب" <?= $selectedMovementAccount === 'حساب المكتب' ? 'selected' : '' ?>>حساب المكتب</option>
                    <option value="حساب يحيى الخاص" <?= $selectedMovementAccount === 'حساب يحيى الخاص' ? 'selected' : '' ?>>حساب يحيى الخاص</option>
                    <option value="حساب موسى" <?= $selectedMovementAccount === 'حساب موسى' ? 'selected' : '' ?>>حساب موسى</option>
                    <option value="حساب كريم" <?= $selectedMovementAccount === 'حساب كريم' ? 'selected' : '' ?>>حساب كريم</option>
                    <option value="صندوق الكاش" <?= $selectedMovementAccount === 'صندوق الكاش' ? 'selected' : '' ?>>صندوق الكاش</option>
                    <option value="أخرى" <?= $selectedMovementAccount === 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                </select>
                <small>حدد أين دخل أو خرج المبلغ، مثل حساب المكتب أو حسابك الخاص.</small>
            </div>
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

            <label class="date-field">
    <span class="field-title">التاريخ</span>
    <div class="date-card">
        <strong id="datePreview"><?= e($editTransaction['transaction_date'] ?? $today) ?></strong>
        <small>اضغط لتغيير التاريخ</small>
        <input id="transactionDate" name="transaction_date" type="date" value="<?= e($editTransaction['transaction_date'] ?? $today) ?>" required>
    </div>
</label>

            <label class="span-2">
                الوصف
                <input name="description" value="<?= e($editTransaction['description'] ?? '') ?>" placeholder="مثال: تسجيل محل جوالات في تمارا">
            </label>

            <button class="btn primary" type="submit"><?= $editTransaction ? 'حفظ التعديل' : 'حفظ العملية' ?></button>
        </form>
    </section>

    <section class="panel employee-summary-panel">
        <div class="employee-summary-head">
            <div>
                <h2>ملخص الموظفين - <?= e($employeeSummaryLabel) ?></h2>
                <p>يعرض دخل ومصروف وصافي كل موظف حسب المدة المحددة.</p>
            </div>

            <form method="get" class="employee-summary-form">
                <input type="hidden" name="period" value="<?= e($period) ?>">
                <input type="hidden" name="employee_id" value="<?= e((string)$employeeFilter) ?>">
                <input type="hidden" name="type" value="<?= e($typeFilter) ?>">

                <?php foreach (['office_period', 'office_from', 'office_to'] as $keepParam): ?>
                    <?php if (isset($_GET[$keepParam])): ?>
                        <input type="hidden" name="<?= e($keepParam) ?>" value="<?= e($_GET[$keepParam]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <label>
                    مدة الملخص
                    <select name="employee_summary_period" onchange="mzaiaToggleEmployeeSummaryRange(this)">
                        <option value="month" <?= $employeeSummaryPeriod === 'month' ? 'selected' : '' ?>>هذا الشهر</option>
                        <option value="previous_month" <?= $employeeSummaryPeriod === 'previous_month' ? 'selected' : '' ?>>الشهر السابق</option>
                        <option value="last_3_months" <?= $employeeSummaryPeriod === 'last_3_months' ? 'selected' : '' ?>>آخر 3 أشهر</option>
                        <option value="year" <?= $employeeSummaryPeriod === 'year' ? 'selected' : '' ?>>هذا العام</option>
                        <option value="all" <?= $employeeSummaryPeriod === 'all' ? 'selected' : '' ?>>كل المدة</option>
                        <option value="custom" <?= $employeeSummaryPeriod === 'custom' ? 'selected' : '' ?>>مدة مخصصة</option>
                    </select>
                </label>

                <div class="employee-summary-custom-range <?= $employeeSummaryPeriod === 'custom' ? 'show' : '' ?>">
                    <label>من <input type="date" name="employee_summary_from" value="<?= e($employeeSummaryFrom) ?>"></label>
                    <label>إلى <input type="date" name="employee_summary_to" value="<?= e($employeeSummaryTo) ?>"></label>
                    <button type="submit">تطبيق</button>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>الدخل</th>
                        <th>المصروف</th>
                        <th>الصافي</th>
                        <th>عدد العمليات</th>
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
                            <td><?= e((string)$row['operations_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <script>
    function mzaiaToggleEmployeeSummaryRange(select) {
        const form = select.closest('form');
        const customRange = form ? form.querySelector('.employee-summary-custom-range') : null;

        if (!customRange) return;

        if (select.value === 'custom') {
            customRange.classList.add('show');
            const firstDate = customRange.querySelector('input[type="date"]');
            if (firstDate) firstDate.focus();
        } else {
            customRange.classList.remove('show');
            form.submit();
        }
    }
    </script>

    <section class="panel">
        <div class="panel-title">
            <h2>العمليات</h2>
            <a class="btn success" href="export_excel.php?period=all&employee_id=0&type=all&movement_account=all">تصدير Excel كامل</a>
            <a class="btn light" href="export_excel.php?period=<?= e($period) ?>&employee_id=<?= e((string)$employeeFilter) ?>&type=<?= e($typeFilter) ?>&movement_account=<?= e($movementAccountFilter) ?>">تصدير حسب الفلتر</a>
                <a class="secondary-button" href="reports.php">تقرير تفصيلي</a>
            <form class="filters" method="get">
                <select name="period">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>اليوم</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>هذا الشهر</option>
                    <option value="previous_month" <?= $period === 'previous_month' ? 'selected' : '' ?>>الشهر السابق</option>
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
                <select name="movement_account">
                    <option value="all" <?= $movementAccountFilter === 'all' ? 'selected' : '' ?>>كل الحسابات</option>
                    <option value="حساب المكتب" <?= $movementAccountFilter === 'حساب المكتب' ? 'selected' : '' ?>>حساب المكتب</option>
                    <option value="حساب يحيى الخاص" <?= $movementAccountFilter === 'حساب يحيى الخاص' ? 'selected' : '' ?>>حساب يحيى الخاص</option>
                    <option value="حساب موسى" <?= $movementAccountFilter === 'حساب موسى' ? 'selected' : '' ?>>حساب موسى</option>
                    <option value="حساب كريم" <?= $movementAccountFilter === 'حساب كريم' ? 'selected' : '' ?>>حساب كريم</option>
                    <option value="صندوق الكاش" <?= $movementAccountFilter === 'صندوق الكاش' ? 'selected' : '' ?>>صندوق الكاش</option>
                    <option value="أخرى" <?= $movementAccountFilter === 'أخرى' ? 'selected' : '' ?>>أخرى</option>
                    <option value="غير محدد" <?= $movementAccountFilter === 'غير محدد' ? 'selected' : '' ?>>غير محدد</option>
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
                        <th>طريقة الدفع / الصرف</th>
                        <th>حساب الحركة</th>
                        <th>التصنيف</th>
                        <th>الوصف</th>
                        <th>المبلغ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$transactions): ?>
                        <tr>
                            <td colspan="9" class="empty">لا توجد عمليات حتى الآن.</td>
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
                            <td><?= e($transaction['payment_method'] ?: '-') ?></td>
                            <td><?= e($transaction['movement_account'] ?: 'غير محدد') ?></td>
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
<script>
(function formatOfficeDateV2() {
    const input = document.getElementById('transactionDate');
    const preview = document.getElementById('datePreview');

    if (!input || !preview) return;

    function updateDatePreview() {
        if (!input.value) return;

        const parts = input.value.split('-').map(Number);
        const date = new Date(parts[0], parts[1] - 1, parts[2]);

        const formatted = new Intl.DateTimeFormat('ar-SA-u-ca-gregory', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(date);

        preview.textContent = formatted;
    }

    input.addEventListener('change', updateDatePreview);
    updateDatePreview();
})();
</script>
<script>
(function paymentMethodToggleV1() {
    const typeInputs = document.querySelectorAll('input[name="type"]');
    const methodOptions = document.querySelectorAll('[data-method-for]');
    const methodInputs = document.querySelectorAll('input[name="payment_method"]');

    function currentType() {
        const checked = document.querySelector('input[name="type"]:checked');
        return checked ? checked.value : 'income';
    }

    function updateMethods() {
        const type = currentType();

        methodOptions.forEach(option => {
            const target = option.getAttribute('data-method-for');
            const shouldShow = target === 'all' || target === type;
            option.style.display = shouldShow ? '' : 'none';
        });

        const checked = document.querySelector('input[name="payment_method"]:checked');
        if (checked) {
            const option = checked.closest('[data-method-for]');
            const target = option ? option.getAttribute('data-method-for') : 'all';
            if (!(target === 'all' || target === type)) {
                const cash = document.querySelector('input[name="payment_method"][value="كاش"]');
                if (cash) cash.checked = true;
            }
        }
    }

    typeInputs.forEach(input => input.addEventListener('change', updateMethods));
    methodInputs.forEach(input => input.addEventListener('change', updateMethods));
    updateMethods();
})();
</script>

</body>
</html>
