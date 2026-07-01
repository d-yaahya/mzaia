<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

$pdo = db();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$previousMonthStart = date('Y-m-01', strtotime('first day of previous month'));
$previousMonthEnd = date('Y-m-t', strtotime('last day of previous month'));

$period = $_GET['period'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$employeeFilter = (int)($_GET['employee_id'] ?? 0);
$movementAccountFilter = $_GET['movement_account'] ?? 'all';

$where = [];
$params = [];
$filterLabels = [];

if ($period === 'today') {
    $where[] = 't.transaction_date = :today';
    $params[':today'] = $today;
    $filterLabels[] = 'المدة: اليوم';
} elseif ($period === 'month') {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $monthStart;
    $params[':to'] = $monthEnd;
    $filterLabels[] = 'المدة: هذا الشهر';
} elseif ($period === 'previous_month') {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $previousMonthStart;
    $params[':to'] = $previousMonthEnd;
    $filterLabels[] = 'المدة: الشهر السابق';
} else {
    $period = 'all';
    $filterLabels[] = 'المدة: كل العمليات';
}

if (in_array($typeFilter, ['income', 'expense'], true)) {
    $where[] = 't.type = :type';
    $params[':type'] = $typeFilter;
    $filterLabels[] = 'نوع العملية: ' . ($typeFilter === 'income' ? 'دخل فقط' : 'مصروف فقط');
} else {
    $typeFilter = 'all';
    $filterLabels[] = 'نوع العملية: دخل ومصروف';
}

if ($employeeFilter > 0) {
    $where[] = 't.employee_id = :employee_id';
    $params[':employee_id'] = $employeeFilter;

    $empStmt = $pdo->prepare('SELECT name FROM employees WHERE id = :id');
    $empStmt->execute([':id' => $employeeFilter]);
    $empName = $empStmt->fetchColumn();

    $filterLabels[] = 'الموظف: ' . ($empName ?: $employeeFilter);
} else {
    $filterLabels[] = 'الموظف: كل الموظفين';
}

if ($movementAccountFilter !== 'all') {
    $where[] = "COALESCE(NULLIF(t.movement_account, ''), 'غير محدد') = :movement_account";
    $params[':movement_account'] = $movementAccountFilter;
    $filterLabels[] = 'حساب الحركة: ' . $movementAccountFilter;
} else {
    $filterLabels[] = 'حساب الحركة: كل الحسابات';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT t.*, e.name AS employee_name
    FROM transactions t
    JOIN employees e ON e.id = t.employee_id
    {$whereSql}
    ORDER BY t.transaction_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$totalIncome = 0;
$totalExpense = 0;

foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $totalIncome += (float)$transaction['amount'];
    } else {
        $totalExpense += (float)$transaction['amount'];
    }
}

$totalNet = $totalIncome - $totalExpense;
$filename = 'mzaia-filtered-report-' . date('Y-m-d-His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            color: #1f2937;
        }

        h1 {
            background: #16423C;
            color: #ffffff;
            padding: 14px;
            text-align: center;
            margin: 0 0 18px;
        }

        h2 {
            background: #E9EFEC;
            color: #16423C;
            padding: 10px;
            border-right: 5px solid #16423C;
            margin: 20px 0 8px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 18px;
        }

        th, td {
            border: 1px solid #999;
            padding: 8px;
            text-align: right;
            vertical-align: middle;
        }

        th {
            background: #16423C;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        tr:nth-child(even) td {
            background: #F8FAFC;
        }

        .summary th {
            background: #6A9C89;
        }

        .filters th {
            background: #6A9C89;
        }

        .filters td {
            background: #F7FFFB;
            font-weight: bold;
        }

        .income {
            color: #0f766e;
            font-weight: bold;
        }

        .expense {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>تقرير العمليات - مزايا المجتمع التجارية</h1>

    <h2>الفلاتر المستخدمة</h2>
    <table class="filters">
        <tr>
            <th>الفلتر</th>
        </tr>
        <?php foreach ($filterLabels as $label): ?>
            <tr>
                <td><?= e($label) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>الملخص</h2>
    <table class="summary">
        <tr>
            <th>إجمالي الدخل</th>
            <th>إجمالي المصروف</th>
            <th>الصافي</th>
            <th>عدد العمليات</th>
        </tr>
        <tr>
            <td class="income"><?= money($totalIncome) ?></td>
            <td class="expense"><?= money($totalExpense) ?></td>
            <td class="<?= $totalNet >= 0 ? 'income' : 'expense' ?>"><?= money($totalNet) ?></td>
            <td><?= count($transactions) ?></td>
        </tr>
    </table>

    <h2>تفصيل العمليات</h2>
    <table>
        <thead>
            <tr>
                <th>م</th>
                <th>التاريخ</th>
                <th>الموظف</th>
                <th>النوع</th>
                <th>طريقة الدفع / الصرف</th>
                <th>حساب الحركة</th>
                <th>التصنيف</th>
                <th>الوصف</th>
                <th>المبلغ</th>
                <th>تاريخ الإضافة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $i => $transaction): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($transaction['transaction_date']) ?></td>
                    <td><?= e($transaction['employee_name']) ?></td>
                    <td><?= $transaction['type'] === 'income' ? 'دخل' : 'مصروف' ?></td>
                    <td><?= e($transaction['payment_method'] ?: '-') ?></td>
                    <td><?= e($transaction['movement_account'] ?: 'غير محدد') ?></td>
                    <td><?= e($transaction['category']) ?></td>
                    <td><?= e($transaction['description']) ?></td>
                    <td class="<?= $transaction['type'] === 'income' ? 'income' : 'expense' ?>">
                        <?= money($transaction['amount']) ?>
                    </td>
                    <td><?= e($transaction['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>