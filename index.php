<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ./auth/signin.php");
    exit();
}

require_once './config/db.php';

$today = date('Y-m-d');

$result = $mysqli->query("SELECT COUNT(*) AS today_orders FROM BookReservations WHERE DATE(created_at) = '$today' AND deleted_at IS NULL");
$today_orders = $result->fetch_assoc()['today_orders'] ?? 0;

$stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_paid), 0) AS today_revenue FROM BookReservations WHERE DATE(created_at) = ? AND deleted_at IS NULL AND status IN ('approved', 'returned')");
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->bind_result($today_revenue);
$stmt->fetch();
$stmt->close();

$history = [];
$result = $mysqli->query("SELECT DATE(created_at) AS date, COUNT(*) AS orders_count, SUM(CASE WHEN status IN ('approved', 'returned') THEN amount_paid ELSE 0 END) AS revenue FROM BookReservations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND deleted_at IS NULL GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC");
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$result = $mysqli->query("SELECT COUNT(*) AS total_orders FROM BookReservations WHERE deleted_at IS NULL");
$total_orders = $result->fetch_assoc()['total_orders'] ?? 0;

$stmt = $mysqli->prepare("SELECT COUNT(*) AS total_books_sold, COALESCE(SUM(amount_paid), 0) AS total_revenue FROM BookReservations WHERE deleted_at IS NULL AND status IN ('approved', 'returned')");
$stmt->execute();
$stmt->bind_result($total_books_sold, $total_revenue);
$stmt->fetch();
$stmt->close();

$result = $mysqli->query("SELECT status, COUNT(*) AS count FROM BookReservations WHERE deleted_at IS NULL GROUP BY status");
$order_status_counts = [
    'pending' => 0,
    'approved' => 0,
    'cancelled' => 0,
    'returned' => 0,
];
while ($row = $result->fetch_assoc()) {
    $order_status_counts[$row['status']] = $row['count'];
}

$result = $mysqli->query("SELECT SUM(amount) AS total_expenses FROM Expenses WHERE deleted_at IS NULL");
$total_expenses = $result->fetch_assoc()['total_expenses'] ?? 0;

$result = $mysqli->query("SELECT COUNT(*) AS total_students FROM Students WHERE deleted_at IS NULL");
$total_students = $result->fetch_assoc()['total_students'] ?? 0;

$result = $mysqli->query("SELECT COUNT(*) AS total_users FROM Users WHERE deleted_at IS NULL");
$total_users = $result->fetch_assoc()['total_users'] ?? 0;

$daily_records = [];
$result = $mysqli->query("SELECT 
    br.reservation_id,
    s.name AS student_name,
    s.phone AS student_phone,
    g.name AS grade_name,
    t.name AS teacher_name,
    t.phone AS teacher_phone,
    b.title AS book_title,
    b.price AS book_price,
    br.amount_paid,
    br.amount_due,
    br.status,
    br.created_at
FROM BookReservations br
JOIN Students s ON br.student_id = s.student_id
JOIN Books b ON br.book_id = b.book_id
LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
LEFT JOIN Grades g ON s.grade_id = g.grade_id
WHERE DATE(br.created_at) = '$today' AND br.deleted_at IS NULL
ORDER BY br.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $daily_records[] = $row;
}

