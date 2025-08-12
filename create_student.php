<?php
require_once './config/db.php';

$name = '';
$address = '';
$phone = '';
$email = '';
$grade_id = null;
$errors = [];

// جلب الصفوف للاختيار
$grades = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!empty($errors) === false) {
        $stmt = $mysqli->prepare("INSERT INTO Students (name, address, phone, email, grade_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $address, $phone, $email, $grade_id);
        if ($stmt->execute()) {
            header('Location: manage_students.php'); // تأكد من وجود هذه الصفحة
            exit;
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ البيانات: ' . $mysqli->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8" />
    <title>إضافة طالب جديد</title>
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
            max-width: 600px;
            margin: auto;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        select {
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

    <h2>إضافة طالب جديد</h2>

    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <label for="name">اسم الطالب</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required />

        <label for="address">العنوان</label>
        <input type="text" id="address" name="address" value="<?= htmlspecialchars($address) ?>" />

        <label for="phone">الهاتف</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>" />

        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" />

        <label for="grade_id">الصف</label>
        <select id="grade_id" name="grade_id">
            <option value="">-- اختر صف --</option>
            <?php while ($grade = $grades->fetch_assoc()): ?>
                <option value="<?= $grade['grade_id'] ?>" <?= ($grade_id == $grade['grade_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($grade['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">إضافة</button>
    </form>

    <a href="manage_students.php">العودة إلى قائمة الطلاب</a>

</body>

</html>