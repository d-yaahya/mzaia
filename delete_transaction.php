<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();

$employee = current_employee();
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM transactions WHERE id = :id AND employee_id = :employee_id');
    $stmt->execute([
        ':id' => $id,
        ':employee_id' => $employee['id'],
    ]);
}

redirect('index.php');
