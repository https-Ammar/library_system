<?php
require_once '../config/db.php';



session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/signin.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("معرف المدرس غير موجود");
}


$teacher_id = intval($_GET['id']);

$stmt = $mysqli->prepare("SELECT * FROM Teachers WHERE teacher_id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("لم يتم العثور على المدرس");
}

if (isset($_POST['delete_book'])) {
    $book_id = intval($_POST['book_id']);
    $delete_stmt = $mysqli->prepare("UPDATE Books SET deleted_at = NOW() WHERE book_id = ?");
    $delete_stmt->bind_param("i", $book_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    header("Location: ?id=" . $teacher_id);
    exit();
}

$stmt_books = $mysqli->prepare("
    SELECT 
        b.book_id, 
        b.title, 
        b.image_url, 
        b.price, 
        b.quantity,
        b.created_at,
        g.name AS grade_name,
        COUNT(br.reservation_id) AS total_reservations,
        SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) AS approved_reservations,
        SUM(CASE WHEN br.status = 'pending' THEN 1 ELSE 0 END) AS pending_reservations,
        SUM(CASE WHEN br.status = 'returned' THEN 1 ELSE 0 END) AS returned_reservations,
        SUM(br.amount_paid) AS total_amount_paid,
        SUM(br.amount_due) AS total_amount_due
    FROM Books b
    LEFT JOIN Grades g ON b.grade_id = g.grade_id
    LEFT JOIN BookReservations br ON b.book_id = br.book_id AND br.deleted_at IS NULL
    WHERE b.teacher_id = ? AND b.deleted_at IS NULL
    GROUP BY b.book_id
");
$stmt_books->bind_param("i", $teacher_id);
$stmt_books->execute();
$books = $stmt_books->get_result();
$stmt_books->close();

$stmt_finished_books = $mysqli->prepare("
    SELECT 
        b.book_id, 
        b.title, 
        b.image_url, 
        b.price, 
        b.quantity,
        b.created_at,
        b.deleted_at,
        g.name AS grade_name,
        t.name AS teacher_name
    FROM Books b
    LEFT JOIN Grades g ON b.grade_id = g.grade_id
    LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
    WHERE b.teacher_id = ? AND b.deleted_at IS NOT NULL
");
$stmt_finished_books->bind_param("i", $teacher_id);
$stmt_finished_books->execute();
$finished_books = $stmt_finished_books->get_result();
$stmt_finished_books->close();

$stmt_grades = $mysqli->prepare("
    SELECT g.name 
    FROM TeacherGrades tg 
    JOIN Grades g ON tg.grade_id = g.grade_id 
    WHERE tg.teacher_id = ? AND g.deleted_at IS NULL
");
$stmt_grades->bind_param("i", $teacher_id);
$stmt_grades->execute();
$grades = $stmt_grades->get_result();
$stmt_grades->close();

$stats_stmt = $mysqli->prepare("
    SELECT 
        COUNT(b.book_id) AS total_books,
        SUM(b.quantity) AS total_quantity,
        SUM(b.price * b.quantity) AS total_value,
        SUM(CASE WHEN b.quantity > 0 THEN 1 ELSE 0 END) AS available_books,
        SUM(CASE WHEN b.quantity = 0 THEN 1 ELSE 0 END) AS finished_books,
        COUNT(br.reservation_id) AS total_reservations,
        SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) AS approved_reservations,
        SUM(CASE WHEN br.status = 'pending' THEN 1 ELSE 0 END) AS pending_reservations,
        SUM(CASE WHEN br.status = 'returned' THEN 1 ELSE 0 END) AS returned_reservations,
        SUM(br.amount_paid) AS total_amount_paid,
        SUM(br.amount_due) AS total_amount_due
    FROM Books b
    LEFT JOIN BookReservations br ON b.book_id = br.book_id AND br.deleted_at IS NULL
    WHERE b.teacher_id = ? AND b.deleted_at IS NULL
");
$stats_stmt->bind_param("i", $teacher_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($teacher['name']) ?></title>
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
    x-data="{ page: 'saas', loaded: true, darkMode: false, stickyMenu: false, sidebarToggle: false, scrollTop: false }"
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

                    <div class="mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800">
                        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                            <div class="flex w-full flex-col items-center gap-6 xl:flex-row">
                                <div
                                    class="h-20 w-20 overflow-hidden rounded-full border border-gray-200 dark:border-gray-800">
                                    <?php if (!empty($teacher['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($teacher['image_url']) ?>" alt="user">
                                    <?php else: ?>
                                   
                                   <div class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                                                                                    <span class="text-xs font-semibold text-brand-500">
                                                                     <?= !empty($teacher['name']) ? mb_substr($teacher['name'], 0, 1) : '-' ?>                                                                  </span>
                                                                                                                            </div>
                                   
                                                             
                                                                                

                                   
                                    <?php endif; ?>
                                </div>
                                <div class="order-3 xl:order-2">
                                    <h4
                                        class="mb-2 text-center text-lg font-semibold text-gray-800 xl:text-left dark:text-white/90">
                                        <?= htmlspecialchars($teacher['name']) ?>
                                    </h4>
                                    <div
                                        class="flex flex-col items-center gap-1 text-center xl:flex-row xl:gap-3 xl:text-left">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($teacher['subject'] ?? '—') ?>
                                        </p>
                                        <div class="hidden h-3.5 w-px bg-gray-300 xl:block dark:bg-gray-700"></div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($teacher['region'] ?? '—') ?> /
                                            <?= htmlspecialchars($teacher['phone'] ?? '—') ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="order-2 flex grow items-center gap-2 xl:order-3 xl:justify-end">
                                    <?php if ($grades->num_rows > 0): ?>
                                        <?php while ($grade = $grades->fetch_assoc()): ?>
                                            <button
                                                class="p-3 shadow-theme-xs flex items-center justify-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                                <?= htmlspecialchars($grade['name']) ?>
                                            </button>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-theme-sm text-gray-500 dark:text-gray-400 text-center">لا توجد مراحل
                                            دراسية
                                            مرتبطة</p>


                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    الكتب المتاحة
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['available_books'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    الكتب المنتهية
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['finished_books'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">الكمية الإجمالية</p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['total_quantity'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">القيمة الإجمالية</p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= number_format($stats['total_value'] ?? 0, 2) ?> <sub
                                                style="font-size: x-small;">EG</sub>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 mt-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    إجمالي الحجوزات
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['total_reservations'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    الحجوزات المقبولة
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['approved_reservations'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">الحجوزات المعلقة</p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['pending_reservations'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">الحجوزات المرتجعة</p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= $stats['returned_reservations'] ?? 0 ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 mt-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    إجمالي المبالغ المحصلة
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= number_format($stats['total_amount_paid'] ?? 0, 2) ?> <sub
                                                style="font-size: x-small;">EG</sub>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                                    إجمالي المبالغ المستحقة
                                </p>
                                <div class="mt-3 flex items-end justify-between">
                                    <div>
                                        <h4 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                            <?= number_format($stats['total_amount_due'] ?? 0, 2) ?> <sub
                                                style="font-size: x-small;">EG</sub>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 xl:col-span-7 mt-6">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pt-4 pb-3 sm:px-6 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-2 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        الكتب المرتبطة
                                    </h3>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button onclick="openTab(event, 'available')"
                                        class="tablinks text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 active">
                                        الكتب المتاحة
                                    </button>

                                    <button onclick="openTab(event, 'finished')"
                                        class="tablinks text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                        الكتب المنتهية
                                    </button>
                                </div>
                            </div>

                            <div class="max-w-full overflow-x-auto custom-scrollbar">
                                <div id="available" class="tabcontent" style="display: block;">
                                    <?php if ($books->num_rows > 0): ?>
                                        <table class="min-w-full">
                                            <thead class="border-gray-100 border-y dark:border-gray-800">
                                                <tr>
                                                    <th class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                        <div class="flex items-center">
                                                            <p
                                                                class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                                الصورة</p>
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
                                                                الصف</p>
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
                                                                السعر</p>
                                                        </div>
                                                    </th>
                                                    <th class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <p
                                                                class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                                الحجوزات</p>
                                                        </div>
                                                    </th>
                                                    <th class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <p
                                                                class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                                المبلغ</p>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody class="py-3 divide-y divide-gray-100 dark:divide-gray-800">
                                                <?php while ($book = $books->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <div class="flex items-center gap-3">
                                                                    <?php if (!empty($book['image_url'])): ?>
                                                                        <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                                                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                                                                alt="صورة الكتاب">
                                                                        </div>
                                                                    <?php else: ?>
                                                                   
                                                           
                                                           
                                                           <div class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                                                                                    <span class="text-xs font-semibold text-brand-500">
                                                                     <?= htmlspecialchars(mb_substr($book['title'] ?? '—', 0, 1)) ?>                                                                    </span>
                                                                                                                            </div>
                                                              
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p
                                                                    class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                                    <?= htmlspecialchars($book['title'] ?? '—') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($book['grade_name'] ?? '—') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= isset($book['quantity']) ? htmlspecialchars($book['quantity']) : '0' ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= isset($book['price']) ? htmlspecialchars($book['price']) . ' ' : '—' ?>
                                                                    <sub style="font-size: x-small;">EG</sub>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    (<?= $book['approved_reservations'] ?? 0 ?> مقبولة /
                                                                    <?= $book['pending_reservations'] ?? 0 ?> معلقة /
                                                                    <?= $book['returned_reservations'] ?? 0 ?> مرتجعة)
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= number_format($book['total_amount_paid'] ?? 0, 2) ?>
                                                                    <sub style="font-size: x-small;">EG</sub>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                لا توجد بيانات للعرض
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </div>

                                <div id="finished" class="tabcontent" style="display: none;">
                                    <?php if ($finished_books->num_rows > 0): ?>
                                        <table class="min-w-full">
                                            <thead class="border-gray-100 border-y dark:border-gray-800">
                                                <tr>
                                                    <th class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                        <div class="flex items-center">
                                                            <p
                                                                class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                                الصورة</p>
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
                                                                الصف</p>
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
                                                                السعر</p>
                                                        </div>
                                                    </th>
                                                    <th class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <p
                                                                class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                                تاريخ الحذف</p>
                                                        </div>
                                                    </th>

                                                </tr>
                                            </thead>

                                            <tbody class="py-3 divide-y divide-gray-100 dark:divide-gray-800">
                                                <?php while ($book = $finished_books->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <div class="flex items-center gap-3">
                                                                    <?php if (!empty($book['image_url'])): ?>
                                                                        <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                                                            <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                                                                alt="صورة الكتاب">
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">لا توجد صورة</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p
                                                                    class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                                    <?= htmlspecialchars($book['title'] ?? '—') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($book['grade_name'] ?? '—') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= isset($book['quantity']) ? htmlspecialchars($book['quantity']) : '0' ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= isset($book['price']) ? htmlspecialchars($book['price']) . ' ' : '—' ?>
                                                                    <sub style="font-size: x-small;">EG</sub>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= !empty($book['deleted_at']) ? date('Y-m-d', strtotime($book['deleted_at'])) : '—' ?>
                                                                </p>
                                                            </div>
                                                        </td>

                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p class="text-theme-sm text-gray-500 dark:text-gray-400 text-center">لا توجد كتب
                                            منتهية
                                            للعرض</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function openTab(evt, tabName) {
                            var i, tabcontent, tablinks;

                            tabcontent = document.getElementsByClassName("tabcontent");
                            for (i = 0; i < tabcontent.length; i++) {
                                tabcontent[i].style.display = "none";
                            }

                            tablinks = document.getElementsByClassName("tablinks");
                            for (i = 0; i < tablinks.length; i++) {
                                tablinks[i].className = tablinks[i].className.replace(" active", "");
                            }

                            document.getElementById(tabName).style.display = "block";
                            evt.currentTarget.className += " active";
                        }
                    </script>


<style>
    
    .h-20.w-20.overflow-hidden.rounded-full.border.border-gray-200.dark\:border-gray-800 {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dde9ff;
}

.px-6.py-4.flex.items-center {
    justify-content: space-between;
}
</style>

                </div>
            </main>
        </div>
    </div>

    <script defer src="../assets/js/bundle.js"></script>
</body>

</html>

<?php $mysqli->close(); ?>