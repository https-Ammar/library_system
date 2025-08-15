<?php
session_start();
require_once '../config/db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $mysqli->begin_transaction();
    try {
        // جلب بيانات الحجز بما فيها الكمية
        $getBook = $mysqli->prepare("SELECT book_id, status, quantity FROM BookReservations WHERE reservation_id = ? AND deleted_at IS NULL");
        $getBook->bind_param("i", $id);
        $getBook->execute();
        $bookData = $getBook->get_result()->fetch_assoc();
        $getBook->close();

        if (!$bookData) {
            throw new Exception("الحجز غير موجود أو محذوف مسبقًا");
        }

        $book_id = $bookData['book_id'];
        $status = $bookData['status'];
        $quantity = $bookData['quantity']; // الكمية المحجوزة

        // حذف الحجز (حذف ناعم)
        $stmt = $mysqli->prepare("UPDATE BookReservations SET deleted_at = NOW() WHERE reservation_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // إعادة الكمية إلى المخزون إذا لم يكن الحجز ملغى أو معاد من قبل
        if ($status !== 'cancelled' && $status !== 'returned') {
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = quantity + ? WHERE book_id = ?");
            $updateQty->bind_param("ii", $quantity, $book_id);
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
    $receipt_image = null;

    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/receipts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExt = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'receipt_' . uniqid() . '.' . $fileExt;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
            $receipt_image = $targetPath;
        }
    }

    $mysqli->begin_transaction();
    try {
        // جلب بيانات الحجز بما فيها الكمية
        $getBook = $mysqli->prepare("SELECT book_id, status, amount_paid, amount_due, receipt_image, quantity FROM BookReservations WHERE reservation_id = ? AND deleted_at IS NULL");
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
        $old_receipt_image = $bookData['receipt_image'];
        $quantity = $bookData['quantity']; // الكمية المحجوزة

        if ($receipt_image === null) {
            $receipt_image = $old_receipt_image;
        }

        if ($amount_paid !== null) {
            $amount_due = max($amount_due - ($amount_paid - $old_amount_paid), 0);
            $stmt = $mysqli->prepare("
                UPDATE BookReservations 
                SET status = ?, 
                    amount_paid = ?,
                    amount_due = ?,
                    receipt_image = ?,
                    approved_date = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_date END, 
                    due_date = CASE WHEN ? = 'approved' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) ELSE due_date END, 
                    return_date = CASE WHEN ? = 'returned' THEN NOW() ELSE return_date END 
                WHERE reservation_id = ?
            ");
            $stmt->bind_param("sddssssi", $status, $amount_paid, $amount_due, $receipt_image, $status, $status, $status, $reservation_id);
        } else {
            $stmt = $mysqli->prepare("
                UPDATE BookReservations 
                SET status = ?, 
                    receipt_image = ?,
                    approved_date = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_date END, 
                    due_date = CASE WHEN ? = 'approved' THEN DATE_ADD(NOW(), INTERVAL 14 DAY) ELSE due_date END, 
                    return_date = CASE WHEN ? = 'returned' THEN NOW() ELSE return_date END 
                WHERE reservation_id = ?
            ");
            $stmt->bind_param("sssssi", $status, $receipt_image, $status, $status, $status, $reservation_id);
        }

        $stmt->execute();
        $stmt->close();

        // إدارة الكميات بناءً على تغيير الحالة
        if (
            ($status === 'cancelled' || $status === 'returned') &&
            !in_array($old_status, ['cancelled', 'returned'])
        ) {
            // إعادة الكمية كاملة إلى المخزون
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = quantity + ? WHERE book_id = ?");
            $updateQty->bind_param("ii", $quantity, $book_id);
            $updateQty->execute();
            $updateQty->close();
        } elseif (
            ($old_status === 'cancelled' || $old_status === 'returned') &&
            !in_array($status, ['cancelled', 'returned'])
        ) {
            // خصم الكمية كاملة من المخزون
            $updateQty = $mysqli->prepare("UPDATE Books SET quantity = GREATEST(quantity - ?, 0) WHERE book_id = ?");
            $updateQty->bind_param("ii", $quantity, $book_id);
            $updateQty->execute();
            $updateQty->close();
        }

        if ($receipt_image !== $old_receipt_image && $old_receipt_image && file_exists($old_receipt_image)) {
            unlink($old_receipt_image);
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

// بقية الكود كما هو...
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
        br.reservation_id = '$search' OR
        br.order_number LIKE '%$search%'
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
        <?php require('../includes/header.php'); ?>
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <div :class="sidebarToggle ? 'block xl:hidden' : 'hidden'"
                class="fixed z-50 h-screen w-full bg-gray-900/50"></div>

            <main>
                <?php require('../includes/nav.php'); ?>

                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">

                    <div class="mb-8 flex flex-col justify-between gap-4  flex-row items-center">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white"> السجل الحجز</h3>
                        <div>
                            <a href="./add_reservation.php">
                                <button @click="isTaskModalModal = true"
                                    class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                    <svg class="stroke-current fill-white dark:fill-gray-800" width="20" height="20"
                                        viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.29004 5.90393H17.7067" stroke="" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M17.7075 14.0961H2.29085" stroke="" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path
                                            d="M12.0826 3.33331C13.5024 3.33331 14.6534 4.48431 14.6534 5.90414C14.6534 7.32398 13.5024 8.47498 12.0826 8.47498C10.6627 8.47498 9.51172 7.32398 9.51172 5.90415C9.51172 4.48432 10.6627 3.33331 12.0826 3.33331Z"
                                            fill="" stroke="" stroke-width="1.5"></path>
                                        <path
                                            d="M7.91745 11.525C6.49762 11.525 5.34662 12.676 5.34662 14.0959C5.34661 15.5157 6.49762 16.6667 7.91745 16.6667C9.33728 16.6667 10.4883 15.5157 10.4883 14.0959C10.4883 12.676 9.33728 11.525 7.91745 11.525Z"
                                            fill="" stroke="" stroke-width="1.5"></path>
                                    </svg>
                                    إضافة حجز جديد
                                </button>
                            </a>
                        </div>
                    </div>

                    <div class="col-span-12">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-3">
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                                <div
                                    class="mb-6 flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M20.3662 1.11216C20.6592 0.8193 21.134 0.819349 21.4269 1.11227C21.7198 1.4052 21.7197 1.88007 21.4268 2.17293L17.0308 6.56803C16.7379 6.8609 16.263 6.86085 15.9701 6.56792C15.6773 6.275 15.6773 5.80013 15.9702 5.50726L20.3662 1.11216ZM16.6592 2.696C16.952 2.40308 16.952 1.9282 16.659 1.63534C16.3661 1.34248 15.8913 1.34253 15.5984 1.63545L14.0987 3.13545C13.8058 3.42837 13.8059 3.90325 14.0988 4.19611C14.3917 4.48897 14.8666 4.48892 15.1595 4.196L16.6592 2.696ZM11.8343 3.45488C11.7079 3.19888 11.4472 3.0368 11.1617 3.0368C10.8762 3.0368 10.6155 3.19888 10.4892 3.45488L8.06431 8.36817L2.64217 9.15605C2.35966 9.19711 2.12495 9.39499 2.03673 9.6665C1.94851 9.93801 2.02208 10.2361 2.22651 10.4353L6.15001 14.2598L5.2238 19.66C5.17554 19.9414 5.29121 20.2258 5.52216 20.3936C5.75312 20.5614 6.05932 20.5835 6.31201 20.4506L11.1617 17.901L16.0114 20.4506C16.2641 20.5835 16.5703 20.5614 16.8013 20.3936C17.0322 20.2258 17.1479 19.9414 17.0996 19.66L16.1734 14.2598L20.0969 10.4353C20.3014 10.2361 20.3749 9.93801 20.2867 9.6665C20.1985 9.39499 19.9638 9.19711 19.6813 9.15605L14.2591 8.36817L11.8343 3.45488ZM9.23491 9.3856L11.1617 5.48147L13.0885 9.3856C13.1978 9.60696 13.4089 9.76039 13.6532 9.79588L17.9617 10.4219L14.8441 13.4609C14.6673 13.6332 14.5866 13.8814 14.6284 14.1247L15.3643 18.4158L11.5107 16.3898C11.2922 16.275 11.0312 16.275 10.8127 16.3898L6.9591 18.4158L7.69508 14.1247C7.7368 13.8814 7.65614 13.6332 7.47938 13.4609L4.36174 10.4219L8.67021 9.79588C8.91449 9.76039 9.12567 9.60696 9.23491 9.3856ZM21.6514 5.12825C21.9443 5.42111 21.9444 5.89598 21.6515 6.18891L20.1518 7.68891C19.8589 7.98183 19.3841 7.98188 19.0912 7.68901C18.7982 7.39615 18.7982 6.92128 19.091 6.62836L20.5907 5.12836C20.8836 4.83543 21.3585 4.83538 21.6514 5.12825Z"
                                            fill=""></path>
                                    </svg>
                                </div>

                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    إجمالي المدرسين
                                </p>

                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            <?= $teachers_stats->num_rows ?>
                                        </h4>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span
                                            class="flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                            +
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                                <div
                                    class="mb-6 flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <svg class="fill-current" width="25" height="24" viewBox="0 0 25 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M9.13768 5.60156C7.92435 5.60156 6.94074 6.58517 6.94074 7.79851C6.94074 9.01185 7.92435 9.99545 9.13768 9.99545C10.351 9.99545 11.3346 9.01185 11.3346 7.79851C11.3346 6.58517 10.351 5.60156 9.13768 5.60156ZM5.44074 7.79851C5.44074 5.75674 7.09592 4.10156 9.13768 4.10156C11.1795 4.10156 12.8346 5.75674 12.8346 7.79851C12.8346 9.84027 11.1795 11.4955 9.13768 11.4955C7.09592 11.4955 5.44074 9.84027 5.44074 7.79851ZM5.19577 15.3208C4.42094 16.0881 4.03702 17.0608 3.8503 17.8611C3.81709 18.0034 3.85435 18.1175 3.94037 18.2112C4.03486 18.3141 4.19984 18.3987 4.40916 18.3987H13.7582C13.9675 18.3987 14.1325 18.3141 14.227 18.2112C14.313 18.1175 14.3503 18.0034 14.317 17.8611C14.1303 17.0608 13.7464 16.0881 12.9716 15.3208C12.2153 14.572 11.0231 13.955 9.08367 13.955C7.14421 13.955 5.95202 14.572 5.19577 15.3208ZM4.14036 14.2549C5.20488 13.2009 6.78928 12.455 9.08367 12.455C11.3781 12.455 12.9625 13.2009 14.027 14.2549C15.0729 15.2906 15.554 16.5607 15.7778 17.5202C16.0991 18.8971 14.9404 19.8987 13.7582 19.8987H4.40916C3.22695 19.8987 2.06829 18.8971 2.38953 17.5202C2.6134 16.5607 3.09442 15.2906 4.14036 14.2549ZM15.6375 11.4955C14.8034 11.4955 14.0339 11.2193 13.4153 10.7533C13.7074 10.3314 13.9387 9.86419 14.0964 9.36432C14.493 9.75463 15.0371 9.99545 15.6375 9.99545C16.8508 9.99545 17.8344 9.01185 17.8344 7.79851C17.8344 6.58517 16.8508 5.60156 15.6375 5.60156C15.0371 5.60156 14.493 5.84239 14.0964 6.23271C13.9387 5.73284 13.7074 5.26561 13.4153 4.84371C14.0338 4.37777 14.8034 4.10156 15.6375 4.10156C17.6792 4.10156 19.3344 5.75674 19.3344 7.79851C19.3344 9.84027 17.6792 11.4955 15.6375 11.4955ZM20.2581 19.8987H16.7233C17.0347 19.4736 17.2492 18.969 17.3159 18.3987H20.2581C20.4674 18.3987 20.6323 18.3141 20.7268 18.2112C20.8129 18.1175 20.8501 18.0034 20.8169 17.861C20.6302 17.0607 20.2463 16.088 19.4714 15.3208C18.7379 14.5945 17.5942 13.9921 15.7563 13.9566C15.5565 13.6945 15.3328 13.437 15.0824 13.1891C14.8476 12.9566 14.5952 12.7384 14.3249 12.5362C14.7185 12.4831 15.1376 12.4549 15.5835 12.4549C17.8779 12.4549 19.4623 13.2008 20.5269 14.2549C21.5728 15.2906 22.0538 16.5607 22.2777 17.5202C22.5989 18.8971 21.4403 19.8987 20.2581 19.8987Z"
                                            fill=""></path>
                                    </svg>
                                </div>

                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    إجمالي الحجوزات
                                </p>

                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            <?= $reservations->num_rows ?>
                                        </h4>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span
                                            class="flex items-center gap-1 rounded-full bg-error-50 px-2 py-0.5 text-theme-xs font-medium text-error-600 dark:bg-error-500/15 dark:text-error-500">
                                            -
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                                <div
                                    class="mb-6 flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    <svg class="fill-current" width="25" height="24" viewBox="0 0 25 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M13.4164 2.79175C13.4164 2.37753 13.0806 2.04175 12.6664 2.04175C12.2522 2.04175 11.9164 2.37753 11.9164 2.79175V4.39876C9.94768 4.67329 8.43237 6.36366 8.43237 8.40795C8.43237 10.0954 9.47908 11.6058 11.0591 12.1984L13.7474 13.2066C14.7419 13.5795 15.4008 14.5303 15.4008 15.5925C15.4008 16.9998 14.2599 18.1407 12.8526 18.1407H11.7957C10.7666 18.1407 9.93237 17.3064 9.93237 16.2773C9.93237 15.8631 9.59659 15.5273 9.18237 15.5273C8.76816 15.5273 8.43237 15.8631 8.43237 16.2773C8.43237 18.1348 9.9382 19.6407 11.7957 19.6407H11.9164V21.2083C11.9164 21.6225 12.2522 21.9583 12.6664 21.9583C13.0806 21.9583 13.4164 21.6225 13.4164 21.2083V19.6017C15.3853 19.3274 16.9008 17.6369 16.9008 15.5925C16.9008 13.905 15.8541 12.3946 14.2741 11.8021L11.5858 10.7939C10.5912 10.4209 9.93237 9.47013 9.93237 8.40795C9.93237 7.00063 11.0732 5.85976 12.4806 5.85976H13.5374C14.5665 5.85976 15.4008 6.69401 15.4008 7.72311C15.4008 8.13732 15.7366 8.47311 16.1508 8.47311C16.565 8.47311 16.9008 8.13732 16.9008 7.72311C16.9008 5.86558 15.395 4.35976 13.5374 4.35976H13.4164V2.79175Z"
                                            fill=""></path>
                                    </svg>
                                </div>

                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">إجمالي المبالغ
                                </p>

                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            <?php
                                            $total_amount = 0;
                                            $teachers_stats->data_seek(0);
                                            while ($teacher = $teachers_stats->fetch_assoc()) {
                                                $total_amount += $teacher['total_amount'];
                                            }
                                            echo number_format($total_amount) . ' ';
                                            ?>
                                        </h4>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <span
                                            class="flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-theme-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                            +
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-span-12 mt-6">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-5 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        حجوزات الكتب
                                    </h3>
                                </div>
                                <div class="flex items-center gap-3">
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
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
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
                                                        رقم الطلب</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        اسم الطالب</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الكمية</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        المدرس</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الكتاب</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        السعر</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الإجمالي</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        المدفوع</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الباقي</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        التاريخ</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الإيصال</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الحالة</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center justify-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        حفظ</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center justify-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        حذف</p>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <?php if ($reservations && $reservations->num_rows > 0):
                                            while ($row = $reservations->fetch_assoc()):
                                                $total_price = $row['book_price'] * $row['quantity'];
                                                ?>
                                                <tr>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($row['order_number']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex items-center gap-3">
                                                                <div
                                                                    class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                    <span class="text-xs font-semibold text-brand-500">
                                                                        <?= $row['reservation_id'] ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <span
                                                                        class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                                        <?= htmlspecialchars($row['student_name']) ?>
                                                                    </span>
                                                                    <span
                                                                        class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                        <?= htmlspecialchars($row['grade_name']) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-center">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= $row['quantity'] ?>
                                                        </p>
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
                                                            <?= number_format($total_price, 2) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap text-end">
                                                        <form method="post" class="flex items-center gap-2 justify-end">
                                                            <input type="hidden" name="edit_reservation_id"
                                                                value="<?= $row['reservation_id'] ?>">
                                                            <input type="number" name="amount_paid" step="0.01" min="0"
                                                                max="<?= $total_price ?>" value="<?= $row['amount_paid'] ?>"
                                                                class="dark:bg-dark-900 h-8 w-24 rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                        </form>
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
                                                        <?php if (!empty($row['receipt_image'])): ?>
                                                            <a href="<?= $row['receipt_image'] ?>" target="_blank"
                                                                class="text-blue-500 hover:underline text-gray-700 text-theme-sm dark:text-gray-400">
                                                                عرض الإيصال
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-gray-500">لا يوجد</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <form method="post" enctype="multipart/form-data"
                                                        class="flex items-center gap-2">
                                                        <td class="px-6 py-3 whitespace-nowrap text-center">
                                                            <input type="hidden" name="edit_reservation_id"
                                                                value="<?= $row['reservation_id'] ?>">
                                                            <select name="status" required
                                                                class="dark:bg-dark-900 h-8 w-24 rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                                <option value="pending" <?= $row['status'] == 'pending' ? 'selected' : '' ?>>معلق</option>
                                                                <option value="approved" <?= $row['status'] == 'approved' ? 'selected' : '' ?>>موافق</option>
                                                                <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                                                                <option value="returned" <?= $row['status'] == 'returned' ? 'selected' : '' ?>>معاد</option>
                                                            </select>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap text-center">
                                                            <button type="submit"
                                                                class="btn btn-primary btn-sm text-gray-700 text-theme-sm dark:text-gray-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19"
                                                                    fill="currentColor" viewBox="0 0 16 16">
                                                                    <path d="M11 2H9v3h2z" />
                                                                    <path
                                                                        d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z" />
                                                                </svg>
                                                            </button>
                                                        </td>
                                                    </form>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <a href="?delete=<?= $row['reservation_id'] ?>"
                                                                onclick="return confirm('تأكيد الحذف؟');"
                                                                class="cursor-pointer hover:fill-error-500 dark:hover:fill-error-500 fill-gray-700 dark:fill-gray-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-x-octagon text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M4.54.146A.5.5 0 0 1 4.893 0h6.214a.5.5 0 0 1 .353.146l4.394 4.394a.5.5 0 0 1 .146.353v6.214a.5.5 0 0 1-.146.353l-4.394 4.394a.5.5 0 0 1-.353.146H4.893a.5.5 0 0 1-.353-.146L.146 11.46A.5.5 0 0 1 0 11.107V4.893a.5.5 0 0 1 .146-.353zM5.1 1 1 5.1v5.8L5.1 15h5.8l4.1-4.1V5.1L10.9 1z" />
                                                                    <path
                                                                        d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708" />
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; else: ?>
                                            <tr>
                                                <td colspan="14" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                    لا توجد بيانات للعرض
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>


        </div>
        </main>
    </div>
    </div>
    <script defer src="../assets/js/bundle.js"></script>

</body>

</html>
<?php $mysqli->close(); ?>