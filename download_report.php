<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

$pdo = db();

function report_money($amount) {
    return number_format((float)$amount, 2) . ' ريال';
}

function report_period_range($period, $from = '', $to = '') {
    $today = date('Y-m-d');

    switch ($period) {
        case 'today':
            return [$today, $today, 'اليوم'];

        case 'month':
            return [date('Y-m-01'), date('Y-m-t'), 'هذا الشهر'];

        case 'previous_month':
            return [
                date('Y-m-01', strtotime('first day of previous month')),
                date('Y-m-t', strtotime('last day of previous month')),
                'الشهر السابق'
            ];

        case 'last_3_months':
            return [
                date('Y-m-01', strtotime('-2 months')),
                date('Y-m-t'),
                'آخر 3 أشهر'
            ];

        case 'year':
            return [date('Y-01-01'), date('Y-12-31'), 'هذا العام'];

        case 'custom':
            $fromValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
            $toValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

            if ($fromValid && $toValid) {
                if ($from > $to) {
                    $tmp = $from;
                    $from = $to;
                    $to = $tmp;
                }

                return [$from, $to, 'مدة مخصصة من ' . $from . ' إلى ' . $to];
            }

            return [null, null, 'كل المدة'];

        case 'all':
        default:
            return [null, null, 'كل المدة'];
    }
}

$reportType = $_GET['report_type'] ?? 'full';
$allowedReports = [
    'full',
    'office_summary',
    'employees_summary',
    'transactions_detail',
    'payment_methods',
    'movement_accounts'
];

if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'full';
}

$period = $_GET['period'] ?? 'all';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
[$startDate, $endDate, $periodLabel] = report_period_range($period, $from, $to);

