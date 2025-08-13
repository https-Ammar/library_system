<?php
require_once '../config/db.php';

$title = '';
$grade_id = null;
$teacher_id = null;
$quantity = 0;
$price = 0.00;
$errors = [];
$image_path = null;

// Fetch data for dropdowns
$grades = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");
$teachers = $mysqli->query("SELECT teacher_id, name FROM Teachers WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $grade_id = $_POST['grade_id'] ?: null;
    $teacher_id = $_POST['teacher_id'] ?: null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);

    if ($title === '') {
        $errors[] = 'اسم الكتاب is required.';
    }
    if ($quantity < 0) {
        $errors[] = 'Quantity must be zero or more.';
    }
    if ($price < 0) {
        $errors[] = 'Price must be zero or more.';
    }

    // Upload book image (optional)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image_file']['type'], $allowed_types)) {
            $errors[] = 'Image file type not supported. Only JPG, PNG, GIF allowed.';
        } elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Image size too large, must be less than 2MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/books/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $new_name = uniqid('book_', true) . '.' . $ext;
            $destination = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                $image_path = 'uploads/books/' . $new_name;
            } else {
                $errors[] = 'Error uploading image.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Books (grade_id, teacher_id, title, quantity, price, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisids", $grade_id, $teacher_id, $title, $quantity, $price, $image_path);
        if ($stmt->execute()) {
            header('Location: ./stock.php');
            exit;
        } else {
            $errors[] = "Data save error: " . $mysqli->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>

<body x-data="{
        page: 'saas',
        loaded: true,
        darkMode: false,
        stickyMenu: false,
        sidebarToggle: false,
        scrollTop: false
    }" x-init="
        darkMode = JSON.parse(localStorage.getItem('darkMode')); 
        $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))
    " :class="{'dark bg-gray-900': darkMode === true}">


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
                <div class="mx-auto max-w-[--breakpoint-2xl] p-4 md:p-6">
                    <div class="space-y-6">
                        <div
                            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                                <h2 class="text-lg font-medium text-gray-800 dark:text-white">
                                    انشاء كتاب
                                </h2>
                            </div>
                            <div class="p-4 sm:p-6 dark:border-gray-800">
                                <form method="post" action="" enctype="multipart/form-data">
                                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                        <div>
                                            <label for="title"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                                الكتاب
                                            </label>
                                            <input type="text" id="title" name="title"
                                                value="<?= htmlspecialchars($title) ?>"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                placeholder=" اسم الكتاب" required>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                المرحلة الدراسيه
                                            </label>
                                            <div x-data="{ isOptionSelected: false }"
                                                class="relative z-20 bg-transparent">
                                                <select id="grade_id" name="grade_id"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                    :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                                                    @change="isOptionSelected = true">
                                                    <option value="">-- المرحلة الدراسيه --</option>
                                                    <?php while ($grade = $grades->fetch_assoc()): ?>
                                                        <option value="<?= $grade['grade_id'] ?>"
                                                            <?= ($grade_id == $grade['grade_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($grade['name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <span
                                                    class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                                    <svg class="stroke-current" width="20" height="20"
                                                        viewBox="0 0 20 20" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke=""
                                                            stroke-width="1.5" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                        </path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                المدرس
                                            </label>
                                            <div x-data="{ isOptionSelected: false }"
                                                class="relative z-20 bg-transparent">
                                                <select id="teacher_id" name="teacher_id"
                                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                    :class="isOptionSelected && 'text-gray-800 dark:text-white/90'"
                                                    @change="isOptionSelected = true">
                                                    <option value="">-- اختر المدرس --</option>
                                                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                                        <option value="<?= $teacher['teacher_id'] ?>"
                                                            <?= ($teacher_id == $teacher['teacher_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($teacher['name']) ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <span
                                                    class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                                    <svg class="stroke-current" width="20" height="20"
                                                        viewBox="0 0 20 20" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke=""
                                                            stroke-width="1.5" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                        </path>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>

                                        <div>
                                            <label for="quantity"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الكمية</label>
                                            <input type="number" id="quantity" name="quantity"
                                                value="<?= htmlspecialchars($quantity) ?>" min="0"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                placeholder="Enter quantity" required>
                                        </div>

                                        <div>
                                            <label for="price"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">السعر</label>
                                            <input type="number" step="0.01" id="price" name="price"
                                                value="<?= htmlspecialchars($price) ?>" min="0"
                                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                placeholder="Enter price" required>
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            صورة الكتاب ( اختياري )
                                        </label>
                                        <label for="image_file"
                                            class="shadow-theme-xs group hover:border-brand-500 block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 transition dark:border-gray-800">
                                            <div class="flex justify-center p-10">
                                                <div class="flex max-w-[260px] flex-col items-center gap-4">
                                                    <div
                                                        class="inline-flex h-13 w-13 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition dark:border-gray-800 dark:text-gray-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none">
                                                            <path
                                                                d="M20.0004 16V18.5C20.0004 19.3284 19.3288 20 18.5004 20H5.49951C4.67108 20 3.99951 19.3284 3.99951 18.5V16M12.0015 4L12.0015 16M7.37454 8.6246L11.9994 4.00269L16.6245 8.6246"
                                                                stroke="currentColor" stroke-width="1.5"
                                                                stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </div>
                                                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                                                        <span class="font-medium text-gray-800 dark:text-white/90">Click
                                                            to
                                                            upload</span>
                                                        or drag and drop JPG, PNG, GIF (MAX. 2MB)
                                                    </p>
                                                </div>
                                            </div>
                                            <input type="file" id="image_file" name="image_file" class="hidden"
                                                accept="image/*">
                                        </label>
                                    </div>

                                    <?php if ($errors): ?>
                                        <div class="mt-6 rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-5 w-5 text-red-400 dark:text-red-600" viewBox="0 0 20 20"
                                                        fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                            clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">There
                                                        were errors
                                                        with your submission</h3>
                                                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                                        <ul class="list-disc space-y-1 pl-5">
                                                            <?php foreach ($errors as $error): ?>
                                                                <li><?= htmlspecialchars($error) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <a href="./stock.php"
                                            class="shadow-theme-xs inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                                            الغاء
                                        </a>
                                        <button type="submit"
                                            class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                                            انشاء
                                        </button>
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