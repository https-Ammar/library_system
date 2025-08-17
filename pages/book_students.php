<?php
require_once '../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/signin.php");
    exit();
}

if (isset($_GET['book_id'])) {
    $book_id = intval($_GET['book_id']);
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $book_query = "SELECT title, quantity FROM Books WHERE book_id = ?";
    $stmt = $mysqli->prepare($book_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book_result = $stmt->get_result();
    $book = $book_result->fetch_assoc();

    $reservations_query = "SELECT SUM(quantity) AS total_reserved FROM BookReservations WHERE book_id = ? AND status = 'approved' AND deleted_at IS NULL";
    $stmt = $mysqli->prepare($reservations_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $reservations_result = $stmt->get_result();
    $reserved = $reservations_result->fetch_assoc();
    $available_quantity = $book['quantity'] - ($reserved['total_reserved'] ?? 0);

    $students_query = "
        SELECT 
            br.reservation_id, br.order_number, br.quantity as reserved_quantity,
            s.student_id, s.name, s.phone, s.email, s.address,
            g.name AS grade_name,
            br.reservation_date, br.status, br.amount_paid, br.amount_due
        FROM BookReservations br
        JOIN Students s ON br.student_id = s.student_id
        LEFT JOIN Grades g ON s.grade_id = g.grade_id
        WHERE br.book_id = ? AND br.deleted_at IS NULL
    ";

    if ($search !== '') {
        $students_query .= " AND (s.name LIKE ? OR g.name LIKE ? OR s.phone LIKE ? OR br.order_number LIKE ?)";
    }

    $students_query .= " ORDER BY br.reservation_date DESC";

    $stmt = $mysqli->prepare($students_query);

    if ($search !== '') {
        $like = "%{$search}%";
        $stmt->bind_param("issss", $book_id, $like, $like, $like, $like);
    } else {
        $stmt->bind_param("i", $book_id);
    }

    $stmt->execute();
    $students_result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلاب المسجلين في الكتاب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        .student-row:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }

        .quantity-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }

        .quantity-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 15px;
        }

        .quantity-value {
            font-weight: bold;
            color: #2c3e50;
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
        <?php require('../includes/header.php'); ?>
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <main>
                <?php require('../includes/nav.php'); ?>
                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    <div class="col-span-12">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pt-4 pb-3 sm:px-6 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="mb-6 flex justify-between items-center">
                                <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                                    الطلاب المسجلين في كتاب: <?= htmlspecialchars($book['title'] ?? '') ?>
                                </h2>
                                <a href="stock.php"
                                    class="text-blue-600 hover:underline text-gray-500 text-theme-sm dark:text-gray-400">العودة
                                    إلى المخزون</a>
                            </div>

                            <div class="quantity-info" style="display: none;">
                                <div class="quantity-item">
                                    <span>الكمية المتاحة</span>
                                    <span class="quantity-value"><?= $available_quantity ?></span>
                                </div>
                                <div class="quantity-item">
                                    <span>إجمالي الكمية</span>
                                    <span class="quantity-value"><?= $book['quantity'] ?? 0 ?></span>
                                </div>
                                <div class="quantity-item">
                                    <span>الكمية المحجوزة</span>
                                    <span class="quantity-value"><?= $reserved['total_reserved'] ?? 0 ?></span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <form method="GET" action="" class="flex">
                                    <input type="hidden" name="book_id" value="<?= $_GET['book_id'] ?>">
                                    <div class="relative w-full max-w-md">
                                        <span class="absolute -translate-y-1/2 pointer-events-none top-1/2 left-4">
                                            <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                                                viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z">
                                                </path>
                                            </svg>
                                        </span>
                                        <input type="text" name="search"
                                            placeholder="ابحث باسم الطالب أو الصف أو الهاتف أو رقم الطلب..."
                                            value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    </div>
                                </form>
                            </div>

                            <table class="min-w-full">
                                <thead class="border-gray-100 border-y dark:border-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 whitespace-nowrap first:pl-0">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    رقم الطلب</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap first:pl-0">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    اسم الطالب</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    الصف</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    الهاتف</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    الكمية</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    الحالة</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    المدفوع</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    المتبقي</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                    تاريخ الحجز</p>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="py-3 divide-y divide-gray-100 dark:divide-gray-800">
                                    <?php if ($students_result && $students_result->num_rows > 0): ?>
                                        <?php while ($student = $students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($student['order_number']) ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
                                                            <?= htmlspecialchars($student['name']) ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($student['grade_name'] ?? '-') ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
<?php
$phone = preg_replace('/\D/', '', $student['phone'] ?? '');
?>
<?= !empty($phone) ? '<a href="https://wa.me/' . $phone . '" target="_blank">' . htmlspecialchars($student['phone']) . '</a>' : '-' ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($student['reserved_quantity']) ?>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <?php
                                                        $paid = floatval($student['paid_amount'] ?? 0);
                                                        $due = floatval($student['amount_due'] ?? 0);

                                                        if (empty($student['status'])) {
                                                            if ($paid >= $due && $due > 0) {
                                                                $student['status'] = 'approved';
                                                            } elseif ($paid == 0 && $due > 0) {
                                                                $student['status'] = 'pending';
                                                            } elseif ($paid > 0 && $paid < $due) {
                                                                $student['status'] = 'partial';
                                                            } else {
                                                                $student['status'] = 'N/A';
                                                            }
                                                        }

                                                        $status = $student['status'] ?? 'pending';
                                                        $status_class = 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-500';

                                                        if ($status == 'approved') {
                                                            $status_class = 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500';
                                                        } elseif ($status == 'pending') {
                                                            $status_class = 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500';
                                                        } elseif ($status == 'cancelled') {
                                                            $status_class = 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';
                                                        } elseif ($status == 'returned') {
                                                            $status_class = 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500';
                                                        } elseif ($status == 'partial') {
                                                            $status_class = 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-500';
                                                        }
                                                        ?>
                                                        <span
                                                            class="text-theme-xs <?= $status_class ?> rounded-full px-2 py-0.5 font-medium">
                                                            <?= htmlspecialchars($status) ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= number_format((float) $student['amount_paid'], 2) ?> <sub
                                                                style="font-size: x-small;">EG</sub>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= number_format((float) $student['amount_due'], 2) ?> <sub
                                                                style="font-size: x-small;">EG</sub>
                                                        </p>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                    <div class="flex items-center">
                                                        <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                            <?= date('Y-m-d', strtotime($student['reservation_date'])) ?>
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                لا يوجد طلاب مسجلين في هذا الكتاب
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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