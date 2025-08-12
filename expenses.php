<?php
require_once './config/db.php';

// إضافة مصروف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'];

    if ($description !== '' && $amount > 0 && $expense_date !== '') {
        $stmt = $mysqli->prepare("INSERT INTO Expenses (description, amount, expense_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $description, $amount, $expense_date);
        $stmt->execute();
        $stmt->close();
        header("Location: expenses.php");
        exit;
    } else {
        $error = "من فضلك أدخل بيانات صحيحة.";
    }
}

// حذف مصروف
if (isset($_GET['delete'])) {
    $expense_id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("UPDATE Expenses SET deleted_at = NOW() WHERE expense_id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $stmt->close();
    header("Location: expenses.php");
    exit;
}

// جلب جميع المصروفات غير المحذوفة
$result = $mysqli->query("SELECT * FROM Expenses WHERE deleted_at IS NULL ORDER BY expense_date DESC");
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <title>إدارة المصروفات</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            color: #222;
            padding: 20px;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
        }

        form {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        form input,
        form button {
            padding: 10px;
            margin: 5px 0;
            width: 100%;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        form button {
            background: #222;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        form button:hover {
            background: #444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }

        th {
            background: #222;
            color: white;
        }

        tr:hover {
            background: #f9f9f9;
        }

        a.delete-btn {
            color: #f44336;
            font-weight: bold;
            text-decoration: none;
        }

        a.delete-btn:hover {
            text-decoration: underline;
        }

        .error {
            color: red;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>

<body>
    <h1>إدارة المصروفات</h1>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="expenses.php">
        <input type="text" name="description" placeholder="وصف المصروف" required />
        <input type="number" step="0.01" name="amount" placeholder="المبلغ (جنيه)" required />
        <input type="date" name="expense_date" required />
        <button type="submit" name="add_expense">إضافة مصروف</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>الوصف</th>
                <th>المبلغ (جنيه)</th>
                <th>تاريخ المصروف</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="4">لا توجد مصروفات مسجلة.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?= htmlspecialchars($expense['description']) ?></td>
                        <td><?= number_format($expense['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                        <td>
                            <a class="delete-btn" href="expenses.php?delete=<?= $expense['expense_id'] ?>"
                                onclick="return confirm('هل أنت متأكد من حذف هذا المصروف؟');">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>