<?php
require_once '../config/db.php';

$name = '';
$address = '';
$phone = '';
$email = '';
$grade_id = null;
$errors = [];

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/signin.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("UPDATE BookReservations SET deleted_at = NOW() WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE Students SET deleted_at = NOW() WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        $_SESSION['message'] = "تم حذف الطالب وحجوزاته بنجاح";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "فشل في حذف الطالب: " . $e->getMessage();
    }

    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

if (isset($_POST['update_student'])) {
    $student_id = intval($_POST['student_id']);
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {
        $errors[] = 'اسم الطالب مطلوب.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("UPDATE Students SET name = ?, address = ?, phone = ? WHERE student_id = ?");
        $stmt->bind_param("sssi", $name, $address, $phone, $student_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "تم تحديث بيانات الطالب بنجاح";
            $stmt->close();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errors[] = 'حدث خطأ أثناء تحديث البيانات: ' . $mysqli->error;
            $stmt->close();
        }
    }
}

$count_query = "SELECT COUNT(*) as total_students FROM Students WHERE deleted_at IS NULL";
$count_result = $mysqli->query($count_query);
$total_students = $count_result->fetch_assoc()['total_students'];

$grades = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $grade_id = $_POST['grade_id'] ?: null;

    if ($name === '') {
        $errors[] = 'اسم الطالب مطلوب.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'صيغة البريد الإلكتروني غير صحيحة.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Students (name, address, phone, email, grade_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $address, $phone, $email, $grade_id);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ البيانات: ' . $mysqli->error;
            $stmt->close();
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT s.student_id, s.name, s.address, s.phone, s.email, s.created_at, g.name AS grade_name FROM Students s LEFT JOIN Grades g ON s.grade_id = g.grade_id WHERE s.deleted_at IS NULL";

if ($search !== '') {
    $query .= " AND (s.name LIKE '%" . $mysqli->real_escape_string($search) . "%' OR s.email LIKE '%" . $mysqli->real_escape_string($search) . "%' OR s.phone LIKE '%" . $mysqli->real_escape_string($search) . "%')";
}

$query .= " ORDER BY s.created_at DESC";
$result = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطلاب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif
        }
    </style>
</head>

