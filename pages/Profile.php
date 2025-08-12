<?php
require_once '../config/db.php';

if (!isset($_GET['id'])) {
    die("معرف المدرس غير موجود");
}

$teacher_id = intval($_GET['id']);

// جلب بيانات المدرس
$stmt = $mysqli->prepare("SELECT * FROM Teachers WHERE teacher_id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("لم يتم العثور على المدرس");
}

// جلب الكتب المرتبطة بالمدرس مع عدد المباعة وعدد الحجوزات لكل كتاب
$stmt_books = $mysqli->prepare("
    SELECT 
        b.book_id, 
        b.title, 
        b.image_url, 
        b.price, 
        b.quantity,
        b.created_at,
        COALESCE(SUM(s.quantity_sold), 0) AS sold_count,
        COUNT(br.reservation_id) AS total_reservations,
        SUM(CASE WHEN br.status = 'approved' THEN 1 ELSE 0 END) AS approved_reservations,
        SUM(CASE WHEN br.status = 'pending' THEN 1 ELSE 0 END) AS pending_reservations
    FROM Books b
    LEFT JOIN Sales s ON b.book_id = s.book_id AND s.deleted_at IS NULL
    LEFT JOIN BookReservations br ON b.book_id = br.book_id AND br.deleted_at IS NULL
    WHERE b.teacher_id = ? AND b.deleted_at IS NULL
    GROUP BY b.book_id
");
$stmt_books->bind_param("i", $teacher_id);
$stmt_books->execute();
$books = $stmt_books->get_result();
$stmt_books->close();

// جلب المراحل الدراسية المرتبطة
$stmt_grades = $mysqli->prepare("
    SELECT g.name 
    FROM TeacherGrades tg 
    JOIN Grades g ON tg.grade_id = g.grade_id 
    WHERE tg.teacher_id = ? AND g.deleted_at IS NULL
");
$stmt_grades->bind_param("i", $teacher_id);
$stmt_grades->execute();
$grades = $stmt_grades->get_result();
$stmt_grades->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($teacher['name']) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>

<body
    x-data="{ page: 'saas', loaded: true, darkMode: false, stickyMenu: false, sidebarToggle: false, scrollTop: false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); 
            $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">

    <main>
        <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">

            <div class="mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex w-full flex-col items-center gap-6 xl:flex-row">
                        <div class="h-20 w-20 overflow-hidden rounded-full border border-gray-200 dark:border-gray-800">

                            <?php if (!empty($teacher['image_url'])): ?>


                                <img src="<?= htmlspecialchars($teacher['image_url']) ?>" alt="user">
                            <?php else: ?>
                                <span class="text-muted">لا توجد صورة</span>
                            <?php endif; ?>
                        </div>
                        <div class="order-3 xl:order-2">
                            <h4
                                class="mb-2 text-center text-lg font-semibold text-gray-800 xl:text-left dark:text-white/90">
                                De . <?= htmlspecialchars($teacher['name']) ?>
                            </h4>
                            <div class="flex flex-col items-center gap-1 text-center xl:flex-row xl:gap-3 xl:text-left">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?= htmlspecialchars($teacher['subject'] ?? '—') ?>
                                </p>
                                <div class="hidden h-3.5 w-px bg-gray-300 xl:block dark:bg-gray-700"></div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?= htmlspecialchars($teacher['region'] ?? '—') ?> /
                                    <?= htmlspecialchars($teacher['phone'] ?? '—') ?>

                                </p>
                            </div>
                        </div>
                        <div class="order-2 flex grow items-center gap-2 xl:order-3 xl:justify-end">
                            <button
                                class="shadow-theme-xs flex h-11 w-11 items-center justify-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M11.6666 11.2503H13.7499L14.5833 7.91699H11.6666V6.25033C11.6666 5.39251 11.6666 4.58366 13.3333 4.58366H14.5833V1.78374C14.3118 1.7477 13.2858 1.66699 12.2023 1.66699C9.94025 1.66699 8.33325 3.04771 8.33325 5.58342V7.91699H5.83325V11.2503H8.33325V18.3337H11.6666V11.2503Z"
                                        fill=""></path>
                                </svg>
                            </button>

                            <button
                                class="shadow-theme-xs flex h-11 w-11 items-center justify-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M15.1708 1.875H17.9274L11.9049 8.75833L18.9899 18.125H13.4424L9.09742 12.4442L4.12578 18.125H1.36745L7.80912 10.7625L1.01245 1.875H6.70078L10.6283 7.0675L15.1708 1.875ZM14.2033 16.475H15.7308L5.87078 3.43833H4.23162L14.2033 16.475Z"
                                        fill=""></path>
                                </svg>
                            </button>

                            <button
                                class="shadow-theme-xs flex h-11 w-11 items-center justify-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M5.78381 4.16645C5.78351 4.84504 5.37181 5.45569 4.74286 5.71045C4.11391 5.96521 3.39331 5.81321 2.92083 5.32613C2.44836 4.83904 2.31837 4.11413 2.59216 3.49323C2.86596 2.87233 3.48886 2.47942 4.16715 2.49978C5.06804 2.52682 5.78422 3.26515 5.78381 4.16645ZM5.83381 7.06645H2.50048V17.4998H5.83381V7.06645ZM11.1005 7.06645H7.78381V17.4998H11.0672V12.0248C11.0672 8.97475 15.0422 8.69142 15.0422 12.0248V17.4998H18.3338V10.8914C18.3338 5.74978 12.4505 5.94145 11.0672 8.46642L11.1005 7.06645Z"
                                        fill=""></path>
                                </svg>
                            </button>

                            <button
                                class="shadow-theme-xs flex h-11 w-11 items-center justify-center gap-2 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                                <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M10.8567 1.66699C11.7946 1.66854 12.2698 1.67351 12.6805 1.68573L12.8422 1.69102C13.0291 1.69766 13.2134 1.70599 13.4357 1.71641C14.3224 1.75738 14.9273 1.89766 15.4586 2.10391C16.0078 2.31572 16.4717 2.60183 16.9349 3.06503C17.3974 3.52822 17.6836 3.99349 17.8961 4.54141C18.1016 5.07197 18.2419 5.67753 18.2836 6.56433C18.2935 6.78655 18.3015 6.97088 18.3081 7.15775L18.3133 7.31949C18.3255 7.73011 18.3311 8.20543 18.3328 9.1433L18.3335 9.76463C18.3336 9.84055 18.3336 9.91888 18.3336 9.99972L18.3335 10.2348L18.333 10.8562C18.3314 11.794 18.3265 12.2694 18.3142 12.68L18.3089 12.8417C18.3023 13.0286 18.294 13.213 18.2836 13.4351C18.2426 14.322 18.1016 14.9268 17.8961 15.458C17.6842 16.0074 17.3974 16.4713 16.9349 16.9345C16.4717 17.397 16.0057 17.6831 15.4586 17.8955C14.9273 18.1011 14.3224 18.2414 13.4357 18.2831C13.2134 18.293 13.0291 18.3011 12.8422 18.3076L12.6805 18.3128C12.2698 18.3251 11.7946 18.3306 10.8567 18.3324L10.2353 18.333C10.1594 18.333 10.0811 18.333 10.0002 18.333H9.76516L9.14375 18.3325C8.20591 18.331 7.7306 18.326 7.31997 18.3137L7.15824 18.3085C6.97136 18.3018 6.78703 18.2935 6.56481 18.2831C5.67801 18.2421 5.07384 18.1011 4.5419 17.8955C3.99328 17.6838 3.5287 17.397 3.06551 16.9345C2.60231 16.4713 2.3169 16.0053 2.1044 15.458C1.89815 14.9268 1.75856 14.322 1.7169 13.4351C1.707 13.213 1.69892 13.0286 1.69238 12.8417L1.68714 12.68C1.67495 12.2694 1.66939 11.794 1.66759 10.8562L1.66748 9.1433C1.66903 8.20543 1.67399 7.73011 1.68621 7.31949L1.69151 7.15775C1.69815 6.97088 1.70648 6.78655 1.7169 6.56433C1.75786 5.67683 1.89815 5.07266 2.1044 4.54141C2.3162 3.9928 2.60231 3.52822 3.06551 3.06503C3.5287 2.60183 3.99398 2.31641 4.5419 2.10391C5.07315 1.89766 5.67731 1.75808 6.56481 1.71641C6.78703 1.70652 6.97136 1.69844 7.15824 1.6919L7.31997 1.68666C7.7306 1.67446 8.20591 1.6689 9.14375 1.6671L10.8567 1.66699ZM10.0002 5.83308C7.69781 5.83308 5.83356 7.69935 5.83356 9.99972C5.83356 12.3021 7.69984 14.1664 10.0002 14.1664C12.3027 14.1664 14.1669 12.3001 14.1669 9.99972C14.1669 7.69732 12.3006 5.83308 10.0002 5.83308ZM10.0002 7.49974C11.381 7.49974 12.5002 8.61863 12.5002 9.99972C12.5002 11.3805 11.3813 12.4997 10.0002 12.4997C8.6195 12.4997 7.50023 11.3809 7.50023 9.99972C7.50023 8.61897 8.61908 7.49974 10.0002 7.49974ZM14.3752 4.58308C13.8008 4.58308 13.3336 5.04967 13.3336 5.62403C13.3336 6.19841 13.8002 6.66572 14.3752 6.66572C14.9496 6.66572 15.4169 6.19913 15.4169 5.62403C15.4169 5.04967 14.9488 4.58236 14.3752 4.58308Z"
                                        fill=""></path>
                                </svg>
                            </button>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </main>




    <script defer src="../assets/js/bundle.js"></script>
</body>

</html>















<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تفاصيل المدرس</title>
    <style>
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #28a745;
            color: #fff;
        }

        .badge-other {
            background-color: #6c757d;
            color: #fff;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">
        <h2 class="mb-4">تفاصيل المدرس</h2>



        <!-- الكتب المرتبطة -->
        <h4>📚 الكتب المرتبطة</h4>
        <?php if ($books->num_rows > 0): ?>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>الصورة</th>
                            <th>العنوان</th>
                            <th>السعر</th>
                            <th>الكمية المتاحة</th>
                            <th>عدد المباعة</th>
                            <th>الحجوزات</th>
                            <th>تاريخ الإضافة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($book['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($book['image_url']) ?>" alt="صورة الكتاب"
                                            class="img-thumbnail" style="width:60px; height:60px; object-fit:cover;">
                                    <?php else: ?>
                                        <span class="text-muted">لا توجد صورة</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($book['title'] ?? '—') ?></td>
                                <td><?= isset($book['price']) ? htmlspecialchars($book['price']) . ' ج.م' : '—' ?></td>
                                <td><?= isset($book['quantity']) ? htmlspecialchars($book['quantity']) : '0' ?></td>
                                <td><?= htmlspecialchars($book['sold_count']) ?></td>
                                <td>
                                    <?php if ($book['total_reservations'] > 0): ?>
                                        <span class="badge badge-pending">قيد الانتظار: <?= $book['pending_reservations'] ?></span>
                                        <span class="badge badge-approved">موافق عليها: <?= $book['approved_reservations'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-other">لا توجد حجوزات</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($book['created_at']) ? date('Y-m-d', strtotime($book['created_at'])) : '—' ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">لا توجد كتب مرتبطة</p>
        <?php endif; ?>

        <!-- المراحل الدراسية -->
        <h4>🎓 المراحل الدراسية المرتبطة</h4>
        <?php if ($grades->num_rows > 0): ?>
            <ul class="list-group">
                <?php while ($grade = $grades->fetch_assoc()): ?>
                    <li class="list-group-item"><?= htmlspecialchars($grade['name']) ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">لا توجد مراحل دراسية مرتبطة</p>
        <?php endif; ?>

        <a href="manage_teachers.php" class="btn btn-secondary mt-3">⬅ رجوع</a>
    </div>
</body>

</html>
<?php $mysqli->close(); ?>