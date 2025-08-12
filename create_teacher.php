<?php
require_once './config/db.php';

$name = '';
$region = '';
$phone = '';
$subject = '';
$selected_grades = [];
$errors = [];
$image_path = null;

$grades_result = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $selected_grades = $_POST['grades'] ?? [];
    $selected_grades = array_map('intval', $selected_grades);

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image_file']['type'], $allowed_types)) {
            $errors[] = 'نوع الملف غير مدعوم. يرجى رفع صورة بصيغة JPG أو PNG أو GIF.';
        } elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'حجم الصورة كبير جداً. يجب أن يكون أقل من 2 ميجابايت.';
        } else {
            $upload_dir = __DIR__ . '/uploads/teachers/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $new_name = uniqid('teacher_', true) . '.' . $ext;
            $destination = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                $image_path = 'uploads/teachers/' . $new_name;
            } else {
                $errors[] = 'حدث خطأ أثناء رفع الصورة.';
            }
        }
    }

    if ($name === '') {
        $errors[] = 'الاسم مطلوب.';
    }
    if ($region === '') {
        $errors[] = 'المنطقة مطلوبة.';
    }
    if ($phone === '') {
        $errors[] = 'رقم الهاتف مطلوب.';
    }
    if ($subject === '') {
        $errors[] = 'اسم المادة مطلوب.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Teachers (name, region, phone, subject, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $region, $phone, $subject, $image_path);
        if ($stmt->execute()) {
            $teacher_id = $stmt->insert_id;
            $stmt->close();

            if (!empty($selected_grades)) {
                $stmt_link = $mysqli->prepare("INSERT INTO TeacherGrades (teacher_id, grade_id) VALUES (?, ?)");
                foreach ($selected_grades as $grade_id) {
                    $gid = intval($grade_id);
                    $stmt_link->bind_param("ii", $teacher_id, $gid);
                    $stmt_link->execute();
                }
                $stmt_link->close();
            }

            header('Location: manage_teachers.php');
            exit;
        } else {
            $errors[] = "خطأ في حفظ البيانات: " . $mysqli->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8" />
    <title>إضافة مدرس جديد</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            padding: 20px;
            background: #f5f5f5;
        }

        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            margin: auto;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            box-sizing: border-box;
        }

        button {
            margin-top: 16px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .errors {
            background: #f8d7da;
            color: #842029;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        a {
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <h2>إضافة مدرس جديد</h2>

    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data">
        <label for="name">اسم المدرس</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required />

        <label for="region">المنطقة</label>
        <input type="text" id="region" name="region" value="<?= htmlspecialchars($region) ?>" required />

        <label for="phone">رقم الهاتف</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" required />

        <label for="subject">اسم المادة</label>
        <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>" required />

        <label for="image_file">صورة المدرس (اختياري)</label>
        <input type="file" id="image_file" name="image_file" accept="image/*" />

        <label>اختر المرحلة الدراسية (يمكن اختيار أكثر من مرحلة):</label>
        <div>
            <?php if ($grades_result && $grades_result->num_rows > 0): ?>
                <?php while ($grade = $grades_result->fetch_assoc()): ?>
                    <label style="display:block; margin-bottom:4px;">
                        <input type="checkbox" name="grades[]" value="<?= $grade['grade_id'] ?>" <?= in_array($grade['grade_id'], $selected_grades) ? 'checked' : '' ?> />
                        <?= htmlspecialchars($grade['name']) ?>
                    </label>
                <?php endwhile; ?>
            <?php else: ?>
                <p>لا توجد مراحل دراسية متاحة</p>
            <?php endif; ?>
        </div>

        <button type="submit">إضافة</button>
    </form>

    <a href="manage_teachers.php">العودة إلى قائمة المدرسين</a>

</body>

</html>