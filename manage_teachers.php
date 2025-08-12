<?php
require_once './config/db.php';

// حذف مدرس (حذف منطقي)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Teachers SET deleted_at = NOW() WHERE teacher_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_teachers.php');
    exit;
}

// معالجة البحث
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
    <title>إدارة المدرسين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4">
        <h2 class="mb-4">إدارة المدرسين</h2>
        <a href="create_teacher.php" class="btn btn-success mb-3">+ إضافة مدرس جديد</a>
        <form class="d-flex mb-3" method="GET">
            <input type="text" class="form-control me-2" name="search" placeholder="ابحث باسم المدرس..."
                value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary">بحث</button>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>معرف</th>
                        <th>الاسم</th>
                        <th>الصورة</th>
                        <th>المنطقة</th>
                        <th>الهاتف</th>
                        <th>المادة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الكتب</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($teacher = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $teacher['teacher_id'] ?></td>
                                <td>
                                    <a href="teacher_details.php?id=<?= $teacher['teacher_id'] ?>">
                                        <?= htmlspecialchars($teacher['name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($teacher['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($teacher['image_url']) ?>" class="img-thumbnail"
                                            style="width:60px;">
                                    <?php else: ?>
                                        <span class="text-muted">لا توجد</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($teacher['region']) ?></td>
                                <td><?= htmlspecialchars($teacher['phone']) ?></td>
                                <td><?= htmlspecialchars($teacher['subject']) ?></td>
                                <td><?= date('Y-m-d', strtotime($teacher['created_at'])) ?></td>
                                <td>
                                    <?php
                                    $stmt_books = $mysqli->prepare("SELECT COUNT(*) as cnt FROM Books WHERE teacher_id = ? AND deleted_at IS NULL");
                                    $stmt_books->bind_param("i", $teacher['teacher_id']);
                                    $stmt_books->execute();
                                    $books_count = $stmt_books->get_result()->fetch_assoc()['cnt'];
                                    $stmt_books->close();
                                    ?>
                                    <?= $books_count ?> كتاب
                                </td>
                                <td>
                                    <a href="?delete=<?= $teacher['teacher_id'] ?>" class="btn btn-danger btn-sm"
                                        onclick="return confirm('هل تريد حذف هذا المدرس؟');">حذف</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">لا توجد بيانات</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php $mysqli->close(); ?>