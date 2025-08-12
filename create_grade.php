<?php
require_once './config/db.php';

$name = '';
$description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'اسم المرحلة مطلوب.';
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Grades (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        if ($stmt->execute()) {
            header('Location: manage_grades.php'); // تأكد من وجود هذه الصفحة
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
    <title>إضافة مرحلة دراسية جديدة</title>
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
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
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

    <h2>إضافة مرحلة دراسية جديدة</h2>

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
        <label for="name">اسم المرحلة</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required />

        <label for="description">وصف المرحلة (اختياري)</label>
        <textarea id="description" name="description"><?= htmlspecialchars($description) ?></textarea>

        <button type="submit">إضافة</button>
    </form>

    <a href="manage_grades.php">العودة إلى قائمة المراحل الدراسية</a>

</body>

</html>