$employeeId = $_GET['employee_id'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$paymentMethod = $_GET['payment_method'] ?? 'all';
$movementAccount = $_GET['movement_account'] ?? 'all';

$where = [];
$params = [];

if ($startDate !== null && $endDate !== null) {
    $where[] = 't.transaction_date BETWEEN :from AND :to';
    $params[':from'] = $startDate;
    $params[':to'] = $endDate;
}

if ($employeeId !== 'all' && ctype_digit((string)$employeeId)) {
    $where[] = 't.employee_id = :employee_id';
    $params[':employee_id'] = (int)$employeeId;
}

if (in_array($type, ['income', 'expense'], true)) {
    $where[] = 't.type = :type';
    $params[':type'] = $type;
}

if ($paymentMethod !== 'all') {
    $where[] = "COALESCE(NULLIF(t.payment_method, ''), 'غير محدد') = :payment_method";
    $params[':payment_method'] = $paymentMethod;
}

if ($movementAccount !== 'all') {
    $where[] = "COALESCE(NULLIF(t.movement_account, ''), 'غير محدد') = :movement_account";
    $params[':movement_account'] = $movementAccount;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT
    t.*,
    e.name AS employee_name,
    COALESCE(NULLIF(t.payment_method, ''), 'غير محدد') AS payment_method_label,
    COALESCE(NULLIF(t.movement_account, ''), 'غير محدد') AS movement_account_label
    FROM transactions t
    JOIN employees e ON e.id = t.employee_id
    {$whereSql}
    ORDER BY t.transaction_date ASC, t.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$totalIncome = 0;
$totalExpense = 0;
$employeesSummary = [];
$paymentMethodsSummary = [];
$movementAccountsSummary = [];

foreach ($transactions as $transaction) {
    $amount = (float)$transaction['amount'];
    $isIncome = $transaction['type'] === 'income';

    if ($isIncome) {
        $totalIncome += $amount;
    } else {
        $totalExpense += $amount;
    }

    $employeeKey = $transaction['employee_name'];
    if (!isset($employeesSummary[$employeeKey])) {
        $employeesSummary[$employeeKey] = [
            'name' => $transaction['employee_name'],
            'income' => 0,
            'expense' => 0,
            'operations_count' => 0,
        ];
    }

    if ($isIncome) {
        $employeesSummary[$employeeKey]['income'] += $amount;
    } else {
        $employeesSummary[$employeeKey]['expense'] += $amount;
    }
    $employeesSummary[$employeeKey]['operations_count']++;

    $methodKey = $transaction['type'] . '|' . $transaction['payment_method_label'];
    if (!isset($paymentMethodsSummary[$methodKey])) {
        $paymentMethodsSummary[$methodKey] = [
            'type' => $transaction['type'],
            'payment_method' => $transaction['payment_method_label'],
            'total' => 0,
            'operations_count' => 0,
        ];
    }
    $paymentMethodsSummary[$methodKey]['total'] += $amount;
    $paymentMethodsSummary[$methodKey]['operations_count']++;

    $movementKey = $transaction['movement_account_label'] . '|' . $transaction['type'];
    if (!isset($movementAccountsSummary[$movementKey])) {
        $movementAccountsSummary[$movementKey] = [
            'movement_account' => $transaction['movement_account_label'],
            'type' => $transaction['type'],
            'total' => 0,
            'operations_count' => 0,
        ];
    }
    $movementAccountsSummary[$movementKey]['total'] += $amount;
    $movementAccountsSummary[$movementKey]['operations_count']++;
}

$totalNet = $totalIncome - $totalExpense;

$employeeLabel = 'كل الموظفين';
if ($employeeId !== 'all' && ctype_digit((string)$employeeId)) {
    $empStmt = $pdo->prepare('SELECT name FROM employees WHERE id = :id');
    $empStmt->execute([':id' => (int)$employeeId]);
    $employeeLabel = $empStmt->fetchColumn() ?: $employeeId;
}

$filterLabels = [
    'المدة: ' . $periodLabel,
    'الموظف: ' . $employeeLabel,
    'نوع العملية: ' . ($type === 'income' ? 'دخل فقط' : ($type === 'expense' ? 'مصروف فقط' : 'الكل')),
    'طريقة الدفع / الصرف: ' . ($paymentMethod === 'all' ? 'الكل' : $paymentMethod),
    'حساب الحركة: ' . ($movementAccount === 'all' ? 'الكل' : $movementAccount),
];

$filename = 'mzaia-detailed-report-' . date('Y-m-d-His') . '.xls';

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
            padding: 16px;
            margin: 0 0 18px 0;
            font-size: 22px;
            text-align: center;
        }

        h2 {
            background: #E9EFEC;
            color: #16423C;
            padding: 10px 12px;
            margin: 22px 0 8px 0;
            font-size: 17px;
            border-right: 6px solid #16423C;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 22px;
        }

        th {
            background: #16423C;
            color: #ffffff;
            border: 1px solid #0f2f2a;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }

        td {
            border: 1px solid #d7e3de;
            padding: 9px;
            text-align: right;
            vertical-align: middle;
        }

        tr:nth-child(even) td {
            background: #F8FAFC;
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
    <h1>التقرير التفصيلي - مزايا المجتمع التجارية</h1>

    <h2>الفلاتر المستخدمة</h2>
    <table class="filters">
        <tr><th>الفلتر</th></tr>
        <?php foreach ($filterLabels as $label): ?>
            <tr><td><?= e($label) ?></td></tr>
        <?php endforeach; ?>
    </table>

    <?php if (in_array($reportType, ['full', 'office_summary'], true)): ?>
        <h2>ملخص المكتب</h2>
        <table>
            <tr>
                <th>إجمالي الدخل</th>
                <th>إجمالي المصروف</th>
                <th>الصافي</th>
                <th>عدد العمليات</th>
            </tr>
            <tr>
                <td class="income"><?= report_money($totalIncome) ?></td>
                <td class="expense"><?= report_money($totalExpense) ?></td>
                <td class="<?= $totalNet >= 0 ? 'income' : 'expense' ?>"><?= report_money($totalNet) ?></td>
                <td><?= count($transactions) ?></td>
            </tr>
        </table>
    <?php endif; ?>

    <?php if (in_array($reportType, ['full', 'employees_summary'], true)): ?>
        <h2>ملخص الموظفين</h2>
        <table>
            <tr>
                <th>الموظف</th>
                <th>الدخل</th>
                <th>المصروف</th>
                <th>الصافي</th>
                <th>عدد العمليات</th>
            </tr>
            <?php foreach ($employeesSummary as $row): ?>
                <?php $net = (float)$row['income'] - (float)$row['expense']; ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td class="income"><?= report_money($row['income']) ?></td>
                    <td class="expense"><?= report_money($row['expense']) ?></td>
                    <td class="<?= $net >= 0 ? 'income' : 'expense' ?>"><?= report_money($net) ?></td>
                    <td><?= e((string)$row['operations_count']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (in_array($reportType, ['full', 'payment_methods'], true)): ?>
        <h2>تفصيل طرق الدفع / الصرف</h2>
        <table>
            <tr>
                <th>النوع</th>
                <th>طريقة الدفع / الصرف</th>
                <th>الإجمالي</th>
                <th>عدد العمليات</th>
            </tr>
            <?php foreach ($paymentMethodsSummary as $row): ?>
                <tr>
                    <td><?= $row['type'] === 'income' ? 'دخل' : 'مصروف' ?></td>
                    <td><?= e($row['payment_method']) ?></td>
                    <td class="<?= $row['type'] === 'income' ? 'income' : 'expense' ?>"><?= report_money($row['total']) ?></td>
                    <td><?= e((string)$row['operations_count']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (in_array($reportType, ['full', 'movement_accounts'], true)): ?>
        <h2>تفصيل حساب الحركة</h2>
        <table>
            <tr>
                <th>حساب الحركة</th>
                <th>النوع</th>
                <th>الإجمالي</th>
                <th>عدد العمليات</th>
            </tr>
            <?php foreach ($movementAccountsSummary as $row): ?>
                <tr>
                    <td><?= e($row['movement_account']) ?></td>
                    <td><?= $row['type'] === 'income' ? 'دخل' : 'مصروف' ?></td>
                    <td class="<?= $row['type'] === 'income' ? 'income' : 'expense' ?>"><?= report_money($row['total']) ?></td>
                    <td><?= e((string)$row['operations_count']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (in_array($reportType, ['full', 'transactions_detail'], true)): ?>
        <h2>تفصيل العمليات</h2>
        <table>
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
            <?php foreach ($transactions as $i => $transaction): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($transaction['transaction_date']) ?></td>
                    <td><?= e($transaction['employee_name']) ?></td>
                    <td><?= $transaction['type'] === 'income' ? 'دخل' : 'مصروف' ?></td>
                    <td><?= e($transaction['payment_method_label']) ?></td>
                    <td><?= e($transaction['movement_account_label']) ?></td>
                    <td><?= e($transaction['category']) ?></td>
                    <td><?= e($transaction['description']) ?></td>
                    <td class="<?= $transaction['type'] === 'income' ? 'income' : 'expense' ?>"><?= report_money($transaction['amount']) ?></td>
                    <td><?= e($transaction['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>