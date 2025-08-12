<?php
require_once './config/db.php';

// حذف طالب (حذف منطقي)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Students SET deleted_at = NOW() WHERE student_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_students.php');
    exit;
}

// جلب الطلاب غير المحذوفين مع اسم الصف
$query = "
    SELECT 
        s.student_id, s.name, s.address, s.phone, s.email, s.created_at,
        g.name AS grade_name
    FROM Students s
    LEFT JOIN Grades g ON s.grade_id = g.grade_id
    WHERE s.deleted_at IS NULL
    ORDER BY s.created_at DESC
";

$result = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8" />
    <title>إدارة الطلاب</title>
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

        a.add-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 10px 18px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        a.edit-btn {
            background-color: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 5px;
        }

        a.delete-btn {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <h2>إدارة الطلاب</h2>

    <a href="create_student.php" class="add-btn">إضافة طالب جديد</a>

    <table>
        <thead>
            <tr>
                <th>معرف الطالب</th>
                <th>الاسم</th>
                <th>العنوان</th>
                <th>الهاتف</th>
                <th>البريد الإلكتروني</th>
                <th>الصف</th>
                <th>تاريخ الإضافة</th>
                <th>تعديل</th>
                <th>حذف</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($student = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                        <td><?= htmlspecialchars($student['name']) ?></td>
                        <td><?= htmlspecialchars($student['address'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($student['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($student['grade_name'] ?? '-') ?></td>
                        <td><?= date('Y-m-d', strtotime($student['created_at'])) ?></td>
                        <td><a href="edit_student.php?id=<?= $student['student_id'] ?>" class="edit-btn">تعديل</a></td>
                        <td><a href="?delete=<?= $student['student_id'] ?>" onclick="return confirm('هل تريد حذف هذا الطالب؟');"
                                class="delete-btn">حذف</a></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center;">لا توجد بيانات للعرض</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>

</html>

<?php $mysqli->close(); ?>