<?php
require_once '../config/db.php';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Teachers SET deleted_at = NOW() WHERE teacher_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ./teachers.php');
    exit;
}

$name = '';
$region = '';
$phone = '';
$subject = '';
$selected_grades = [];
$errors = [];
$image_path = null;

$grades_result = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $name = trim($_POST['name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $selected_grades = $_POST['grades'] ?? [];
    $selected_grades = array_map('intval', $selected_grades);

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image_file']['type'], $allowed_types)) {
            $errors[] = 'نوع الملف غير مدعوم.';
        } elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'حجم الصورة كبير جداً.';
        } else {
            $upload_dir = __DIR__ . '/uploads/teachers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $new_name = uniqid('teacher_', true) . '.' . $ext;
            $destination = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                $image_path = 'uploads/teachers/' . $new_name;
            } else {
                $errors[] = 'حدث خطأ أثناء رفع الصورة.';
            }
        }
    }

    if (empty($name))
        $errors[] = 'الاسم مطلوب.';


    if (empty($subject))
        $errors[] = 'اسم المادة مطلوب.';

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Teachers (name, region, phone, subject, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $region, $phone, $subject, $image_path);

        if ($stmt->execute()) {
            $teacher_id = $stmt->insert_id;
            $stmt->close();

            if (!empty($selected_grades)) {
                $stmt_link = $mysqli->prepare("INSERT INTO TeacherGrades (teacher_id, grade_id) VALUES (?, ?)");
                foreach ($selected_grades as $grade_id) {
                    $stmt_link->bind_param("ii", $teacher_id, $grade_id);
                    $stmt_link->execute();
                }
                $stmt_link->close();
            }

            header('Location: ./teachers.php');
            exit;
        } else {
            $errors[] = "خطأ في حفظ البيانات: " . $mysqli->error;
        }
    }
}

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $search_param = "%$search%";
    $stmt = $mysqli->prepare("
        SELECT teacher_id, name, image_url, region, phone, subject, created_at 
        FROM Teachers 
        WHERE deleted_at IS NULL AND name LIKE ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $mysqli->query("
        SELECT teacher_id, name, image_url, region, phone, subject, created_at 
        FROM Teachers 
        WHERE deleted_at IS NULL 
        ORDER BY created_at DESC
    ");
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المدرسين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }

        .teacher-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }
    </style>
</head>

