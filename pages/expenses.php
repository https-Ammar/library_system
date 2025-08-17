<?php
require_once '../config/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/signin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];

    if ($description !== '' && $amount > 0 && $expense_date !== '') {
        $stmt = $mysqli->prepare("INSERT INTO Expenses (description, amount, expense_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $description, $amount, $expense_date);
        $stmt->execute();
        $stmt->close();
        header("Location: expenses.php");
        exit;
    } else {
        $error = "من فضلك أدخل بيانات صحيحة.";
    }
}

if (isset($_GET['delete'])) {
    $expense_id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Expenses SET deleted_at = NOW() WHERE expense_id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $stmt->close();
    header("Location: expenses.php");
    exit;
}

$result = $mysqli->query("SELECT * FROM Expenses WHERE deleted_at IS NULL ORDER BY expense_date DESC");
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$result->free();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المصروفات</title>
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
                    <div class="col-span-12">
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-5 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        المصروفات
                                    </h3>
                                </div>
                                <div>
                                    <button
                                        @click="isTaskModalModal = true; $nextTick(() => { document.querySelector('input[name=\'description\']').focus(); })"
                                        class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                        إضافة مصروف جديد
                                    </button>
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
                                                        الوصف
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        المبلغ (جنيه)
                                                    </p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        تاريخ المصروف
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
                                        <?php if (empty($expenses)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                    لا توجد بيانات للعرض
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($expenses as $expense): ?>
                                                <tr>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($expense['description']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= number_format($expense['amount'], 2) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($expense['expense_date']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <a href="expenses.php?delete=<?= $expense['expense_id'] ?>"
                                                                onclick="return confirm('هل أنت متأكد من حذف هذا المصروف؟');"
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
                                            <?php endforeach; ?>
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
                                إضافة مصروف جديد </h4>
                            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">أدخل تفاصيل المصروف</p>
                        </div>

                        <form class="flex flex-col" method="POST" action="expenses.php">
                            <div class="custom-scrollbar overflow-y-auto px-2">
                                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">وصف
                                            المصروف</label>
                                        <input type="text" name="description" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div class="sm:col-span-1">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المبلغ
                                            (جنيه)</label>
                                        <input type="number" step="0.01" name="amount" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div class="sm:col-span-1">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">تاريخ
                                            المصروف</label>
                                        <input type="date" name="expense_date" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                <div class="flex items-center w-full gap-3 sm:w-auto">
                                    <button @click="isTaskModalModal = false" type="button"
                                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                    <button type="submit" name="add_expense"
                                        class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">
                                        إضافة المصروف </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script defer src="../assets/js/bundle.js"></script>
</body>

</html>