<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ./auth/signin.php");
    exit();
}

require_once '../config/db.php';

$today = date('Y-m-d');

$dates_result = $mysqli->query("
    SELECT DISTINCT DATE(created_at) AS date 
    FROM BookReservations 
    WHERE deleted_at IS NULL 
    ORDER BY DATE(created_at) DESC
");
$dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $dates[] = $row['date'];
}

$selected_date = $_GET['date'] ?? $today;

$stmt = $mysqli->prepare("
    SELECT COUNT(*) AS today_orders 
    FROM BookReservations 
    WHERE DATE(created_at) = ? AND deleted_at IS NULL
");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$stmt->bind_result($today_orders);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT COALESCE(SUM(amount_paid), 0) AS today_revenue 
    FROM BookReservations 
    WHERE DATE(created_at) = ? AND deleted_at IS NULL 
    AND status NOT IN ('cancelled', 'returned')
");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$stmt->bind_result($today_revenue);
$stmt->fetch();
$stmt->close();

$all_records = [];
$stmt = $mysqli->prepare("
    SELECT 
        br.reservation_id,
        br.order_number,
        s.name AS student_name,
        s.phone AS student_phone,
        g.name AS grade_name,
        t.name AS teacher_name,
        b.title AS book_title,
        b.price AS book_price,
        br.quantity,
        br.amount_paid,
        (b.price * br.quantity - br.amount_paid) AS amount_due,
        (b.price * br.quantity) AS total_amount,
        br.status,
        br.created_at
    FROM BookReservations br
    JOIN Students s ON br.student_id = s.student_id
    JOIN Books b ON br.book_id = b.book_id
    LEFT JOIN Grades g ON s.grade_id = g.grade_id
    LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
    WHERE DATE(br.created_at) = ? AND br.deleted_at IS NULL
    ORDER BY br.created_at DESC
");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_records[] = $row;
}
$stmt->close();

$result = $mysqli->query("
    SELECT COUNT(*) AS total_orders 
    FROM BookReservations 
    WHERE deleted_at IS NULL
");
$total_orders = $result->fetch_assoc()['total_orders'] ?? 0;

$stmt = $mysqli->prepare("
    SELECT COUNT(*) AS total_books_sold, 
           COALESCE(SUM(amount_paid), 0) AS total_revenue 
    FROM BookReservations 
    WHERE deleted_at IS NULL 
    AND status NOT IN ('cancelled', 'returned')
");
$stmt->execute();
$stmt->bind_result($total_books_sold, $total_revenue);
$stmt->fetch();
$stmt->close();

$result = $mysqli->query("
    SELECT status, COUNT(*) AS count 
    FROM BookReservations 
    WHERE DATE(created_at) = '$selected_date' 
    AND deleted_at IS NULL 
    GROUP BY status
");
$order_status_counts = [
    'pending' => 0,
    'approved' => 0,
    'cancelled' => 0,
    'returned' => 0,
];
while ($row = $result->fetch_assoc()) {
    $order_status_counts[$row['status']] = $row['count'];
}

$total_expenses = $mysqli->query("
    SELECT SUM(amount) AS total_expenses 
    FROM Expenses 
    WHERE deleted_at IS NULL
")->fetch_assoc()['total_expenses'] ?? 0;

$total_students = $mysqli->query("
    SELECT COUNT(*) AS total_students 
    FROM Students 
    WHERE deleted_at IS NULL
")->fetch_assoc()['total_students'] ?? 0;

$total_users = $mysqli->query("
    SELECT COUNT(*) AS total_users 
    FROM Users 
    WHERE deleted_at IS NULL
")->fetch_assoc()['total_users'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif !important;
        }

        .date-tab {
            cursor: pointer;
            transition: all 0.3s;
        }

        .date-tab:hover {
            background-color: #f3f4f6;
        }

        .date-tab.active {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false, 'isTaskModalModal': false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">
    <div x-show="loaded" x-transition.opacity
        x-init="window.addEventListener('DOMContentLoaded', () => {setTimeout(() => loaded = false, 500)})"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-white dark:bg-black">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent">
        </div>
    </div>

    <div x-show="isTaskModalModal" x-transition=""
        class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">



        <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" @click="isTaskModalModal = false">
        </div>

        <div @click.outside="isTaskModalModal = false"
            class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">

            <div class="px-2">
                <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">سجل النشاط </h4>
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">إدارة النشاط اليومي</p>
            </div>

            <div class="custom-scrollbar overflow-y-auto px-2">
                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">

                        <?php foreach ($dates as $date): ?>
                            <a href="history.php?date=<?= $date ?>" class="">
                                <span
                                    class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium">
                                    <?= $date ?>
                                </span>
                            </a>
                        <?php endforeach; ?>


                    </div>
                </div>
            </div>

            <div class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                <div class="flex items-center w-full gap-3 sm:w-auto">
                    <button @click="isTaskModalModal = false" type="button"
                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                </div>
            </div>
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

                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white"> السجل الكامل</h3>

                        <div>
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
                                سجل النشاط
                            </button>
                        </div>
                    </div>



                    <div
                        class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">

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
                                <span class="text-sm text-gray-500 dark:text-gray-400"> الطلبات المعلقة </span>
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
                                <span class="text-sm text-gray-500 dark:text-gray-400"> الطلبات الموافق عليها</span>
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
                                    <span class="text-sm text-gray-500 dark:text-gray-400"> الطلبات الملغاة</span>
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
                                <span class="text-sm text-gray-500 dark:text-gray-400"> الطلبات المرتجعة</span>
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
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-6 py-4">
                            <p class="flex items-center gap-3 text-gray-500 dark:text-gray-400 justify-between">
                                السجل اليومي

                                <span
                                    class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium">
                                    <?= $selected_date ?>
                                </span>
                            </p>
                        </div>

                        <div class="custom-scrollbar overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-900">
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            رقم الطلب</th>
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
                                            الكمية</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            الإجمالي</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المدفوع</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المتبقي</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            وقت الحجز</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            الحالة</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <?php if (count($all_records) > 0): ?>
                                        <?php foreach ($all_records as $record): ?>
                                            <tr>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['order_number'] ?>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                <span class="text-xs font-semibold text-brand-500">
                                                                    <?= $record['reservation_id'] ?></span>
                                                            </div>
                                                            <div>
                                                                <span
                                                                    class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                                    <?= $record['student_name'] ?> </span>
                                                                <span class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= $record['grade_name'] ?? 'غير محدد' ?> </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['student_phone'] ?? 'غير متوفر' ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['teacher_name'] ?? 'غير محدد' ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['book_title'] ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['book_price'], 2) ?> <sub
                                                        style="font-size: x-small;">EG</sub>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['quantity'] ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['total_amount'], 2) ?> <sub
                                                        style="font-size: x-small;">EG</sub>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['amount_paid'], 2) ?> <sub
                                                        style="font-size: x-small;">EG</sub>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['amount_due'], 2) ?> <sub
                                                        style="font-size: x-small;">EG</sub>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= date('h:i A', strtotime($record['created_at'])) ?>
                                                </td>

                                                <td class="px-6 py-4 text-left">
                                                    <?php
                                                    $status = $record['status'];
                                                    $status_class = 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-500';

                                                    if ($status == 'approved') {
                                                        $status_class = 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500';
                                                    } elseif ($status == 'pending') {
                                                        $status_class = 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500';
                                                    } elseif ($status == 'cancelled') {
                                                        $status_class = 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';
                                                    } elseif ($status == 'returned') {
                                                        $status_class = 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';
                                                    }
                                                    ?>
                                                    <span
                                                        class="text-theme-xs <?= $status_class ?> rounded-full px-2 py-0.5 font-medium"><?= $status ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="12"
                                                class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                لا توجد سجلات لعرضها
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

            <script defer src="../assets/js/bundle.js"></script>

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

                td.px-6.py-4.text-left.text-sm.whitespace-nowrap.text-gray-700.dark\:text-gray-400 {
                    direction: ltr !important;
                }

                @media screen and (max-width:992px) {


                    .grid.rounded-2xl.border.border-gray-200.bg-white.sm\:grid-cols-2.xl\:grid-cols-4.dark\:border-gray-800.dark\:bg-gray-900 {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                    }
                }
            </style>
</body>

</html>