$all_records = [];
$result = $mysqli->query("SELECT 
    br.reservation_id,
    s.name AS student_name,
    s.phone AS student_phone,
    g.name AS grade_name,
    t.name AS teacher_name,
    t.phone AS teacher_phone,
    b.title AS book_title,
    b.price AS book_price,
    br.amount_paid,
    br.amount_due,
    br.status,
    br.created_at
FROM BookReservations br
JOIN Students s ON br.student_id = s.student_id
JOIN Books b ON br.book_id = b.book_id
LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
LEFT JOIN Grades g ON s.grade_id = g.grade_id
WHERE br.deleted_at IS NULL
ORDER BY br.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $all_records[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="./assets/css/main.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif !important;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">
    <div x-show="loaded" x-transition.opacity
        x-init="window.addEventListener('DOMContentLoaded', () => {setTimeout(() => loaded = false, 500)})"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-white dark:bg-black">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent">
        </div>
    </div>

    <div class="flex h-screen overflow-hidden">
        <?php require('./includes/header.php'); ?>
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <div :class="sidebarToggle ? 'block xl:hidden' : 'hidden'"
                class="fixed z-50 h-screen w-full bg-gray-900/50"></div>
            <main>
                <?php require('./includes/nav.php'); ?>
                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">

                        <div class="mb-8 flex flex-col justify-between gap-4  flex-row items-center">

                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">الإحصائيات</h3>

                            <div>
                                <a href="./pages/history.php"
                                    class="text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                    <svg class="fill-white stroke-current dark:fill-gray-800" width="20" height="20"
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
                                    <span class="hidden sm:block">عرض السجل الكامل</span>
                                </a>
                            </div>
                        </div>


                        <div
                            class="grid rounded-2xl border border-gray-200 bg-white sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800 dark:bg-gray-900">
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r xl:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">عدد الطلبات اليوم</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= $today_orders ?>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 xl:border-r xl:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">إيرادات اليوم</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= number_format($today_revenue, 2) ?> <sub
                                            style="font-size: x-small;">EG</sub>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r sm:border-b-0 dark:border-gray-800">
                                <div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">إجمالي المصروفات</span>
                                    <div class="mt-2 flex items-end gap-3">
                                        <h4
                                            class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            <?= number_format($total_expenses, 2) ?>
                                            <sub style="font-size: x-small;">EG</sub>
                                        </h4>
                                        <div>
                                            <span
                                                class="bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r sm:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">إجمالي الإيرادات</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= number_format($total_revenue, 2) ?>
                                        <sub style="font-size: x-small;">EG</sub>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-3 grid rounded-2xl border border-gray-200 bg-white sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800 dark:bg-gray-900">
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r xl:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">الطلبات المعلقة</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= $order_status_counts['pending'] ?>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 xl:border-r xl:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">الطلبات الموافق عليها</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= $order_status_counts['approved'] ?>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r sm:border-b-0 dark:border-gray-800">
                                <div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">الطلبات الملغاة</span>
                                    <div class="mt-2 flex items-end gap-3">
                                        <h4
                                            class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            <?= $order_status_counts['cancelled'] ?>
                                        </h4>
                                        <div>
                                            <span
                                                class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="border-b border-gray-200 px-6 py-5 sm:border-r sm:border-b-0 dark:border-gray-800">
                                <span class="text-sm text-gray-500 dark:text-gray-400">الطلبات المرتجعة</span>
                                <div class="mt-2 flex items-end gap-3">
                                    <h4
                                        class="text-title-xs sm:text-title-sm font-bold text-gray-800 dark:text-white/90">
                                        <?= $order_status_counts['returned'] ?>
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            class="flex items-center gap-5 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/3">
                            <div
                                class="inline-flex h-16 w-16 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" width="28" height="28"
                                    viewBox="0 0 28 28" fill="none">
                                    <path
                                        d="M14.0003 24.5898V24.5863M14.0003 12.8684V24.5863M9.06478 16.3657V10.6082M18.9341 5.67497C18.9341 5.67497 12.9204 8.68175 9.06706 10.6084M23.5913 8.27989C23.7686 8.55655 23.8679 8.88278 23.8679 9.2241V18.7779C23.8679 19.4407 23.4934 20.0467 22.9005 20.3431L14.7834 24.4015C14.537 24.5248 14.2686 24.5864 14.0003 24.5863M23.5913 8.27989L14.7834 12.6837C14.2908 12.93 13.7109 12.93 13.2182 12.6837L4.41037 8.27989M23.5913 8.27989C23.4243 8.01927 23.1881 7.80264 22.9005 7.65884L14.7834 3.60044C14.2908 3.35411 13.7109 3.35411 13.2182 3.60044L5.10118 7.65884C4.81359 7.80264 4.57737 8.01927 4.41037 8.27989M4.41037 8.27989C4.23309 8.55655 4.13379 8.88278 4.13379 9.2241V18.7779C4.13379 19.4407 4.5083 20.0467 5.10118 20.3431L13.2182 24.4015C13.4644 24.5246 13.7324 24.5862 14.0003 24.5863"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-800 dark:text-white/90"><?= $total_orders ?>
                                </h3>
                                <p class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                                    عدد الطلبات الكلي
                                    <span
                                        class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium">+</span>
                                </p>
                            </div>
                        </article>
                        <article
                            class="flex items-center gap-5 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/3">
                            <div
                                class="inline-flex h-16 w-16 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                                <svg xmlns="http://www.w3.org/2000/svg" width="29" height="28" viewBox="0 0 29 28"
                                    fill="none">
                                    <path
                                        d="M7.04102 4.66667H15.791C16.7575 4.66667 17.541 5.45017 17.541 6.41667V20.4167M17.541 20.4167V8.33004H20.4036C20.9846 8.33004 21.5277 8.61836 21.8532 9.09958L24.8239 13.4917C24.9171 13.6295 24.9897 13.7792 25.0402 13.9359M17.541 20.4167H17.5495M17.541 20.4167H11.5592M3.54102 20.4167H6.17451M25.1243 20.4167V14.4721C25.1243 14.2891 25.0956 14.1082 25.0402 13.9359M25.9993 20.4167H22.9342M12.8743 20.4167H17.5495M17.541 13.9359H25.0402M5.29102 9.04167H10.541M10.541 13.4167H3.54102M17.5495 20.4167C17.6595 19.026 18.8229 17.9317 20.2418 17.9317C21.6608 17.9317 22.8242 19.026 22.9342 20.4167M17.5495 20.4167C17.5439 20.4879 17.541 20.5599 17.541 20.6325C17.541 22.1241 18.7502 23.3333 20.2418 23.3333C21.7335 23.3333 22.9427 22.1241 22.9427 20.6325C22.9427 20.5599 22.9398 20.4879 22.9342 20.4167M11.5592 20.4167C11.5648 20.4879 11.5677 20.5599 11.5677 20.6325C11.5677 22.1241 10.3585 23.3333 8.86685 23.3333C7.37522 23.3333 6.16602 22.1241 6.16602 20.6325C6.16602 20.5599 6.16888 20.4879 6.17451 20.4167"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                                    <?= $total_books_sold ?>
                                </h3>
                                <p class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                                    إجمالي الكتب المباعة
                                    <span
                                        class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium">+</span>
                                </p>
                            </div>
                        </article>
                        <article
                            class="flex items-center gap-5 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/3">
                            <div
                                class="inline-flex h-16 w-16 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90">
                                <svg class="h-7 w-7" width="29" height="28" viewBox="0 0 29 28" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M5.625 9.33333L3 9.33333M4.75 14H3M3.875 18.6667H3M9.90222 22.3117H23.0071C23.9027 22.3117 24.6537 21.6356 24.7475 20.7449L26.129 7.62071C26.2378 6.58744 25.4276 5.6875 24.3887 5.6875H11.2838C10.3882 5.6875 9.63716 6.36364 9.5434 7.25429L8.16184 20.3785C8.05307 21.4118 8.86324 22.3117 9.90222 22.3117ZM16.4622 5.6875H19.5793L18.7043 11.508H15.5872L16.4622 5.6875Z"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                                    <?= $total_students ?>
                                </h3>
                                <p class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                                    عدد الطلاب
                                    <span
                                        class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium">+</span>
                                </p>
                            </div>
                        </article>
                    </div>

                    <div
                        class="mt-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90"></h3>
                            <p class="flex items-center gap-3 text-gray-500 dark:text-gray-400 justify-between">
                                السجل اليومي
                                <span
                                    class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium"><?= date('Y-m-d') ?></span>
                            </p>
                        </div>
                        <div class="custom-scrollbar overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-900">

                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            اسم الطالب</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            رقم الهاتف</th>

                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المدرس</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            اسم الكتاب</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            السعر</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المبلغ المدفوع</th>

                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المبلغ المتبقي</th>

                                        <!-- <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            تاريخ الحجز</th> -->
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            وقت الحجز</th>


                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            الحالة</th>
                                    </tr>

                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <?php foreach ($daily_records as $record): ?>
                                        <tr>








                                            <td class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex items-center gap-3">
                                                        <div
                                                            class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                            <span class="text-xs font-semibold text-brand-500">
                                                                <?= htmlspecialchars($record['reservation_id'] ?? '') ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <span
                                                                class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                                <?= htmlspecialchars($record['student_name'] ?? '') ?>
                                                            </span>
                                                            <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                <?= htmlspecialchars($record['grade_name'] ?? 'غير محدد') ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>


                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= htmlspecialchars($record['student_phone'] ?? 'غير متوفر') ?>
                                            </td>

                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= htmlspecialchars($record['teacher_name'] ?? 'غير محدد') ?>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= htmlspecialchars($record['book_title'] ?? '') ?>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= number_format($record['book_price'] ?? 0, 2) ?> <sub
                                                    style="font-size: x-small;">EG</sub>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= number_format($record['amount_paid'] ?? 0, 2) ?> <sub
                                                    style="font-size: x-small;">EG</sub>
                                            </td>
                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= number_format($record['amount_due'] ?? 0, 2) ?> <sub
                                                    style="font-size: x-small;">EG</sub>
                                            </td>



                                            <!-- <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= isset($record['created_at']) ? date('Y-m-d', strtotime($record['created_at'])) : '' ?>
                                            </td> -->
                                            <td
                                                class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                <?= isset($record['created_at']) ? date('h:i A', strtotime($record['created_at'])) : '' ?>
                                            </td>



                                            <td class="px-6 py-4 text-left">
                                                <?php
                                                $status = $record['status'] ?? 'pending';
                                                $status_class = 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-500';

                                                if ($status == 'approved') {
                                                    $status_class = 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500';
                                                } elseif ($status == 'pending') {
                                                    $status_class = 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500';
                                                } elseif ($status == 'cancelled') {
                                                    $status_class = 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';
                                                } elseif ($status == 'returned') {
                                                    $status_class = 'bg-info-50 text-info-600 dark:bg-info-500/15 dark:text-info-500';
                                                }
                                                ?>
                                                <span
                                                    class="text-theme-xs <?= $status_class ?> rounded-full px-2 py-0.5 font-medium"><?= htmlspecialchars($status) ?></span>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <br>
                <div
                    class=" text-center z-50 p-2 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border-t border-l border-gray-200 dark:border-gray-800 rounded-tl-lg text-xs text-gray-600 dark:text-gray-400">
                    <br>
                    Developed by <span class="font-medium text-primary-600 dark:text-primary-400">eng - Ammar
                        Ahmed</span> &copy;
                    <span x-text="new Date().getFullYear()"></span>
                </div>
            </main>
            <script defer src="./assets/js/bundle.js"></script>
        </div>
    </div>
    <style>
        .flex.h-screen.overflow-hidden {
            direction: rtl;
        }

        h4.text-title-xs.sm\:text-title-sm.font-bold.text-gray-800.dark\:text-white\/90 {
            direction: ltr;
        }

        * {
            text-align: right !important;



        }


        @media screen and (max-width:992px) {

            td.px-6.py-4.text-left.text-sm.whitespace-nowrap.text-gray-700.dark\:text-gray-400 {
                direction: ltr !important;
            }

            .grid.rounded-2xl.border.border-gray-200.bg-white.sm\:grid-cols-2.xl\:grid-cols-4.dark\:border-gray-800.dark\:bg-gray-900 {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

        }
    </style>
</body>

</html>