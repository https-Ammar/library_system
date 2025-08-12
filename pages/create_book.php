<?php
require_once '../config/db.php';

$title = '';
$category_id = null;
$grade_id = null;
$teacher_id = null;
$quantity = 0;
$price = 0.00;
$errors = [];
$image_path = null;

// جلب البيانات للاختيارات
$categories = $mysqli->query("SELECT category_id, name FROM Categories WHERE deleted_at IS NULL ORDER BY name");
$grades = $mysqli->query("SELECT grade_id, name FROM Grades WHERE deleted_at IS NULL ORDER BY name");
$teachers = $mysqli->query("SELECT teacher_id, name FROM Teachers WHERE deleted_at IS NULL ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = $_POST['category_id'] ?: null;
    $grade_id = $_POST['grade_id'] ?: null;
    $teacher_id = $_POST['teacher_id'] ?: null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);

    if ($title === '') {
        $errors[] = 'اسم الكتاب مطلوب.';
    }
    if ($quantity < 0) {
        $errors[] = 'الكمية يجب أن تكون صفر أو أكثر.';
    }
    if ($price < 0) {
        $errors[] = 'السعر يجب أن يكون صفر أو أكثر.';
    }

    // رفع صورة الكتاب (اختياري)
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['image_file']['type'], $allowed_types)) {
            $errors[] = 'نوع ملف الصورة غير مدعوم. فقط JPG, PNG, GIF مسموح.';
        } elseif ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'حجم الصورة كبير جداً، يجب أن يكون أقل من 2 ميجابايت.';
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
                $errors[] = 'حدث خطأ أثناء رفع الصورة.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO Books (category_id, grade_id, teacher_id, title, quantity, price, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisids", $category_id, $grade_id, $teacher_id, $title, $quantity, $price, $image_path);
        if ($stmt->execute()) {
            header('Location: manage_books.php');
            exit;
        } else {
            $errors[] = "خطأ في حفظ البيانات: " . $mysqli->error;
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
    <title>create_book</title>
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

    <main>
        <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
            <!-- المحتوى هنا -->
        </div>
    </main>

    <script defer src="../assets/js/bundle.js"></script>
</body>

</html>





<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8" />
    <title>إضافة كتاب جديد</title>
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
        input[type="number"],
        input[type="file"],
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }

        .close {
            color: #aaa;
            float: left;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }
    </style>
</head>

<body>

    <h2>إضافة كتاب جديد</h2>

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
        <label for="title">اسم الكتاب</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required />

        <label for="category_id">التصنيف</label>
        <select id="category_id" name="category_id">
            <option value="">-- اختر تصنيف --</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= $cat['category_id'] ?>" <?= ($category_id == $cat['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="grade_id">الصف</label>
        <select id="grade_id" name="grade_id">
            <option value="">-- اختر صف --</option>
            <?php while ($grade = $grades->fetch_assoc()): ?>
                <option value="<?= $grade['grade_id'] ?>" <?= ($grade_id == $grade['grade_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($grade['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="teacher_id">المدرس</label>
        <select id="teacher_id" name="teacher_id">
            <option value="">-- اختر مدرس --</option>
            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                <option value="<?= $teacher['teacher_id'] ?>" <?= ($teacher_id == $teacher['teacher_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($teacher['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="quantity">الكمية</label>
        <input type="number" id="quantity" name="quantity" value="<?= htmlspecialchars($quantity) ?>" min="0"
            required />

        <label for="price">السعر</label>
        <input type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars($price) ?>" min="0"
            required />

        <label for="image_file">صورة الكتاب (اختياري)</label>
        <input type="file" id="image_file" name="image_file" accept="image/*" />

        <button type="submit">إضافة</button>
    </form>

    <a href="manage_books.php">العودة إلى قائمة الكتب</a>

    <script>
        function showAddForm() {
            document.getElementById('addModal').style.display = 'block';
        }
        function closeAddForm() {
            document.getElementById('addModal').style.display = 'none';
        }
        window.onclick = function (event) {
            var modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>