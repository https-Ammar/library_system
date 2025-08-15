<?php
require_once '../config/db.php';

$name = '';
$description = '';
$errors = [];
$edit_id = null;
$isTaskModalModal = false;

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Grades SET deleted_at = NOW() WHERE grade_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ./grade.php');
    exit;
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $mysqli->prepare("SELECT name, description FROM Grades WHERE grade_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $grade = $result->fetch_assoc();
        $name = $grade['name'];
        $description = $grade['description'];
    }
    $stmt->close();
    $isTaskModalModal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

    if (empty($name)) {
        $errors[] = 'اسم المرحلة مطلوب.';
    }

    if (empty($errors)) {
        if ($edit_id) {
            $stmt = $mysqli->prepare("UPDATE Grades SET name = ?, description = ?, updated_at = NOW() WHERE grade_id = ?");
            $stmt->bind_param("ssi", $name, $description, $edit_id);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO Grades (name, description, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $name, $description);
        }

        if ($stmt->execute()) {
            header('Location: ./grade.php');
            exit;
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ البيانات: ' . $mysqli->error;
        }
        $stmt->close();
    }
}

$result = $mysqli->query("SELECT grade_id, name, description, created_at, updated_at FROM Grades WHERE deleted_at IS NULL ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المراحل الدراسية</title>
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
    x-data="{ page: 'saas', loaded: true, darkMode: false, stickyMenu: false, sidebarToggle: false, scrollTop: false, isTaskModalModal: <?php echo $isTaskModalModal ? 'true' : 'false'; ?> }"
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
                                        إدارة المراحل الدراسية
                                    </h3>
                                </div>
                                <div>
                                    <button
                                        @click="isTaskModalModal = true; $nextTick(() => { document.querySelector('input[name=\'name\']').focus(); })"
                                        class="text-theme-sm shadow-theme-xs inline-flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                        إضافة مرحلة دراسية
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
                                                        المعرف</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        اسم المرحلة</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        الوصف</p>
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
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        وقت الإضافة</p>
                                                </div>
                                            </th>
                                            <th class="px-6 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <p
                                                        class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                        آخر تعديل</p>
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
                                        <?php if ($result->num_rows > 0): ?>
                                            <?php while ($grade = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <span
                                                            class="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($grade['grade_id']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($grade['name']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= htmlspecialchars($grade['description']) ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= date('Y-m-d', strtotime($grade['created_at'])) ?><br>
                                                        </p>
                                                    </td>

                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?= date('h:i A', strtotime($grade['created_at'])) ?>
                                                        </p>
                                                    </td>


                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <p class="text-gray-700 text-theme-sm dark:text-gray-400">
                                                            <?php if ($grade['updated_at']): ?>
                                                                <?= date('Y-m-d', strtotime($grade['updated_at'])) ?><br>
                                                                <!-- <?= date('h:i A', strtotime($grade['updated_at'])) ?> -->
                                                            <?php else: ?>
                                                                لم يتم التعديل
                                                            <?php endif; ?>
                                                        </p>
                                                    </td>
                                                    <td class="px-6 py-3 whitespace-nowrap">
                                                        <div class="flex items-center justify-center gap-3">
                                                            <a href="?edit=<?= $grade['grade_id'] ?>"
                                                                class="cursor-pointer hover:fill-brand-500 dark:hover:fill-brand-500 fill-gray-700 dark:fill-gray-400">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                    fill="currentColor"
                                                                    class="bi bi-transparency text-gray-700 text-theme-sm dark:text-gray-400"
                                                                    viewBox="0 0 16 16">
                                                                    <path
                                                                        d="M0 6.5a6.5 6.5 0 0 1 12.346-2.846 6.5 6.5 0 1 1-8.691 8.691A6.5 6.5 0 0 1 0 6.5m5.144 6.358a5.5 5.5 0 1 0 7.714-7.714 6.5 6.5 0 0 1-7.714 7.714m-.733-1.269q.546.226 1.144.33l-1.474-1.474q.104.597.33 1.144m2.614.386a5.5 5.5 0 0 0 1.173-.242L4.374 7.91a6 6 0 0 0-.296 1.118zm2.157-.672q.446-.25.838-.576L5.418 6.126a6 6 0 0 0-.587.826zm1.545-1.284q.325-.39.576-.837L6.953 4.83a6 6 0 0 0-.827.587l4.6 4.602Zm1.006-1.822q.183-.562.242-1.172L9.028 4.078q-.58.096-1.118.296l3.823 3.824Zm.186-2.642a5.5 5.5 0 0 0-.33-1.144 5.5 5.5 0 0 0-1.144-.33z" />
                                                                </svg>
                                                            </a>
                                                            <a href="?delete=<?= $grade['grade_id'] ?>"
                                                                onclick="return confirm('هل تريد حذف هذه المرحلة؟');"
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
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                                    لا توجد بيانات للعرض
                                                </td>
                                            </tr>
                                            </tr>
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
                                <?= $edit_id ? 'تعديل المرحلة الدراسية' : 'إضافة مرحلة دراسية جديدة' ?>
                            </h4>
                            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 lg:mb-7">إدارة المراحل الدراسية
                                بسهولة</p>
                        </div>

                        <form class="flex flex-col" method="POST">
                            <div class="custom-scrollbar overflow-y-auto px-2">
                                <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                    <div class="sm:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اسم
                                            المرحلة</label>
                                        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required
                                            class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الوصف</label>
                                        <textarea name="description"
                                            class="dark:bg-dark-900 h-20 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"><?= htmlspecialchars($description) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col items-center gap-6 px-2 mt-6 sm:flex-row sm:justify-between">
                                <div class="flex items-center w-full gap-3 sm:w-auto">
                                    <button @click="isTaskModalModal = false" type="button"
                                        class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto">إلغاء</button>
                                    <button type="submit"
                                        class="flex w-full justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">
                                        <?= $edit_id ? 'تحديث المرحلة' : 'إضافة المرحلة' ?>
                                    </button>
                                </div>
                            </div>

                            <?php if ($edit_id): ?>
                                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
                            <?php endif; ?>
                        </form>

                        <?php if (!empty($errors)): ?>
                            <div class="errors mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                                <ul class="list-disc list-inside">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

        </div>
    </div>
    <script defer src="../assets/js/bundle.js"></script>

</body>

</html>
<?php $mysqli->close(); ?>