<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php?v=after-save-fix');
}

verify_csrf();

$employee = current_employee();

$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$movementAccount = trim($_POST['movement_account'] ?? '');
$description = trim($_POST['description'] ?? '');
$date = trim($_POST['transaction_date'] ?? date('Y-m-d'));

if (!in_array($type, ['income', 'expense'], true)) {
    exit('نوع العملية غير صحيح.');
}

if ($amount <= 0) {
    exit('المبلغ يجب أن يكون أكبر من صفر.');
}

if ($category === '') {
    exit('التصنيف مطلوب.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    exit('صيغة التاريخ غير صحيحة.');
}

$allowedPaymentMethods = $type === 'income'
    ? ['كاش', 'تحويل', 'شبكة']
    : ['كاش', 'من الحساب'];

if ($paymentMethod === '') {
    $paymentMethod = 'كاش';
}

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    exit('طريقة الدفع أو الصرف غير صحيحة.');
}

$allowedMovementAccounts = [
    'حساب المكتب',
    'حساب يحيى الخاص',
    'حساب موسى',
    'حساب كريم',
    'صندوق الكاش',
    'أخرى'
];

if ($movementAccount === '') {
    $movementAccount = $paymentMethod === 'كاش' ? 'صندوق الكاش' : 'حساب المكتب';
}

if (!in_array($movementAccount, $allowedMovementAccounts, true)) {
    exit('حساب الحركة غير صحيح.');
}

$pdo = db();

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE transactions
        SET type = :type,
            amount = :amount,
            category = :category,
            payment_method = :payment_method, movement_account = :movement_account,
            description = :description,
            transaction_date = :transaction_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND employee_id = :employee_id");

    $stmt->execute([
        ':type' => $type,
        ':amount' => $amount,
        ':category' => $category,
        ':payment_method' => $paymentMethod,
        ':movement_account' => $movementAccount,
        ':description' => $description,
        ':transaction_date' => $date,
        ':id' => $id,
        ':employee_id' => $employee['id'],
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO transactions
        (employee_id, type, amount, category, payment_method, movement_account, description, transaction_date)
        VALUES (:employee_id, :type, :amount, :category, :payment_method, :movement_account, :description, :transaction_date)");

    $stmt->execute([
        ':employee_id' => $employee['id'],
        ':type' => $type,
        ':amount' => $amount,
        ':category' => $category,
        ':payment_method' => $paymentMethod,
        ':movement_account' => $movementAccount,
        ':description' => $description,
        ':transaction_date' => $date,
    ]);
}

redirect('index.php?v=after-save-fix');