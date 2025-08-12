<?php
session_start();
require_once './config/db.php';

// معالجة حذف الحجز
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

// معالجة تعديل حالة الحجز
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reservation_id'])) {
    $reservation_id = intval($_POST['edit_reservation_id']);
    $status = $_POST['status'];

    $mysqli->begin_transaction();
    try {
        $getBook = $mysqli->prepare("SELECT book_id, status FROM BookReservations WHERE reservation_id = ? AND deleted_at IS NULL");
        $getBook->bind_param("i", $reservation_id);
        $getBook->execute();
        $bookData = $getBook->get_result()->fetch_assoc();
        $getBook->close();

        if (!$bookData) {
            throw new Exception("الحجز غير موجود أو محذوف مسبقًا");
        }

        $book_id = $bookData['book_id'];
        $old_status = $bookData['status'];

        $stmt = $mysqli->prepare("
            UPDATE BookReservations 
            SET status = ?, 
                approved_date = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_date END, 
                due_date = CASE WHEN ? = 'approved' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) ELSE due_date END, 
                return_date = CASE WHEN ? = 'returned' THEN NOW() ELSE return_date END 
            WHERE reservation_id = ?
        ");
        $stmt->bind_param("ssssi", $status, $status, $status, $status, $reservation_id);
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

// معالجة إضافة حجز جديد
$add_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {
    $student_id = intval($_POST['student_id']);
    $teacher_id = intval($_POST['teacher_id']); // يستخدم فقط لجلب الكتب وليس للحجز مباشرة
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

            // إضافة الحجز مع المبالغ
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

// استعلام عرض الحجوزات
$reservations = $mysqli->query("
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
ORDER BY br.created_at DESC
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

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>حجوزات الكتب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            width: 300px;
        }
    </style>
    <script>
        function fetchBooks() {
            let teacherId = document.getElementById('teacher_id').value;
            let bookSelect = document.getElementById('book_id');
            bookSelect.innerHTML = '<option value="">جاري التحميل...</option>';
            if (teacherId) {
                fetch('get_books.php?teacher_id=' + teacherId)
                    .then(res => res.json())
                    .then(data => {
                        bookSelect.innerHTML = '<option value="">-- اختر الكتاب --</option>';
                        data.forEach(book => {
                            bookSelect.innerHTML += `<option value="${book.book_id}" data-price="${book.price}">${book.title} - ${book.price} ر.س (المتبقي: ${book.quantity})</option>`;
                        });
                    });
            } else {
                bookSelect.innerHTML = '<option value="">-- اختر الكتاب --</option>';
            }
        }

        function fetchInfo() {
            let bookSelect = document.getElementById('book_id');
            let selectedBook = bookSelect.options[bookSelect.selectedIndex];
            let bookPrice = selectedBook.getAttribute('data-price') || 0;
            document.getElementById('book_price').value = bookPrice;
            calculateDue();
        }

        function calculateDue() {
            let bookPrice = parseFloat(document.getElementById('book_price').value) || 0;
            let amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
            let amountDue = Math.max(bookPrice - amountPaid, 0);
            document.getElementById('amount_due').value = amountDue.toFixed(2);
        }
    </script>
</head>

<body class="container py-4">
    <!-- عرض الرسائل التحذيرية والنجاح -->
    <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <h2 class="text-center mb-4">إضافة حجز جديد</h2>
    <?php if (!empty($add_errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($add_errors as $error)
                    echo "<li>" . htmlspecialchars($error) . "</li>"; ?></ul>
            </div>
    <?php endif; ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm mb-4" novalidate>
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">اختر الطالب</label>
                <select class="form-select" name="student_id" required>
                    <option value="">-- اختر الطالب --</option>
                    <?php while ($student = $students->fetch_assoc()): ?>
                            <option value="<?= $student['student_id'] ?>"><?= htmlspecialchars($student['name']) ?> -
                                <?= htmlspecialchars($student['grade_name']) ?>
                            </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">اختر المدرس</label>
                <select class="form-select" name="teacher_id" id="teacher_id" onchange="fetchBooks()" required>
                    <option value="">-- اختر المدرس --</option>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">اختر الكتاب</label>
                <select class="form-select" name="book_id" id="book_id" onchange="fetchInfo()" required>
                    <option value="">-- اختر الكتاب --</option>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">سعر الكتاب</label>
                <input type="number" step="0.01" class="form-control" name="book_price" id="book_price" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label">المبلغ المدفوع</label>
                <input type="number" step="0.01" class="form-control" name="amount_paid" id="amount_paid"
                    oninput="calculateDue()" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">الباقي</label>
                <input type="number" step="0.01" class="form-control" name="amount_due" id="amount_due" readonly>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label">تاريخ الحجز</label>
                <input type="date" class="form-control" name="reservation_date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <button type="submit" name="add_reservation" class="btn btn-success">حجز الكتاب</button>
    </form>

    <h2 class="text-center mb-4">قائمة الحجوزات</h2>
    <div class="table-responsive bg-white p-3 rounded shadow-sm">
        <table class="table table-bordered table-striped">
            <thead class="table-dark text-center">
                <tr>
                    <th>رقم الحجز</th>
                    <th>اسم الطالب</th>
                    <th>المرحلة</th>
                    <th>دفع</th>
                    <th>الباقي</th>
                    <th>المدرس</th>
                    <th>الكتاب</th>
                    <th>السعر</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th>تعديل</th>
                    <th>حذف</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations && $reservations->num_rows > 0):
                    while ($row = $reservations->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><?= $row['reservation_id'] ?></td>
                                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                                    <td><?= htmlspecialchars($row['grade_name']) ?></td>
                                    <td class="text-end"><?= number_format($row['amount_paid'], 2) ?></td>
                                    <td class="text-end"><?= number_format($row['amount_due'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                    <td><?= htmlspecialchars($row['book_title']) ?></td>
                                    <td class="text-end"><?= number_format($row['book_price'], 2) ?></td>
                                    <td class="text-center"><?= $row['reservation_date'] ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['status']) ?></td>
                                    <td>
                                        <form method="post" class="d-flex flex-column align-items-center">
                                            <input type="hidden" name="edit_reservation_id" value="<?= $row['reservation_id'] ?>">
                                            <select name="status" class="form-select form-select-sm mb-1" required>
                                                <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : '' ?>>معلق</option>
                                                <option value="approved" <?= $row['status'] == 'approved' ? 'selected' : '' ?>>موافق
                                                </option>
                                                <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : '' ?>>ملغي
                                                </option>
                                                <option value="returned" <?= $row['status'] == 'returned' ? 'selected' : '' ?>>معاد
                                                </option>
                                            </select>
                                            <button class="btn btn-primary btn-sm w-100">حفظ</button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <a href="?delete=<?= $row['reservation_id'] ?>" class="btn btn-danger btn-sm"
                                            onclick="return confirm('تأكيد الحذف؟')">حذف</a>
                                    </td>
                                </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="12" class="text-center">لا توجد حجوزات</td>
                        </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
</body>

</html>
<?php $mysqli->close(); ?>