<body
    x-data="{isTaskModalModal:false,darkMode:false,loaded:true,stickyMenu:false,sidebarToggle:false,scrollTop:false,editModalOpen:false,currentStudent:{}}"
    x-init="darkMode=JSON.parse(localStorage.getItem('darkMode'));$watch('darkMode',value=>localStorage.setItem('darkMode',JSON.stringify(value)))"
    :class="{'dark bg-gray-900':darkMode===true}">

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['message'] ?>
        </div>
        <?php unset($_SESSION['message']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error'] ?>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div x-show="loaded" x-transition.opacity
        x-init="window.addEventListener('DOMContentLoaded',()=>{setTimeout(()=>loaded=false,500)})"
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
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-5 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">قائمة الطلاب<span
                                            class="text-sm text-gray-500 dark:text-gray-400">(
                                            <?= $total_students ?>)
                                        </span></h3>
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <form method="GET" action="">
                                        <div class="relative"><span
                                                class="absolute -translate-y-1/2 pointer-events-none top-1/2 left-4"><svg
                                                    class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                                                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                                        d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z"
                                                        fill=""></path>
                                                </svg></span><input type="text" name="search" placeholder="بحث..."
                                                value="<?= htmlspecialchars($search) ?>"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-10 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        </div>
                                    </form>
                                    <div><button @click="isTaskModalModal=true"
                                            class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"><svg
                                                class="stroke-current fill-white dark:fill-gray-800" width="20"
                                                height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
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
                                            </svg>إضافة طالب</button></div>
                                </div>
                            </div>

                            <div class="max-w-full overflow-x-auto custom-scrollbar">
                                <table class="min-w-full">
                                    <thead
                                        class="border-gray-100 border-y bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div x-data="{checked:false}" class="flex items-center gap-3">
                                                        <div @click="checked=!checked"
                                                            class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-md border-[1.25px] bg-white dark:bg-white/0 border-gray-300 dark:border-gray-700"
                                                            :class="checked?'border-brand-500 dark:border-brand-500 bg-brand-500':'bg-white dark:bg-white/0 border-gray-300 dark:border-gray-700'">
                                                            <svg :class="checked?'block':'hidden'" width="14"
                                                                height="14" viewBox="0 0 14 14" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg" class="hidden">
                                                                <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7"
                                                                    stroke="white" stroke-width="1.94437"
                                                                    stroke-linecap="round" stroke-linejoin="round">
                                                                </path>
                                                            </svg>
                                                        </div>
                                                        <div><span
                                                                class="block font-medium text-gray-500 text-theme-xs dark:text-gray-400">الرقم</span>
                                                        </div>
                                                    </div>
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
                                                        العنوان</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الهاتف</p>
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
                                                        تاريخ الإضافة</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center justify-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        إجراءات</p>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php while ($student = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div x-data="{checked:false}" class="flex items-center gap-3">
                                                                <div @click="checked=!checked"
                                                                    class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-md border-[1.25px] bg-white dark:bg-white/0 border-gray-300 dark:border-gray-700"
                                                                    :class="checked?'border-brand-500 dark:border-brand-500 bg-brand-500':'bg-white dark:bg-white/0 border-gray-300 dark:border-gray-700'">
                                                                    <svg :class="checked?'block':'hidden'" width="14"
                                                                        height="14" viewBox="0 0 14 14" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg" class="hidden">
                                                                        <path d="M11.6668 3.5L5.25016 9.91667L2.3335 7"
                                                                            stroke="white" stroke-width="1.94437"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </svg>
                                                                </div>
                                                                <div><span
                                                                        class="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                                                        <?= htmlspecialchars($student['student_id']) ?>
                                                                    </span></div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                <span class="text-xs font-semibold text-brand-500">
                                                                    <?= strtoupper(substr($student['name'], 0, 2)) ?>
                                                                </span>
                                                            </div>
                                                            <div><span
                                                                    class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                                    <?= htmlspecialchars($student['name']) ?>
                                                                </span><span
                                                                    class="text-gray-500 text-theme-sm dark:text-gray-400"><?= htmlspecialchars($student['email'] ?? '-') ?></span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($student['address'] ?? '-') ?>
                                                        </p>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?php $phone = preg_replace('/\D/', '', $student['phone'] ?? ''); ?>
                                                            <?= !empty($phone) ? '<a href="https://wa.me/' . $phone . '" target="_blank">' . htmlspecialchars($student['phone']) . '</a>' : '-' ?>
                                                        </p>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($student['grade_name'] ?? '-') ?>
                                                        </p>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= date('Y-m-d', strtotime($student['created_at'])) ?>
                                                        </p>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <button
                                                                @click="editModalOpen=true;currentStudent={student_id:'<?= $student['student_id'] ?>',name:'<?= htmlspecialchars($student['name'], ENT_QUOTES) ?>',address:'<?= htmlspecialchars($student['address'], ENT_QUOTES) ?>',phone:'<?= htmlspecialchars($student['phone'], ENT_QUOTES) ?>'}"
                                                                class="cursor-pointer hover:fill-brand-500 dark:hover:fill-brand-500 fill-gray-700 dark:fill-gray-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-transparency text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M0 6.5a6.5 6.5 0 0 1 12.346-2.846 6.5 6.5 0 1 1-8.691 8.691A6.5 6.5 0 0 1 0 6.5m5.144 6.358a5.5 5.5 0 1 0 7.714-7.714 6.5 6.5 0 0 1-7.714 7.714m-.733-1.269q.546.226 1.144.33l-1.474-1.474q.104.597.33 1.144m2.614.386a5.5 5.5 0 0 0 1.173-.242L4.374 7.91a6 6 0 0 0-.296 1.118zm2.157-.672q.446-.25.838-.576L5.418 6.126a6 6 0 0 0-.587.826zm1.545-1.284q.325-.39.576-.837L6.953 4.83a6 6 0 0 0-.827.587l4.6 4.602Zm1.006-1.822q.183-.562.242-1.172L9.028 4.078q-.58.096-1.118.296l3.823 3.824Zm.186-2.642a5.5 5.5 0 0 0-.33-1.144 5.5 5.5 0 0 0-1.144-.33z">
                                                                    </path>
                                                                </svg>
                                                            </button>
                                                            <a href="?delete=<?= $student['student_id'] ?>"
                                                                onclick="return confirm('هل تريد حذف هذا الطالب وجميع حجوزاته؟');"
                                                                class="cursor-pointer hover:fill-error-500 dark:hover:fill-error-500 fill-gray-700 dark:fill-gray-400"><svg
                                                                    xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-x-octagon text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M4.54.146A.5.5 0 0 1 4.893 0h6.214a.5.5 0 0 1 .353.146l4.394 4.394a.5.5 0 0 1 .146.353v6.214a.5.5 0 0 1-.146.353l-4.394 4.394a.5.5 0 0 1-.353.146H4.893a.5.5 0 0 1-.353-.146L.146 11.46A.5.5 0 0 1 0 11.107V4.893a.5.5 0 0 1 .146-.353zM5.1 1 1 5.1v5.8L5.1 15h5.8l4.1-4.1V5.1L10.9 1z">
                                                                    </path>
                                                                    <path
                                                                        d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708">
                                                                    </path>
                                                                </svg></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-gray-500 dark:text-gray-400">لا
                                                    توجد بيانات للعرض</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="isTaskModalModal" x-transition
                            class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
                            <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                                @click="isTaskModalModal=false"></div>
                            <div @click.outside="isTaskModalModal=false"
                                class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">
                                <div class="px-2">
                                    <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">إضافة طالب
                                        جديد</h4>
                                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">إدارة الطلاب بسهولة
                                    </p>
                                </div>

                                <form class="flex flex-col" method="POST">
                                    <div class="custom-scrollbar overflow-y-auto px-2">
                                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                                    الطالب</label>
                                                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>"
                                                    required
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div>
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">العنوان</label>
                                                <input type="text" name="address"
                                                    value="<?= htmlspecialchars($address) ?>"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div>
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الهاتف</label>
                                                <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">البريد
                                                    الإلكتروني</label>
                                                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الصف</label>
                                                <select name="grade_id"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                                    <option value="">-- اختر صف --</option>
                                                    <?php $grades->data_seek(0);
                                                    while ($grade = $grades->fetch_assoc()): ?>
                                                        <option value="<?= $grade['grade_id'] ?>"
                                                            <?= ($grade_id == $grade['grade_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($grade['name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                        <div class="flex items-center w-full gap-3 sm:w-auto">
                                            <button @click="isTaskModalModal=false" type="button"
                                                class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                            <button type="submit" name="add_student"
                                                class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">إضافة
                                                الطالب</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div x-show="editModalOpen" x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                            class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999"
                            :class="{'hidden':!editModalOpen}" style="">
                            <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                                @click="editModalOpen=false"></div>
                            <div @click.outside="editModalOpen=false"
                                class="no-scrollbar relative w-full max-w-[700px] mx-auto overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">
                                <div class="px-2">
                                    <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">تعديل
                                        بيانات الطالب</h4>
                                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">تعديل بيانات الطالب
                                    </p>
                                </div>
                                <form method="POST" class="flex flex-col">
                                    <input type="hidden" name="student_id" x-model="currentStudent.student_id">
                                    <input type="hidden" name="update_student" value="1">
                                    <div class="custom-scrollbar overflow-y-auto px-2">
                                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                                    الطالب</label>
                                                <input type="text" name="name" x-model="currentStudent.name" required=""
                                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">العنوان</label>
                                                <input type="text" name="address" x-model="currentStudent.address"
                                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الهاتف</label>
                                                <input type="text" name="phone" x-model="currentStudent.phone"
                                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                        <div class="flex items-center w-full gap-3 sm:w-auto">
                                            <button type="button" @click="editModalOpen=false"
                                                class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                            <button type="submit"
                                                class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">حفظ
                                                التغييرات</button>
                                        </div>
                                    </div>
                                </form>
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