<?php
$officePeriod = $_GET['office_period'] ?? 'all';
$officeFrom = $_GET['office_from'] ?? '';
$officeTo = $_GET['office_to'] ?? '';

if (!function_exists('mzaia_office_period_range')) {
    function mzaia_office_period_range($period, $from = '', $to = '') {
        $allowed = ['all', 'month', 'previous_month', 'last_3_months', 'year', 'custom'];

        if (!in_array($period, $allowed, true)) {
            $period = 'all';
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

            return [null, null, 'مدة مخصصة', 'custom'];
        }

        switch ($period) {
            case 'month':
                return [date('Y-m-01'), date('Y-m-t'), 'هذا الشهر', 'month'];

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
            default:
                return [null, null, 'كل المدة', 'all'];
        }
    }
}

[$officeStart, $officeEnd, $officeLabel, $officePeriod] = mzaia_office_period_range($officePeriod, $officeFrom, $officeTo);

$officeWhere = '';
$officeParams = [];

if ($officeStart !== null && $officeEnd !== null) {
    $officeWhere = 'WHERE transaction_date BETWEEN :from AND :to';
    $officeParams[':from'] = $officeStart;
    $officeParams[':to'] = $officeEnd;
}

$officeTotalsStmt = $pdo->prepare("SELECT
    COUNT(id) AS operations_count,
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
    FROM transactions
    {$officeWhere}");

$officeTotalsStmt->execute($officeParams);
$officeTotals = $officeTotalsStmt->fetch();
$officeNet = (float)$officeTotals['income'] - (float)$officeTotals['expense'];

$methodStmt = $pdo->prepare("SELECT
    type,
    COALESCE(NULLIF(payment_method, ''), 'غير محدد') AS payment_method,
    COUNT(id) AS operations_count,
    COALESCE(SUM(amount), 0) AS total
    FROM transactions
    {$officeWhere}
    GROUP BY type, COALESCE(NULLIF(payment_method, ''), 'غير محدد')
    ORDER BY type ASC, total DESC");

$methodStmt->execute($officeParams);
$officeMethods = $methodStmt->fetchAll();

$officeDetailsOpen = isset($_GET['office_period']) ? ' open' : '';
?>

<section class="panel office-summary-widget office-summary-dropdown-widget">
    <details class="office-summary-details"<?= $officeDetailsOpen ?>>
        <summary class="office-summary-summary">
            <div>
                <h2>إجمالي المكتب - <?= e($officeLabel) ?></h2>
                <p>اضغط لعرض أو إخفاء تفاصيل دخل ومصروفات المكتب.</p>
            </div>

            <div class="office-summary-mini">
                <span>الدخل: <strong class="income"><?= money($officeTotals['income']) ?></strong></span>
                <span>المصروف: <strong class="expense"><?= money($officeTotals['expense']) ?></strong></span>
                <span>الصافي: <strong class="<?= $officeNet >= 0 ? 'income' : 'expense' ?>"><?= money($officeNet) ?></strong></span>
            </div>
        </summary>

        <div class="office-summary-dropdown-content">
            <div class="office-summary-widget-head">
                <div>
                    <h3>تفاصيل إجمالي المكتب</h3>
                    <p>غيّر المدة لعرض إجمالي المكتب حسب الفترة المطلوبة.</p>
                </div>

                <form method="get" class="office-period-form">
                    <label>
                        المدة
                        <select name="office_period" onchange="mzaiaToggleOfficeCustomRange(this)">
                            <option value="all" <?= $officePeriod === 'all' ? 'selected' : '' ?>>كل المدة</option>
                            <option value="month" <?= $officePeriod === 'month' ? 'selected' : '' ?>>هذا الشهر</option>
                            <option value="previous_month" <?= $officePeriod === 'previous_month' ? 'selected' : '' ?>>الشهر السابق</option>
                            <option value="last_3_months" <?= $officePeriod === 'last_3_months' ? 'selected' : '' ?>>آخر 3 أشهر</option>
                            <option value="year" <?= $officePeriod === 'year' ? 'selected' : '' ?>>هذا العام</option>
                            <option value="custom" <?= $officePeriod === 'custom' ? 'selected' : '' ?>>مدة مخصصة</option>
                        </select>
                    </label>

                    <div class="office-custom-range <?= $officePeriod === 'custom' ? 'show' : '' ?>">
                        <label>من <input type="date" name="office_from" value="<?= e($officeFrom) ?>"></label>
                        <label>إلى <input type="date" name="office_to" value="<?= e($officeTo) ?>"></label>
                        <button type="submit">تطبيق</button>
                    </div>
                </form>
            </div>

            <div class="office-summary-cards">
                <article>
                    <span>إجمالي دخل المكتب</span>
                    <strong class="income"><?= money($officeTotals['income']) ?></strong>
                </article>

                <article>
                    <span>إجمالي مصروفات المكتب</span>
                    <strong class="expense"><?= money($officeTotals['expense']) ?></strong>
                </article>

                <article>
                    <span>صافي المكتب</span>
                    <strong class="<?= $officeNet >= 0 ? 'income' : 'expense' ?>"><?= money($officeNet) ?></strong>
                </article>

                <article>
                    <span>عدد العمليات</span>
                    <strong><?= e((string)$officeTotals['operations_count']) ?></strong>
                </article>
            </div>

            <div class="office-methods">
                <h3>تفصيل حسب طريقة الدفع / الصرف</h3>

                <?php if (empty($officeMethods)): ?>
                    <p class="muted">لا توجد عمليات في هذه المدة.</p>
                <?php else: ?>
                    <div class="office-methods-grid">
                        <?php foreach ($officeMethods as $row): ?>
                            <article>
                                <span><?= $row['type'] === 'income' ? 'دخل' : 'مصروف' ?> - <?= e($row['payment_method']) ?></span>
                                <strong class="<?= $row['type'] === 'income' ? 'income' : 'expense' ?>"><?= money($row['total']) ?></strong>
                                <small><?= e((string)$row['operations_count']) ?> عملية</small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </details>
</section>

<script>
function mzaiaToggleOfficeCustomRange(select) {
    const form = select.closest('form');
    const customRange = form ? form.querySelector('.office-custom-range') : null;

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