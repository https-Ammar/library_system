<?php
require_once '../config/db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Books SET deleted_at = NOW() WHERE book_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_books.php');
    exit;
}

if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);
    $stmt = $mysqli->prepare("UPDATE Books SET deleted_at = NULL WHERE book_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_books.php');
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
    header('Location: manage_books.php');
    exit;
}

$grades_query = "SELECT * FROM Grades";
$grades_result = $mysqli->query($grades_query);

$query = "
    SELECT 
        b.book_id, b.title, b.quantity, b.price, b.image_url, b.created_at, b.grade_id,
        g.name AS grade_name,
        t.name AS teacher_name
    FROM Books b
    LEFT JOIN Grades g ON b.grade_id = g.grade_id
    LEFT JOIN Teachers t ON b.teacher_id = t.teacher_id
    WHERE b.deleted_at IS NULL
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

// استعلامات الإحصائيات المعدلة
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
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">

    <main>
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
                                    <?= isset($total_money['total_money']) ? number_format((float)$total_money['total_money'], 2) : '0.00' ?> جنيه
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
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <form method="GET" action="">
                                    <div class="relative">
                                        <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2">
                                            <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                                                viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z"
                                                    fill=""></path>
                                            </svg>
                                        </span>
                                        <input type="text" name="search" placeholder="بحث..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-[42px] w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    </div>
                                </form>
                            </div>

                            <button onclick="openTab(event, 'available')" class="tablinks text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                الكتب المتاحة
                            </button>

                            <button onclick="openTab(event, 'finished')" class="tablinks text-theme-sm shadow-theme-xs inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
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
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الصورة</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الكتاب</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الصف</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">المدرس</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الكمية</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">السعر</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">تاريخ الإضافة</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">تعديل</p>
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
                                                                <div class="flex items-center gap-3">
                                                                    <div class="h-[50px] w-[50px] overflow-hidden rounded-md">
                                                                        <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                                                                                <img src="<?= htmlspecialchars($product['image_url']) ?>"
                                                                                    alt="صورة الكتاب">
                                                                        <?php else: ?>
                                                                                <img src="src/images/product/default.jpg" alt="صورة افتراضية">
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
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
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= number_format((float)$product['price'], 2) ?> جنيه
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                                    <?= date('Y-m-d', strtotime($product['created_at'])) ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <a href="#"
                                                                onclick="toggleEditForm(<?= (int) $product['book_id'] ?>); return false;"
                                                                class="edit-btn text-blue-600 hover:underline">
                                                                تعديل
                                                            </a>
                                                            <a href="?delete=<?= $product['book_id'] ?>"
                                                                onclick="return confirm('هل تريد حذف هذا الكتاب؟');"
                                                                class="text-red-600 hover:underline mr-2">
                                                                حذف
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <tr id="edit-form-<?= $product['book_id'] ?>" style="display: none;">
                                                        <td colspan="8" class="px-6 py-3">
                                                            <form method="POST" class="flex items-center gap-4">
                                                                <input type="hidden" name="book_id" value="<?= $product['book_id'] ?>">
                                                                <div>
                                                                    <label class="block text-gray-700 text-sm font-bold mb-2">الكمية</label>
                                                                    <input type="number" name="quantity" value="<?= $product['quantity'] ?>"
                                                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-gray-700 text-sm font-bold mb-2">السعر</label>
                                                                    <input type="number" step="0.01" name="price" value="<?= $product['price'] ?>"
                                                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-gray-700 text-sm font-bold mb-2">الصف</label>
                                                                    <select name="grade_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                                        <?php while ($grade = $grades_result->fetch_assoc()): ?>
                                                                                <option value="<?= $grade['grade_id'] ?>" <?= $grade['grade_id'] == $product['grade_id'] ? 'selected' : '' ?>>
                                                                                    <?= htmlspecialchars($grade['name']) ?>
                                                                                </option>
                                                                        <?php endwhile; ?>
                                                                        <?php $grades_result->data_seek(0); ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mt-6">
                                                                    <button type="submit" name="update" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                                        حفظ
                                                                    </button>
                                                                    <button type="button" onclick="toggleEditForm(<?= $product['book_id'] ?>)" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                                                        إلغاء
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </td>
                                                    </tr>
                                            <?php endwhile; ?>
                                    <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-3">لا توجد كتب متاحة للعرض</td>
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
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الصورة</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الكتاب</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الصف</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">المدرس</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الكمية</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">السعر</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">تاريخ الحذف</p>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">إجراءات</p>
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
                                                                            <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="صورة الكتاب">
                                                                    <?php else: ?>
                                                                            <img src="src/images/product/default.jpg" alt="لا توجد صورة">
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-3 whitespace-nowrap first:pl-0">
                                                            <div class="flex items-center">
                                                                <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">
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
                                                                    <?= number_format((float)$book['price'], 2) ?> جنيه
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
                                                                استعادة
                                                            </a>
                                                        </td>
                                                    </tr>
                                            <?php endwhile; ?>
                                    <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-3">لا توجد كتب منتهية للعرض</td>
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

    <script defer src="../assets/js/bundle.js"></script>
    <script>
        function toggleEditForm(bookId) {
            var form = document.getElementById('edit-form-' + bookId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'table-row';
            } else {
                form.style.display = 'none';
            }
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.tablinks').click();
        });
    </script>
</body>

</html>

<?php $mysqli->close(); ?>