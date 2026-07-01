<?php
$movementPeriod = $_GET['office_period'] ?? 'all';
$movementFrom = $_GET['office_from'] ?? '';
$movementTo = $_GET['office_to'] ?? '';

if (!function_exists('mzaia_movement_period_range')) {
    function mzaia_movement_period_range($period, $from = '', $to = '') {
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

                return [$from, $to, 'مدة مخصصة من ' . $from . ' إلى ' . $to];
            }

            return [null, null, 'كل المدة'];
        }

        switch ($period) {
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

            case 'all':
            default:
                return [null, null, 'كل المدة'];
        }
    }
}

[$movementStart, $movementEnd, $movementLabel] = mzaia_movement_period_range($movementPeriod, $movementFrom, $movementTo);

$movementWhere = '';
$movementParams = [];

if ($movementStart !== null && $movementEnd !== null) {
    $movementWhere = 'WHERE transaction_date BETWEEN :from AND :to';
    $movementParams[':from'] = $movementStart;
    $movementParams[':to'] = $movementEnd;
}

$movementAccounts = [];
$movementAccountsError = '';

try {
    $movementStmt = $pdo->prepare("SELECT
        COALESCE(NULLIF(movement_account, ''), 'غير محدد') AS movement_account,
        COUNT(id) AS operations_count,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM transactions
        {$movementWhere}
        GROUP BY COALESCE(NULLIF(movement_account, ''), 'غير محدد')
        ORDER BY income DESC, movement_account ASC");

    $movementStmt->execute($movementParams);
    $movementAccounts = $movementStmt->fetchAll();
} catch (Throwable $e) {
    $movementAccountsError = 'تعذر تحميل ملخص حسابات الحركة.';
}
?>

<section class="panel movement-accounts-summary-panel">
    <details class="movement-accounts-details">
        <summary>
            <div>
                <h2>ملخص حسابات الحركة - <?= e($movementLabel) ?></h2>
                <p>اعرف كم دخل أو خرج من حساب المكتب، حسابك الخاص، أو صندوق الكاش.</p>
            </div>
            <span>عرض التفاصيل</span>
        </summary>

        <div class="movement-accounts-content">
            <?php if ($movementAccountsError): ?>
                <p class="muted"><?= e($movementAccountsError) ?></p>
            <?php elseif (empty($movementAccounts)): ?>
                <p class="muted">لا توجد عمليات في هذه المدة.</p>
            <?php else: ?>
                <div class="movement-accounts-grid">
                    <?php foreach ($movementAccounts as $row): ?>
                        <?php $net = (float)$row['income'] - (float)$row['expense']; ?>
                        <article>
                            <h3><?= e($row['movement_account']) ?></h3>

                            <div>
                                <span>الدخل</span>
                                <strong class="income"><?= money($row['income']) ?></strong>
                            </div>

                            <div>
                                <span>المصروف</span>
                                <strong class="expense"><?= money($row['expense']) ?></strong>
                            </div>

                            <div class="total">
                                <span>الصافي</span>
                                <strong class="<?= $net >= 0 ? 'income' : 'expense' ?>"><?= money($net) ?></strong>
                            </div>

                            <small><?= e((string)$row['operations_count']) ?> عملية</small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </details>
</section>