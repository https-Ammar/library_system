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

// معالجة تعديل حالة الحجز
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
       SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) AS approved_reservations,
       SUM(CASE WHEN br.status = 'returned' THEN 1 ELSE 0 END) AS returned_reservations,
       SUM(br.amount_paid) AS total_amount
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


<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حجوزات الكتب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', loaded: true, darkMode: false, stickyMenu: false, sidebarToggle: false, scrollTop: false, isTaskModalModal: false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); 
            $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">
    <div x-show="loaded" x-transition.opacity
        x-init="window.addEventListener('DOMContentLoaded', () => {setTimeout(() => loaded = false, 500)})"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-white dark:bg-black">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent">
        </div>
    </div>

    <div class="flex h-screen overflow-hidden">
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <div :class="sidebarToggle ? 'block xl:hidden' : 'hidden'"
                class="fixed z-50 h-screen w-full bg-gray-900/50"></div>

            <main>
                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    <div class="col-span-12">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
                       <div class="flex flex-col gap-5 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
            حجوزات الكتب
        </h3>
    </div>
    <div class="flex items-center gap-3">
        <button
            @click="isTaskModalModal = true; $nextTick(() => { document.querySelector('input[name=\'student_id\']').focus(); })"
            class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
            إضافة حجز جديد
        </button>

        <form method="GET" action="" class="flex">
            <div class="relative">
                <span class="absolute -translate-y-1/2 pointer-events-none top-1/2 left-4">
                    <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                        viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z"
                            fill=""></path>
                    </svg>
                </span>
                <input type="text" name="search" placeholder="بحث..."
                    value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 
                           dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent 
                           py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 
                           focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 
                           dark:placeholder:text-white/30">
            </div>
        </form>
    </div>
</div>

                            <div class="max-w-full overflow-x-auto custom-scrollbar">
                                <table class="min-w-full">
                                    <thead
                                        class="border-gray-100 border-y bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                        <tr>
                                       
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        اسم الطالب
                                                    </p>
                                                </div>
                                            </th>

                                                <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        المدرس
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الكتاب
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        السعر
                                                    </p>
                                                </div>
                                            </th>
                                     
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        المدفوع
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الباقي
                                                    </p>
                                                </div>
                                            </th>
                                        
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        التاريخ
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الحالة
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center justify-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        إجراءات
                                                    </p>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <?php if ($reservations && $reservations->num_rows > 0):
                                            while ($row = $reservations->fetch_assoc()): ?>
                                                <tr>




                                                <td class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex items-center gap-3">
                                                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                            <span class="text-xs font-semibold text-brand-500">
                                                                <?= $row['reservation_id'] ?>                                                      </span>
                                                        </div>
                                                        <div>
                                                            <span class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                          <?= htmlspecialchars($row['student_name']) ?>                                                        </span>
                                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                           <?= htmlspecialchars($row['grade_name']) ?>                                                         </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>



                                               
                                             
                                                
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($row['teacher_name']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($row['book_title']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-end">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= number_format($row['book_price'], 2) ?>
                                                        </p>
                                                    </td>

                                                        <td class="px-6 py-3 whitespace-nowrap text-end">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= number_format($row['amount_paid'], 2) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-end">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= number_format($row['amount_due'], 2) ?>
                                                        </p>
                                                    </td>
                                                    
                                                    <td class="px-6 py-3 whitespace-nowrap text-center">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= $row['reservation_date'] ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-center">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($row['status']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <form method="post" class="flex items-center gap-2">
                                                                <input type="hidden" name="edit_reservation_id"
                                                                    value="<?= $row['reservation_id'] ?>">

                                                                <select name="status" onchange="fetchInfo()" required=""
                                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                                    <option value="">-- اختر الكتاب --</option>
                                                                    <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : '' ?>>معلق</option>
                                                                    <option value="approved" <?= $row['status'] == 'approved' ? 'selected' : '' ?>>موافق</option>
                                                                    <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                                                                    <option value="returned" <?= $row['status'] == 'returned' ? 'selected' : '' ?>>معاد</option>
                                                                </select>

                                                           
                                                                <button type="submit"
                                                                    class="btn btn-primary btn-sm">
                                                                
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-floppy text-gray-700 text-theme-sm dark:text-gray-400" viewBox="0 0 16 16">
  <path d="M11 2H9v3h2z"/>
  <path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z"/>
</svg>
                                                                </button>
                                                            </form>
                                                            <a href="?delete=<?= $row['reservation_id'] ?>"
                                                                onclick="return confirm('تأكيد الحذف؟');"
                                                                class="cursor-pointer hover:fill-error-500 dark:hover:fill-error-500 fill-gray-700 dark:fill-gray-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-x-octagon text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M4.54.146A.5.5 0 0 1 4.893 0h6.214a.5.5 0 0 1 .353.146l4.394 4.394a.5.5 0 0 1 .146.353v6.214a.5.5 0 0 1-.146.353l-4.394 4.394a.5.5 0 0 1-.353.146H4.893a.5.5 0 0 1-.353-.146L.146 11.46A.5.5 0 0 1 0 11.107V4.893a.5.5 0 0 1 .146-.353zM5.1 1 1 5.1v5.8L5.1 15h5.8l4.1-4.1V5.1L10.9 1z">
                                                                    </path>
                                                                    <path
                                                                        d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708">
                                                                    </path>
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center py-4">لا توجد حجوزات</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="isTaskModalModal" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                    class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999"
                    :class="{'hidden': !isTaskModalModal}">
                    <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                        @click="isTaskModalModal = false"></div>

                    <div @click.outside="isTaskModalModal = false"
                        class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">

                        <div class="px-2">
                            <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                                إضافة حجز جديد </h4>
                            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">إدارة حجوزات الكتب بسهولة
                            </p>
                        </div>

                        <form class="flex flex-col" method="POST" action="">
                            <div class="custom-scrollbar overflow-y-auto px-2">
                                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر
                                            الطالب</label>
                                        <select name="student_id" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">-- اختر الطالب --</option>
                                            <?php while ($student = $students->fetch_assoc()): ?>
                                                <option value="<?= $student['student_id'] ?>">
                                                    <?= htmlspecialchars($student['name']) ?> -
                                                    <?= htmlspecialchars($student['grade_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر
                                            المدرس</label>
                                        <select name="teacher_id" id="teacher_id" onchange="fetchBooks()" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">-- اختر المدرس --</option>
                                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                                <option value="<?= $teacher['teacher_id'] ?>">
                                                    <?= htmlspecialchars($teacher['name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر
                                            الكتاب</label>
                                        <select name="book_id" id="book_id" onchange="fetchInfo()" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">-- اختر الكتاب --</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">سعر
                                            الكتاب</label>
                                        <input type="number" step="0.01" name="book_price" id="book_price" readonly
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المبلغ
                                            المدفوع</label>
                                        <input type="number" step="0.01" name="amount_paid" id="amount_paid"
                                            oninput="calculateDue()" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الباقي</label>
                                        <input type="number" step="0.01" name="amount_due" id="amount_due" readonly
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">تاريخ
                                            الحجز</label>
                                        <input type="date" name="reservation_date" value="<?= date('Y-m-d') ?>" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                <div class="flex items-center w-full gap-3 sm:w-auto">
                                    <button @click="isTaskModalModal = false" type="button"
                                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                    <button type="submit" name="add_reservation"
                                        class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">
                                        حجز الكتاب </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script defer src="../assets/js/bundle.js"></script>
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
</body>

</html>
<?php $mysqli->close(); ?>