<body
    x-data="{ isTaskModalModal: false, darkMode: false, loaded: true, stickyMenu: false, sidebarToggle: false, scrollTop: false }"
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
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-col gap-5 px-6 mb-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        قائمة المدرسين
                                        <sup class="text-sm text-gray-500 dark:text-gray-400">
                                            ( <?= $result->num_rows ?? 0 ?> )
                                        </sup>
                                    </h3>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <form method="GET" action="#">
                                        <div class="relative">
                                            <span class="absolute -translate-y-1/2 pointer-events-none top-1/2 left-4">
                                                <svg class="fill-gray-500 dark:fill-gray-400" width="20" height="20"
                                                    viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                                        d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z"
                                                        fill=""></path>
                                                </svg>
                                            </span>
                                            <input type="text" name="search" placeholder="ابحث باسم المدرس..."
                                                value="<?= htmlspecialchars($search ?? '') ?>"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 
                                                       h-10 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-[42px] text-sm 
                                                       text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden 
                                                       xl:w-[300px] dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        </div>
                                    </form>

                                    <div>
                                        <button @click="isTaskModalModal = true"
                                            class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                            <svg class="stroke-current fill-white dark:fill-gray-800" width="20"
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
                                            </svg>
                                            إضافة مدرس
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($errors)): ?>
                                <div class="px-6 mb-4">
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="max-w-full overflow-x-auto custom-scrollbar">
                                <table class="min-w-full">
                                    <thead
                                        class="border-gray-100 border-y bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="block font-medium text-gray-500 text-theme-xs dark:text-gray-400">معرف</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الاسم</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">المنطقة</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الهاتف</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">تاريخ
                                                        الإنشاء</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">الكتب</span>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center justify-center">
                                                    <span
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">إجراءات</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php while ($teacher = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <span
                                                            class="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= !empty($teacher['teacher_id']) ? $teacher['teacher_id'] : 'قيمة فارغة' ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="flex items-center justify-center w-10 h-10 rounded-full bg-brand-100">
                                                                <?php if (!empty($teacher['image_url'])): ?>
                                                                    <img src="<?= htmlspecialchars($teacher['image_url']) ?>"
                                                                        class="teacher-image rounded-full">
                                                                <?php else: ?>
                                                                    <span class="text-xs font-semibold text-brand-500">
                                                                        <?= !empty($teacher['name']) ? mb_substr($teacher['name'], 0, 1) : '-' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <a href="profile.php?id=<?= $teacher['teacher_id'] ?>"
                                                                    class="text-theme-sm mb-0.5 block font-medium text-gray-700 dark:text-gray-400">
                                                                    <?= !empty($teacher['name']) ? htmlspecialchars($teacher['name']) : 'قيمة فارغة' ?>
                                                                </a>
                                                                <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                                    <?= !empty($teacher['subject']) ? htmlspecialchars($teacher['subject']) : 'قيمة فارغة' ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= !empty($teacher['region']) ? htmlspecialchars($teacher['region']) : 'قيمة فارغة' ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= !empty($teacher['phone']) ? htmlspecialchars($teacher['phone']) : 'قيمة فارغة' ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= !empty($teacher['created_at']) ? date('Y-m-d', strtotime($teacher['created_at'])) : 'قيمة فارغة' ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <?php
                                                        $stmt_books = $mysqli->prepare("SELECT COUNT(*) as cnt FROM Books WHERE teacher_id = ? AND deleted_at IS NULL");
                                                        $stmt_books->bind_param("i", $teacher['teacher_id']);
                                                        $stmt_books->execute();
                                                        $books_count = $stmt_books->get_result()->fetch_assoc()['cnt'];
                                                        $stmt_books->close();
                                                        ?>
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= $books_count ?> كتاب
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <a href="?delete=<?= $teacher['teacher_id'] ?>"
                                                                onclick="return confirm('هل تريد حذف هذا المدرس؟');"
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
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                    لا توجد بيانات للعرض
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="isTaskModalModal" x-transition
                            class="fixed inset-0 flex items-center justify-center p-5 overflow-y-auto z-99999">
                            <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                                @click="isTaskModalModal = false"></div>
                            <div @click.outside="isTaskModalModal = false"
                                class="no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-11">

                                <div class="px-2">
                                    <h4 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">إضافة مدرس
                                        جديد
                                    </h4>
                                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">إدارة المدرسين
                                        بسهولة</p>
                                </div>

                                <form class="flex flex-col" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="add_teacher" value="1">
                                    <div class="custom-scrollbar overflow-y-auto px-2">
                                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                                    المدرس</label>
                                                <input type="text" name="name"
                                                    value="<?= htmlspecialchars($name ?? '') ?>" required
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div>
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المنطقة</label>
                                                <input type="text" name="region"
                                                    value="<?= htmlspecialchars($region ?? '') ?>"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div>
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الهاتف</label>
                                                <input type="text" name="phone"
                                                    value="<?= htmlspecialchars($phone ?? '') ?>"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                                    المادة</label>
                                                <input type="text" name="subject"
                                                    value="<?= htmlspecialchars($subject ?? '') ?>" required
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">صورة
                                                    المدرس</label>
                                                <input type="file" name="image_file" accept="image/*"
                                                    class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            </div>

                                            <div class="sm:col-span-2">
                                                <label
                                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المراحل
                                                    الدراسية</label>
                                                <?php if ($grades_result && $grades_result->num_rows > 0): ?>
                                                    <?php while ($grade = $grades_result->fetch_assoc()): ?>
                                                        <?php $checkbox_id = 'grade_' . $grade['grade_id']; ?>
                                                        <div class="flex items-center mb-2">
                                                            <input type="checkbox" id="<?= $checkbox_id ?>" name="grades[]"
                                                                value="<?= $grade['grade_id'] ?>"
                                                                <?= in_array($grade['grade_id'], $selected_grades ?? []) ? 'checked' : '' ?>
                                                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900">
                                                            <label for="<?= $checkbox_id ?>"
                                                                class="mr-2 text-sm text-gray-700 dark:text-gray-400">
                                                                <?= htmlspecialchars($grade['name']) ?>
                                                            </label>
                                                        </div>
                                                    <?php endwhile; ?>
                                                    <?php $grades_result->data_seek(0); ?>
                                                <?php else: ?>
                                                    <p class="text-sm text-gray-500">لا توجد مراحل دراسية متاحة</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                        <div class="flex items-center w-full gap-3 sm:w-auto">
                                            <button @click="isTaskModalModal = false" type="button"
                                                class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                            <button type="submit"
                                                class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">إضافة
                                                المدرس</button>
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

<?php
$mysqli->close();
?>