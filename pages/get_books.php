<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$teacher_id = intval($_GET['teacher_id'] ?? 0);

if ($teacher_id > 0) {
    $stmt = $mysqli->prepare("SELECT book_id, title, price, quantity FROM Books WHERE teacher_id = ? AND deleted_at IS NULL AND quantity > 0 ORDER BY title");

    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $mysqli->error]);
        exit;
    }

    $stmt->bind_param("i", $teacher_id);

    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result();

    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    echo json_encode($books);

    $stmt->close();
} else {
    echo json_encode([]);
}

$mysqli->close();
