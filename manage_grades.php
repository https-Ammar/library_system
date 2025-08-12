<?php
require_once './config/db.php';

// حذف مرحلة دراسية (حذف منطقي)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Grades SET deleted_at = NOW() WHERE grade_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_grades.php');
    exit;
}

// تعديل المرحلة (عند ارسال POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    if ($name !== '') {
        $stmt = $mysqli->prepare("UPDATE Grades SET name = ?, description = ? WHERE grade_id = ?");
        $stmt->bind_param("ssi", $name, $description, $edit_id);
        $stmt->execute();
        $stmt->close();
        header('Location: manage_grades.php');
        exit;
    }
}

// جلب المراحل الدراسية غير المحذوفة
$result = $mysqli->query("SELECT grade_id, name, description, created_at FROM Grades WHERE deleted_at IS NULL ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8" />
    <title>إدارة المراحل الدراسية</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            padding: 20px;
            background: #f5f5f5;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: right;
            vertical-align: middle;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 60px;
        }

        form.inline {
            display: inline-block;
            margin: 0;
        }

        button,
        input[type="submit"] {
            padding: 6px 12px;
            margin: 4px 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button.edit-btn {
            background-color: #007bff;
            color: white;
        }

        a.delete-btn {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
        }

        a.add-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 10px 18px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <h2>إدارة المراحل الدراسية</h2>

    <a href="create_grade.php" class="add-btn">إضافة مرحلة دراسية جديدة</a>

    <table>
        <thead>
            <tr>
                <th>معرف المرحلة</th>
                <th>اسم المرحلة</th>
                <th>الوصف</th>
                <th>تاريخ الإضافة</th>
                <th>حفظ التعديل</th>
                <th>حذف</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($grade = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($grade['grade_id']) ?></td>
                        <td>
                            <form method="post" class="inline" action="">
                                <input type="hidden" name="edit_id" value="<?= $grade['grade_id'] ?>" />
                                <input type="text" name="name" value="<?= htmlspecialchars($grade['name']) ?>" required />
                        </td>
                        <td>
                            <textarea name="description"><?= htmlspecialchars($grade['description']) ?></textarea>
                        </td>
                        <td><?= date('Y-m-d', strtotime($grade['created_at'])) ?></td>
                        <td>
                            <button type="submit" class="edit-btn">حفظ</button>
                            </form>
                        </td>
                        <td>
                            <a href="?delete=<?= $grade['grade_id'] ?>" onclick="return confirm('هل تريد حذف هذه المرحلة؟');"
                                class="delete-btn">حذف</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;">لا توجد بيانات للعرض</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>

</html>

<?php $mysqli->close(); ?>