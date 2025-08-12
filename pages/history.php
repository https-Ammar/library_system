<?php
require_once './config/db.php';

$today = date('Y-m-d');

$dates_result = $mysqli->query("SELECT DISTINCT DATE(created_at) as date FROM BookReservations WHERE deleted_at IS NULL ORDER BY DATE(created_at) DESC");
$dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $dates[] = $row['date'];
}

$selected_date = $_GET['date'] ?? $today;
$all_records = [];

$stmt = $mysqli->prepare("SELECT 
    br.reservation_id,
    s.name AS student_name,
    s.phone AS student_phone,
    g.name AS grade_name,
    t.name AS teacher_name,
    b.title AS book_title,
    b.price AS book_price,
    br.amount_paid,
    (b.price - br.amount_paid) AS amount_due,
    br.status,
    br.created_at
FROM BookReservations br
JOIN Students s ON br.student_id = s.student_id
JOIN Books b ON br.book_id = b.book_id
LEFT JOIN Grades g ON s.grade_id = g.grade_id
LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
WHERE DATE(br.created_at) = ? AND br.deleted_at IS NULL
ORDER BY br.created_at DESC");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_records[] = $row;
}
$stmt->close();

$today_orders = $mysqli->query("SELECT COUNT(*) AS today_orders FROM BookReservations WHERE DATE(created_at) = '$selected_date' AND deleted_at IS NULL")->fetch_assoc()['today_orders'] ?? 0;

$stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_paid), 0) AS today_revenue FROM BookReservations WHERE DATE(created_at) = ? AND deleted_at IS NULL AND status IN ('approved', 'returned')");
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$stmt->bind_result($today_revenue);
$stmt->fetch();
$stmt->close();

$total_orders = $mysqli->query("SELECT COUNT(*) AS total_orders FROM BookReservations WHERE deleted_at IS NULL")->fetch_assoc()['total_orders'] ?? 0;

$stmt = $mysqli->prepare("SELECT COUNT(*) AS total_books_sold, COALESCE(SUM(amount_paid), 0) AS total_revenue FROM BookReservations WHERE deleted_at IS NULL AND status IN ('approved', 'returned')");
$stmt->execute();
$stmt->bind_result($total_books_sold, $total_revenue);
$stmt->fetch();
$stmt->close();

$result = $mysqli->query("SELECT status, COUNT(*) AS count FROM BookReservations WHERE DATE(created_at) = '$selected_date' AND deleted_at IS NULL GROUP BY status");
$order_status_counts = [
    'pending' => 0,
    'approved' => 0,
    'cancelled' => 0,
    'returned' => 0,
];
while ($row = $result->fetch_assoc()) {
    $order_status_counts[$row['status']] = $row['count'];
}

$total_expenses = $mysqli->query("SELECT SUM(amount) AS total_expenses FROM Expenses WHERE deleted_at IS NULL")->fetch_assoc()['total_expenses'] ?? 0;
$total_students = $mysqli->query("SELECT COUNT(*) AS total_students FROM Students WHERE deleted_at IS NULL")->fetch_assoc()['total_students'] ?? 0;
$total_users = $mysqli->query("SELECT COUNT(*) AS total_users FROM Users WHERE deleted_at IS NULL")->fetch_assoc()['total_users'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="./assets/css/main.css" rel="stylesheet">
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
                        class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Overview</h3>
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
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+2.5%</span>
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
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+9.5%</span>
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
                                        </h4>
                                        <div>
                                            <span
                                                class="bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">-1.6%</span>
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
                                    </h4>
                                    <div>
                                        <span
                                            class="bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500 flex items-center gap-1 rounded-full py-0.5 pr-2.5 pl-2 text-sm font-medium">+3.5%</span>
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
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">السجل الكامل</h3>
                            <p class="text-gray-500 dark:text-gray-400">
                                عرض الطلبات ليوم <?= $selected_date ?>
                            </p>
                            <div class="flex flex-wrap gap-2 mt-4 mb-6">
                                <?php foreach ($dates as $date): ?>
                                    <a href="history.php?date=<?= $date ?>"
                                        class="date-tab px-4 py-2 rounded-lg <?= $selected_date == $date ? 'active bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-800' ?>">
                                        <?= $date ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="custom-scrollbar overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-900">
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            #</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            اسم الطالب</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            رقم الهاتف</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            المرحلة</th>
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
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            الحالة</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            تاريخ الحجز</th>
                                        <th
                                            class="px-6 py-4 text-left text-sm font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                                            وقت الحجز</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <?php if (count($all_records) > 0): ?>
                                        <?php foreach ($all_records as $record): ?>
                                            <tr>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['reservation_id'] ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['student_name'] ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['student_phone'] ?? 'غير متوفر' ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= $record['grade_name'] ?? 'غير محدد' ?>
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
                                                    <?= number_format($record['book_price'], 2) ?> ج.م
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['amount_paid'], 2) ?> ج.م
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= number_format($record['amount_due'], 2) ?> ج.م
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
                                                        $status_class = 'bg-info-50 text-info-600 dark:bg-info-500/15 dark:text-info-500';
                                                    }
                                                    ?>
                                                    <span
                                                        class="text-theme-xs <?= $status_class ?> rounded-full px-2 py-0.5 font-medium"><?= $status ?></span>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= date('Y-m-d', strtotime($record['created_at'])) ?>
                                                </td>
                                                <td
                                                    class="px-6 py-4 text-left text-sm whitespace-nowrap text-gray-700 dark:text-gray-400">
                                                    <?= date('H:i', strtotime($record['created_at'])) ?>
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
            <script defer src="./assets/js/bundle.js" ط></script>
</body>

</html>