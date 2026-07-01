<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

$pdo = db();
$employees = $pdo->query("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>التقرير التفصيلي - مزايا المجتمع التجارية</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css?v=detailed-report-v1">
</head>
<body>
<main class="container report-page">
    <header class="report-header">
        <div>
            <h1>التقرير التفصيلي</h1>
            <p>اختر نوع التقرير والمدة والموظف ونوع العملية، ثم حمّل التقرير بصيغة Excel.</p>
        </div>

        <a class="secondary-button" href="index.php">العودة للرئيسية</a>
    </header>

    <section class="panel report-builder">
        <form method="get" action="download_report.php" class="report-form">
            <label>
                نوع التقرير
                <select name="report_type" required>
                    <option value="full">تقرير شامل</option>
                    <option value="office_summary">ملخص المكتب</option>
                    <option value="employees_summary">ملخص الموظفين</option>
                    <option value="transactions_detail">تفصيل العمليات</option>
                    <option value="payment_methods">تفصيل طرق الدفع / الصرف</option>
                    <option value="movement_accounts">تفصيل حساب الحركة</option>
                </select>
            </label>

            <label>
                المدة
                <select name="period" id="reportPeriod" onchange="toggleReportCustomRange()">
                    <option value="month">هذا الشهر</option>
                    <option value="today">اليوم</option>
                    <option value="previous_month">الشهر السابق</option>
                    <option value="last_3_months">آخر 3 أشهر</option>
                    <option value="year">هذا العام</option>
                    <option value="all">كل المدة</option>
                    <option value="custom">مدة مخصصة</option>
                </select>
            </label>

            <div class="report-custom-range" id="reportCustomRange">
                <label>
                    من تاريخ
                    <input type="date" name="from">
                </label>

                <label>
                    إلى تاريخ
                    <input type="date" name="to">
                </label>
            </div>

            <label>
                الموظف
                <select name="employee_id">
                    <option value="all">كل الموظفين</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= e((string)$employee['id']) ?>"><?= e($employee['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                نوع العملية
                <select name="type">
                    <option value="all">الكل</option>
                    <option value="income">دخل فقط</option>
                    <option value="expense">مصروف فقط</option>
                </select>
            </label>

            <label>
                طريقة الدفع / الصرف
                <select name="payment_method">
                    <option value="all">الكل</option>
                    <option value="كاش">كاش</option>
                    <option value="تحويل">تحويل</option>
                    <option value="شبكة">شبكة</option>
                    <option value="من الحساب">من الحساب</option>
                </select>
            </label>


            <label>
                حساب الحركة
                <select name="movement_account">
                    <option value="all">الكل</option>
                    <option value="حساب المكتب">حساب المكتب</option>
                    <option value="حساب يحيى الخاص">حساب يحيى الخاص</option>
                    <option value="حساب موسى">حساب موسى</option>
                    <option value="حساب كريم">حساب كريم</option>
                    <option value="صندوق الكاش">صندوق الكاش</option>
                    <option value="أخرى">أخرى</option>
                </select>
            </label>

            <div class="report-actions">
                <button type="submit">تحميل التقرير Excel</button>
            </div>
        </form>
    </section>

    <section class="panel report-help">
        <h2>متى أستخدم كل تقرير؟</h2>
        <div class="report-help-grid">
            <article>
                <strong>تقرير شامل</strong>
                <span>يعطيك ملخص المكتب، الموظفين، طرق الدفع، وتفصيل العمليات في ملف واحد.</span>
            </article>
            <article>
                <strong>ملخص المكتب</strong>
                <span>يعرض إجمالي دخل ومصروف وصافي المكتب.</span>
            </article>
            <article>
                <strong>ملخص الموظفين</strong>
                <span>يعرض دخل ومصروف وصافي كل موظف حسب المدة.</span>
            </article>
            <article>
                <strong>تفصيل العمليات</strong>
                <span>يعرض كل عملية بالتاريخ والموظف والتصنيف والوصف.</span>
            </article>
        </div>
    </section>
</main>

<script>
function toggleReportCustomRange() {
    const period = document.getElementById('reportPeriod');
    const custom = document.getElementById('reportCustomRange');

    if (!period || !custom) return;

    if (period.value === 'custom') {
        custom.classList.add('show');
    } else {
        custom.classList.remove('show');
    }
}

toggleReportCustomRange();
</script>
</body>
</html>