<?php
require_once './config/db.php';

header('Content-Type: application/json');

$student_id = intval($_GET['student_id'] ?? 0);
$book_id = intval($_GET['book_id'] ?? 0);

$response = [
    'book_price' => null,
    'amount_paid' => null,
    'amount_due' => null
];

if ($student_id > 0 && $book_id > 0) {
    // جلب سعر الكتاب
    $stmt = $mysqli->prepare("SELECT price FROM Books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->bind_result($book_price);
    if ($stmt->fetch()) {
        $response['book_price'] = number_format($book_price, 2);
    }
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT amount_paid, amount_due FROM Students WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($amount_paid, $amount_due);
    if ($stmt->fetch()) {
        $response['amount_paid'] = number_format($amount_paid, 2);
        $response['amount_due'] = number_format($amount_due, 2);
    }
    $stmt->close();
}

echo json_encode($response);
$mysqli->close();
?>