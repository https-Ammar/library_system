<?php
session_start();
require_once '../config/db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $mysqli->begin_transaction();
    try {
        $getBook = $mysqli->prepare("SELECT book_id, status FROM BookReservations WHERE reservation_id = ? AND deleted_at IS NULL");
        $getBook->bind_param("i", $id);
        $getBook->execute();
        $bookData = $getBook->get_result()->fetch_assoc();
        $getBook->close();

        if (!$bookData) {
            throw new Exception("الحجز غير موجود أو محذوف مسبقًا");
        }

        $book_id = $bookData['book_id'];
        $status = $bookData['status'];

        $stmt = $mysqli->prepare("UPDATE BookReservations SET deleted_at = NOW() WHERE reservation_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        if ($status !== 'cancelled' && $status !== 'returned') {
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = quantity + 1 WHERE book_id = ?");
            $updateQty->bind_param("i", $book_id);
            $updateQty->execute();
            $updateQty->close();
        }

        $mysqli->commit();
        $_SESSION['message'] = "تم حذف الحجز بنجاح";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "فشل في حذف الحجز: " . $e->getMessage();
    }

    header('Location: book_reservations.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reservation_id'])) {
    $reservation_id = intval($_POST['edit_reservation_id']);
    $status = $_POST['status'];
    $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : null;

    $mysqli->begin_transaction();
    try {
        $getBook = $mysqli->prepare("SELECT book_id, status, amount_paid, amount_due FROM BookReservations WHERE reservation_id = ? AND deleted_at IS NULL");
        $getBook->bind_param("i", $reservation_id);
        $getBook->execute();
        $bookData = $getBook->get_result()->fetch_assoc();
        $getBook->close();

        if (!$bookData) {
            throw new Exception("الحجز غير موجود أو محذوف مسبقًا");
        }

        $book_id = $bookData['book_id'];
        $old_status = $bookData['status'];
        $old_amount_paid = $bookData['amount_paid'];
        $amount_due = $bookData['amount_due'];

        if ($amount_paid !== null) {
            $amount_due = max($amount_due - ($amount_paid - $old_amount_paid), 0);
            $stmt = $mysqli->prepare("
                UPDATE BookReservations 
                SET status = ?, 
                    amount_paid = ?,
                    amount_due = ?,
                    approved_date = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_date END, 
                    due_date = CASE WHEN ? = 'approved' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) ELSE due_date END, 
                    return_date = CASE WHEN ? = 'returned' THEN NOW() ELSE return_date END 
                WHERE reservation_id = ?
            ");
            $stmt->bind_param("sddsssi", $status, $amount_paid, $amount_due, $status, $status, $status, $reservation_id);
        } else {
            $stmt = $mysqli->prepare("
                UPDATE BookReservations 
                SET status = ?, 
                    approved_date = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_date END, 
                    due_date = CASE WHEN ? = 'approved' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) ELSE due_date END, 
                    return_date = CASE WHEN ? = 'returned' THEN NOW() ELSE return_date END 
                WHERE reservation_id = ?
            ");
            $stmt->bind_param("ssssi", $status, $status, $status, $status, $reservation_id);
        }

        $stmt->execute();
        $stmt->close();

        if (
            ($status === 'cancelled' || $status === 'returned') &&
            !in_array($old_status, ['cancelled', 'returned'])
        ) {
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = quantity + 1 WHERE book_id = ?");
            $updateQty->bind_param("i", $book_id);
            $updateQty->execute();
            $updateQty->close();
        } elseif (
            ($old_status === 'cancelled' || $old_status === 'returned') &&
            !in_array($status, ['cancelled', 'returned'])
        ) {
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = GREATEST(quantity - 1, 0) WHERE book_id = ?");
            $updateQty->bind_param("i", $book_id);
            $updateQty->execute();
            $updateQty->close();
        }

        $mysqli->commit();
        $_SESSION['message'] = "تم تحديث حالة الحجز بنجاح";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "فشل في تحديث الحجز: " . $e->getMessage();
    }

    header('Location: book_reservations.php');
    exit;
}

