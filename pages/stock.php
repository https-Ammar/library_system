<?php
require_once '../config/db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Books SET deleted_at = NOW() WHERE book_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ./stock.php');
    exit;
}

if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    $stmt = $mysqli->prepare("UPDATE Books SET deleted_at = NULL WHERE book_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ./stock.php');
    exit;
}

if (isset($_POST['update'])) {
    $id = intval($_POST['book_id']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
    $grade_id = intval($_POST['grade_id']);

    $stmt = $mysqli->prepare("UPDATE Books SET quantity = ?, price = ?, grade_id = ? WHERE book_id = ?");
    $stmt->bind_param("idii", $quantity, $price, $grade_id, $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ./stock.php');
    exit;
}

$grades_query = "SELECT * FROM Grades";
$grades_result = $mysqli->query($grades_query);

$query = "
    SELECT 
        b.book_id, b.title, b.quantity, b.price, b.image_url, b.created_at, b.grade_id,
        g.name AS grade_name,
        t.name AS teacher_name,
        COUNT(br.reservation_id) AS student_count
    FROM Books b
    LEFT JOIN Grades g ON b.grade_id = g.grade_id
    LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
    LEFT JOIN BookReservations br ON b.book_id = br.book_id AND br.deleted_at IS NULL
    WHERE b.deleted_at IS NULL
    GROUP BY b.book_id
    ORDER BY b.created_at DESC
";
$result = $mysqli->query($query);

$finished_query = "
    SELECT 
        b.book_id, b.title, b.quantity, b.price, b.image_url, b.created_at, b.deleted_at, b.grade_id,
        g.name AS grade_name,
        t.name AS teacher_name
    FROM Books b
    LEFT JOIN Grades g ON b.grade_id = g.grade_id
    LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
    WHERE b.deleted_at IS NOT NULL
    ORDER BY b.deleted_at DESC
";
$finished_result = $mysqli->query($finished_query);

$total_money_query = "SELECT SUM(quantity * price) as total_money FROM Books WHERE deleted_at IS NULL";
$total_money_result = $mysqli->query($total_money_query);
$total_money = $total_money_result->fetch_assoc();

$stats_query = "
    SELECT 
        SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) as active_books,
        SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as finished_books,
        SUM(quantity) as total_quantity,
        (SELECT SUM(quantity) FROM Books WHERE deleted_at IS NULL) as available_quantity,
        (SELECT SUM(quantity) FROM Books WHERE deleted_at IS NOT NULL) as finished_quantity
    FROM Books
";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المخزون</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        .edit-modal {
            display: none;
        }

        .edit-modal.active {
            display: flex;
        }

        .student-count {
            background-color: #4CAF50;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-right: 5px;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false, 'editModalOpen': false, 'currentBook': null }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
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
                                            <?= $stats['available_quantity'] ?? 0 ?>
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
                                            <?= $stats['finished_quantity'] ?? 0 ?>
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
                                            <?= isset($total_money['total_money']) ? number_format((float) $total_money['total_money'], 2) : '0.00' ?>
                                            <sub style="font-size: x-small;">EG</sub>
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
                                        إدارة الكتب
                                    </h3>
                                </div>

                                <div class="flex items-center gap-3">
                                    <button onclick="openTab(event, 'available')"
                                        class="tablinks text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
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
                                                            المدرس</p>
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
                                                            الطلاب المسجلين</p>
                                                    </div>
                                                </th>
                                                <th class="px-6 py-3 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <p
                                                            class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                            إجراءات</p>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody class="py-3 divide-y divide-gray-100 dark:divide-gray-800">
                                            <?php if ($result && $result->num_rows > 0): ?>
                                                <?php while ($product = $result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                                                    <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                                                            alt="صورة الكتاب">
                                                                    <?php else: ?>
                                                                        <img src="src/images/product/default.jpg"
                                                                            alt="صورة افتراضية">
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p
                                                                    class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                                    <?= htmlspecialchars($product['title']) ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($product['grade_name'] ?? '-') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($product['teacher_name'] ?? '-') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= (int) $product['quantity'] ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <a href="book_students.php?book_id=<?= $product['book_id'] ?>"
                                                                    class="flex items-center text-blue-600 hover:underline text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    عدد الطلاب (<?= $product['student_count'] ?>)
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center justify-center gap-3">
                                                                <button
                                                                    @click="editModalOpen = true; currentBook = <?= htmlspecialchars(json_encode($product)) ?>"
                                                                    class="edit-btn text-blue-600 hover:underline">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                                        height="20" fill="currentColor"
                                                                        class="bi bi-transparency text-gray-700 text-theme-sm dark:text-gray-400"
                                                                        viewBox="0 0 16 16">
                                                                        <path
                                                                            d="M0 6.5a6.5 6.5 0 0 1 12.346-2.846 6.5 6.5 0 1 1-8.691 8.691A6.5 6.5 0 0 1 0 6.5m5.144 6.358a5.5 5.5 0 1 0 7.714-7.714 6.5 6.5 0 0 1-7.714 7.714m-.733-1.269q.546.226 1.144.33l-1.474-1.474q.104.597.33 1.144m2.614.386a5.5 5.5 0 0 0 1.173-.242L4.374 7.91a6 6 0 0 0-.296 1.118zm2.157-.672q.446-.25.838-.576L5.418 6.126a6 6 0 0 0-.587.826zm1.545-1.284q.325-.39.576-.837L6.953 4.83a6 6 0 0 0-.827.587l4.6 4.602Zm1.006-1.822q.183-.562.242-1.172L9.028 4.078q-.58.096-1.118.296l3.823 3.824Zm.186-2.642a5.5 5.5 0 0 0-.33-1.144 5.5 5.5 0 0 0-1.144-.33z">
                                                                        </path>
                                                                    </svg>
                                                                </button>
                                                                <a href="?delete=<?= $product['book_id'] ?>"
                                                                    onclick="return confirm('هل تريد حذف هذا الكتاب؟');"
                                                                    class="text-red-600 hover:underline mr-2">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                                        height="20" fill="currentColor"
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
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7"
                                                        class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                        لا توجد بيانات للعرض
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div id="finished" class="tabcontent" style="display: none;">
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
                                                            المدرس</p>
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
                                                <th class="px-6 py-3 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <p
                                                            class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                            إجراءات</p>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody class="py-3 divide-y divide-gray-100 dark:divide-gray-800">
                                            <?php if ($finished_result && $finished_result->num_rows > 0): ?>
                                                <?php while ($book = $finished_result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                                                    <?php if (!empty($book['image_url']) && file_exists($book['image_url'])): ?>
                                                                        <img src="<?= htmlspecialchars($book['image_url']) ?>"
                                                                            alt="صورة الكتاب">
                                                                    <?php else: ?>
                                                                        <img src="src/images/product/default.jpg"
                                                                            alt="لا توجد صورة">
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p
                                                                    class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                                    <?= htmlspecialchars($book['title']) ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($book['grade_name'] ?? '-') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($book['teacher_name'] ?? '-') ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= htmlspecialchars($book['quantity']) ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= number_format((float) $book['price'], 2) ?> <sub
                                                                        style="font-size: x-small;">EG</sub>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= isset($book['deleted_at']) ? date('Y-m-d', strtotime($book['deleted_at'])) : '-' ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <a href="?restore=<?= $book['book_id'] ?>"
                                                                onclick="return confirm('هل تريد استعادة هذا الكتاب؟');"
                                                                class="text-blue-600 hover:underline">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-reply-all text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M8.098 5.013a.144.144 0 0 1 .202.134V6.3a.5.5 0 0 0 .5.5c.667 0 2.013.005 3.3.822.984.624 1.99 1.76 2.595 3.876-1.02-.983-2.185-1.516-3.205-1.799a8.7 8.7 0 0 0-1.921-.306 7 7 0 0 0-.798.008h-.013l-.005.001h-.001L8.8 9.9l-.05-.498a.5.5 0 0 0-.45.498v1.153c0 .108-.11.176-.202.134L4.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028zM9.3 10.386q.102 0 .223.006c.434.02 1.034.086 1.7.271 1.326.368 2.896 1.202 3.94 3.08a.5.5 0 0 0 .933-.305c-.464-3.71-1.886-5.662-3.46-6.66-1.245-.79-2.527-.942-3.336-.971v-.66a1.144 1.144 0 0 0-1.767-.96l-3.994 2.94a1.147 1.147 0 0 0 0 1.946l3.994 2.94a1.144 1.144 0 0 0 1.767-.96z" />
                                                                    <path
                                                                        d="M5.232 4.293a.5.5 0 0 0-.7-.106L.54 7.127a1.147 1.147 0 0 0 0 1.946l3.994 2.94a.5.5 0 1 0 .593-.805L1.114 8.254l-.042-.028a.147.147 0 0 1 0-.252l.042-.028 4.012-2.954a.5.5 0 0 0 .106-.699" />
                                                                </svg>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7"
                                                        class="text-center py-4 text-gray-500 dark:text-gray-400">
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

                    <div x-show="editModalOpen" x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                        class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999"
                        :class="{'hidden': !editModalOpen}">

                        <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                            @click="editModalOpen = false">
                        </div>

                        <div @click.outside="editModalOpen = false"
                            class="no-scrollbar relative w-full max-w-[700px] mx-auto overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">

                            <div class="px-2">
                                <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                                    تعديل الكتب الدراسية
                                </h4>
                                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">
                                    إدارة الكتب الدراسية بسهولة
                                </p>
                            </div>

                            <form method="POST" class="flex flex-col">
                                <input type="hidden" name="book_id" x-model="currentBook.book_id">

                                <div class="custom-scrollbar overflow-y-auto px-2">
                                    <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">

                                        <div class="sm:col-span-2">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                الكمية
                                            </label>
                                            <input type="number" name="quantity" x-model="currentBook.quantity" required
                                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 
                               text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 
                               focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 
                               dark:text-white/90">
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                السعر
                                            </label>
                                            <input type="number" step="0.01" name="price" x-model="currentBook.price"
                                                required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 
                               text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 
                               focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 
                               dark:text-white/90">
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                الصف
                                            </label>
                                            <select name="grade_id" x-model="currentBook.grade_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 
                               text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 
                               focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 
                               dark:text-white/90">
                                                <?php $grades_result->data_seek(0); ?>
                                                <?php while ($grade = $grades_result->fetch_assoc()): ?>
                                                    <option value="<?= $grade['grade_id'] ?>">
                                                        <?= htmlspecialchars($grade['name']) ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                    <div class="flex items-center w-full gap-3 sm:w-auto">
                                        <button type="button" @click="editModalOpen = false" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 
                           text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 
                           dark:bg-gray-800 dark:text-gray-400 sm:w-auto">
                                            إلغاء
                                        </button>
                                        <button type="submit" name="update" class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm 
                           font-medium text-white hover:bg-brand-600 sm:w-auto">
                                            حفظ التغييرات
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script defer src="../assets/js/bundle.js"></script>
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

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector('.tablinks').click();
        });
    </script>
</body>

</html>
<?php $mysqli->close(); ?>