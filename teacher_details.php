<?php
require_once './config/db.php';

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
    <title>تفاصيل المدرس</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

        <!-- بيانات المدرس -->
        <div class="card p-3 mb-4 shadow-sm">
            <div class="row g-3">
                <div class="col-md-3 text-center">
                    <?php if (!empty($teacher['image_url'])): ?>
                        <img src="<?= htmlspecialchars($teacher['image_url']) ?>" class="img-fluid rounded"
                            alt="صورة المدرس">
                    <?php else: ?>
                        <span class="text-muted">لا توجد صورة</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h4><?= htmlspecialchars($teacher['name']) ?></h4>
                    <p><strong>📍 المنطقة:</strong> <?= htmlspecialchars($teacher['region'] ?? '—') ?></p>
                    <p><strong>📞 الهاتف:</strong> <?= htmlspecialchars($teacher['phone'] ?? '—') ?></p>
                    <p><strong>📚 المادة:</strong> <?= htmlspecialchars($teacher['subject'] ?? '—') ?></p>
                </div>
            </div>
        </div>

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