$add_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {
    $student_id = intval($_POST['student_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $book_id = intval($_POST['book_id']);
    $reservation_date = $_POST['reservation_date'] ?: date('Y-m-d');
    $amount_paid = floatval($_POST['amount_paid']);
    $book_price = floatval($_POST['book_price']);

    if ($student_id <= 0)
        $add_errors[] = "اختر الطالب.";
    if ($teacher_id <= 0)
        $add_errors[] = "اختر المدرس.";
    if ($book_id <= 0)
        $add_errors[] = "اختر الكتاب.";
    if ($amount_paid < 0)
        $add_errors[] = "المبلغ المدفوع غير صحيح.";
    if ($book_price < 0)
        $add_errors[] = "سعر الكتاب غير صحيح.";

    if (empty($add_errors)) {
        $mysqli->begin_transaction();
        try {
            $check = $mysqli->prepare("SELECT quantity, price FROM Books WHERE book_id = ? FOR UPDATE");
            $check->bind_param("i", $book_id);
            $check->execute();
            $result = $check->get_result();
            $book = $result->fetch_assoc();
            $check->close();

            if (!$book) {
                throw new Exception("الكتاب غير موجود.");
            } elseif ($book['quantity'] < 1) {
                throw new Exception("عذرًا، لا توجد نسخ متاحة من هذا الكتاب.");
            }

            $amount_due = max($book_price - $amount_paid, 0);

            $stmt = $mysqli->prepare("
                INSERT INTO BookReservations (student_id, book_id, reservation_date, status, amount_paid, amount_due) 
                VALUES (?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->bind_param("iisdd", $student_id, $book_id, $reservation_date, $amount_paid, $amount_due);
            $stmt->execute();
            $stmt->close();

            $update = $mysqli->prepare("UPDATE Books SET quantity = GREATEST(quantity - 1, 0) WHERE book_id = ?");
            $update->bind_param("i", $book_id);
            $update->execute();
            $update->close();

            $mysqli->commit();
            $_SESSION['message'] = "تم حجز الكتاب بنجاح";
            header('Location: book_reservations.php');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $add_errors[] = "فشل الحجز: " . $e->getMessage();
        }
    }
}

$search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
$reservations_query = "
SELECT br.*, 
       s.name AS student_name, 
       g.name AS grade_name,
       br.amount_paid, 
       br.amount_due, 
       b.title AS book_title, 
       b.price AS book_price,
       t.name AS teacher_name
FROM BookReservations br
JOIN Students s ON br.student_id = s.student_id
LEFT JOIN Grades g ON s.grade_id = g.grade_id
JOIN Books b ON br.book_id = b.book_id
LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
WHERE br.deleted_at IS NULL
";

if (!empty($search)) {
    $reservations_query .= " AND (
        s.name LIKE '%$search%' OR 
        b.title LIKE '%$search%' OR 
        t.name LIKE '%$search%' OR 
        g.name LIKE '%$search%' OR 
        br.reservation_id = '$search'
    )";
}

$reservations_query .= " ORDER BY br.created_at DESC";
$reservations = $mysqli->query($reservations_query);

$teachers_stats = $mysqli->query("
SELECT t.teacher_id, t.name, 
       COUNT(br.reservation_id) AS total_reservations,
       SUM(CASE WHEN br.status = 'pending' THEN 1 ELSE 0 END) AS pending_reservations,
       SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) AS approved_reservations,
       SUM(CASE WHEN br.status = 'returned' THEN 1 ELSE 0 END) AS returned_reservations,
       SUM(CASE WHEN br.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_reservations,
       SUM(br.amount_paid) AS total_amount,
       SUM(br.amount_due) AS total_due
FROM Teachers t
LEFT JOIN Books b ON t.teacher_id = b.teacher_id
LEFT JOIN BookReservations br ON b.book_id = br.book_id AND br.deleted_at IS NULL
WHERE t.deleted_at IS NULL
GROUP BY t.teacher_id, t.name
ORDER BY total_reservations DESC
");

$students = $mysqli->query("
SELECT s.student_id, s.name, g.name AS grade_name
FROM Students s
LEFT JOIN Grades g ON s.grade_id = g.grade_id
WHERE s.deleted_at IS NULL
ORDER BY s.name
");

$teachers = $mysqli->query("SELECT teacher_id, name FROM Teachers WHERE deleted_at IS NULL ORDER BY name");
?>
<!-- قسم إحصائيات المدرسين -->
<section class="teachers-stats my-5">
    <div class="container">



        <!-- جدول إحصائيات المدرسين -->
        <div class="card shadow">

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>اسم المدرس</th>
                                <th>إجمالي الحجوزات</th>
                                <th>قيد الانتظار</th>
                                <th>معتمدة</th>
                                <th>ملغاة</th>
                                <th>مستردة</th>
                                <th>المبلغ المدفوع</th>
                                <th>المبلغ المستحق</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $teachers_stats->data_seek(0); // إعادة تعيين مؤشر النتائج
                            $counter = 1;
                            while ($teacher = $teachers_stats->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($teacher['name']) ?></td>
                                    <td><?= $teacher['total_reservations'] ?></td>
                                    <td><span class="badge bg-warning"><?= $teacher['pending_reservations'] ?></span></td>
                                    <td><span class="badge bg-success"><?= $teacher['approved_reservations'] ?></span></td>
                                    <td><span class="badge bg-danger"><?= $teacher['cancelled_reservations'] ?></span></td>
                                    <td><span class="badge bg-info"><?= $teacher['returned_reservations'] ?></span></td>
                                    <td><?= number_format($teacher['total_amount']) ?> ر.س</td>
                                    <td><?= number_format($teacher['total_due']) ?> ر.س</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>