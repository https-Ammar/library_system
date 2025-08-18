<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/signin.php");
    exit();
}

require_once '../config/db.php';

$add_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {
    $student_id = intval($_POST['student_id']);
    $teacher_id = intval($_POST['teacher_id']);
    $book_id = intval($_POST['book_id']);
    $quantity = intval($_POST['quantity']);
    $reservation_date = $_POST['reservation_date'] ?: date('Y-m-d H:i:s');
    $amount_paid = floatval($_POST['amount_paid']);
    $book_price = floatval($_POST['book_price']);
    $receipt_image = null;

    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/receipts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileExt = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'receipt_' . uniqid() . '.' . $fileExt;
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
            $receipt_image = $targetPath;
        }
    }

    if ($student_id <= 0) $add_errors[] = "اختر الطالب.";
    if ($teacher_id <= 0) $add_errors[] = "اختر المدرس.";
    if ($book_id <= 0) $add_errors[] = "اختر الكتاب.";
    if ($quantity <= 0) $add_errors[] = "الكمية يجب أن تكون أكبر من الصفر.";
    if ($amount_paid < 0) $add_errors[] = "المبلغ المدفوع غير صحيح.";
    if ($book_price < 0) $add_errors[] = "سعر الكتاب غير صحيح.";

    if (empty($add_errors)) {
        $mysqli->begin_transaction();
        try {
            $check = $mysqli->prepare("SELECT quantity, price FROM Books WHERE book_id = ? FOR UPDATE");
            $check->bind_param("i", $book_id);
            $check->execute();
            $result = $check->get_result();
            $book = $result->fetch_assoc();
            $check->close();

            if (!$book) {
                throw new Exception("الكتاب غير موجود.");
            } elseif ($book['quantity'] < $quantity) {
                throw new Exception("عذرًا، لا توجد نسخ كافية من هذا الكتاب. الكمية المتاحة: " . $book['quantity']);
            }

            $total_price = $book['price'] * $quantity;
            if ($amount_paid > $total_price) {
                throw new Exception("المبلغ المدفوع لا يمكن أن يكون أكبر من السعر الإجمالي.");
            }

            $amount_due = max($total_price - $amount_paid, 0);
            $order_number = 'RD-' . rand(10000, 99999);

            $stmt = $mysqli->prepare("
                INSERT INTO BookReservations (
                    student_id, 
                    teacher_id,
                    book_id, 
                    quantity,
                    book_price,
                    total_amount,
                    reservation_date, 
                    status, 
                    amount_paid, 
                    amount_due,
                    order_number,
                    receipt_image
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "iiiiddsddss", 
                $student_id, 
                $teacher_id, 
                $book_id, 
                $quantity, 
                $book['price'], 
                $total_price, 
                $reservation_date, 
                $amount_paid, 
                $amount_due, 
                $order_number, 
                $receipt_image
            );
            $stmt->execute();
            $stmt->close();

            $update = $mysqli->prepare("UPDATE Books SET quantity = quantity - ? WHERE book_id = ?");
            $update->bind_param("ii", $quantity, $book_id);
            $update->execute();
            $update->close();

            $mysqli->commit();
            $_SESSION['message'] = "تم حجز الكتاب بنجاح. رقم الطلب: " . $order_number;
            header('Location: book_reservations.php');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            if ($receipt_image && file_exists($receipt_image)) {
                unlink($receipt_image);
            }
            $add_errors[] = $e->getMessage();
        }
    }
}

$students = $mysqli->query("
SELECT s.student_id, s.name, g.name AS grade_name
FROM Students s
LEFT JOIN Grades g ON s.grade_id = g.grade_id
WHERE s.deleted_at IS NULL
ORDER BY s.name
");

$teachers = $mysqli->query("SELECT teacher_id, name FROM Teachers WHERE deleted_at IS NULL ORDER BY name");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة حجز جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
        .ts-wrapper {
            padding-top: 0 !important;
        }
        .ts-control {
            height: 44px !important;
            padding: 0.6rem 1rem !important;
            border-radius: 0.5rem !important;
            border: 1px solid #d1d5db !important;
            background-color: transparent !important;
            font-size: 0.875rem !important;
            color: #1f2937 !important;
        }
        .ts-dropdown {
            border-radius: 0.5rem !important;
            border: 1px solid #d1d5db !important;
            font-size: 0.875rem !important;
        }
        .dark .ts-control {
            border-color: #374151 !important;
            background-color: #111827 !important;
            color: #e5e7eb !important;
        }
        .dark .ts-dropdown {
            border-color: #374151 !important;
            background-color: #111827 !important;
            color: #e5e7eb !important;
        }
        .dark .ts-dropdown .active {
            background-color: #1f2937 !important;
            color: #ffffff !important;
        }
        input#student_select-ts-control {
    color: var(--color-gray-700);
}
    </style>
</head>

<body
    x-data="{ page: 'saas', loaded: true, darkMode: false, stickyMenu: false, sidebarToggle: false, scrollTop: false }"
    x-init="darkMode = JSON.parse(localStorage.getItem('darkMode')); 
            $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">

    <div x-show="loaded" x-transition.opacity
        x-init="window.addEventListener('DOMContentLoaded', () => {setTimeout(() => loaded = false, 500)})"
        class="fixed inset-0 z-999999 flex items-center justify-center bg-white dark:bg-black">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent">
        </div>
    </div>

    <div class="flex h-screen overflow-hidden">
        <?php require('../includes/header.php'); ?>
        <div class="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
            <?php require('../includes/nav.php'); ?>

            <main>
                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">إضافة حجز جديد</h3>
                    </div>

                    <?php if (!empty($add_errors)): ?>
                        <div
                            class="mb-4 rounded-lg border border-error-200 bg-error-50 p-4 text-sm text-error-600 dark:border-error-900 dark:bg-error-900/30 dark:text-error-500">
                            <ul>
                                <?php foreach ($add_errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر الطالب</label>
                                    <select name="student_id" id="student_select" required
                                        placeholder="ابحث عن الطالب..."
                                        class="w-full">
                                        <option value="">-- اختر الطالب --</option>
                                        <?php while ($student = $students->fetch_assoc()): ?>
                                            <option value="<?= $student['student_id'] ?>">
                                                <?= htmlspecialchars($student['name']) ?> - <?= htmlspecialchars($student['grade_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر المدرس</label>
                                    <select name="teacher_id" id="teacher_id" onchange="fetchBooks()" required
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">-- اختر المدرس --</option>
                                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                            <option value="<?= $teacher['teacher_id'] ?>">
                                                <?= htmlspecialchars($teacher['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">اختر الكتاب</label>
                                    <select name="book_id" id="book_id" onchange="fetchInfo()" required
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        <option value="">-- اختر الكتاب --</option>
                                    </select>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الكمية</label>
                                    <input type="number" name="quantity" id="quantity" min="1" value="1"
                                        oninput="calculateTotal()" required
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">سعر الوحدة</label>
                                    <input type="number" step="0.01" name="book_price" id="book_price" readonly
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المبلغ الإجمالي</label>
                                    <input type="number" step="0.01" name="total_amount" id="total_amount" readonly
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">المبلغ المدفوع</label>
                                    <input type="number" step="0.01" name="amount_paid" id="amount_paid"
                                        oninput="calculateDue()" required
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">الباقي</label>
                                    <input type="number" step="0.01" name="amount_due" id="amount_due" readonly
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                </div>

                       <div>
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">تاريخ الحجز</label>
    <input type="date" name="reservation_date" 
        value="<?= (new DateTime('now', new DateTimeZone('Africa/Cairo')))->format('Y-m-d') ?>" 
        required
        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
</div>


                                <div class="sm:col-span-2">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">إثبات الدفع (صورة أو ملف)</label>
                                    <input type="file" name="receipt_image" accept="image/*,.pdf,.doc,.docx"
                                        class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">يمكنك رفع صورة إثبات الدفع أو ملف PDF</p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <a href="book_reservations.php"
                                    class="flex justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">العودة للحجوزات</a>
                                <button type="submit" name="add_reservation"
                                    class="flex justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">حجز الكتاب</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script defer src="../assets/js/bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        new TomSelect('#student_select', {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            },
            searchField: 'text',
            placeholder: 'ابحث عن الطالب...',
            render: {
                option: function(data, escape) {
                    return '<div>' + escape(data.text) + '</div>';
                },
                item: function(data, escape) {
                    return '<div>' + escape(data.text) + '</div>';
                }
            }
        });

        function fetchBooks() {
            let teacherId = document.getElementById('teacher_id').value;
            let bookSelect = document.getElementById('book_id');
            bookSelect.innerHTML = '<option value="">جاري التحميل...</option>';
            if (teacherId) {
                fetch('get_books.php?teacher_id=' + teacherId)
                    .then(res => res.json())
                    .then(data => {
                        bookSelect.innerHTML = '<option value="">-- اختر الكتاب --</option>';
                        data.forEach(book => {
                            bookSelect.innerHTML += `<option value="${book.book_id}" data-price="${book.price}" data-quantity="${book.quantity}">${book.title} - ${book.price}  (المتبقي: ${book.quantity})</option>`;
                        });
                    });
            } else {
                bookSelect.innerHTML = '<option value="">-- اختر الكتاب --</option>';
            }
        }

        function fetchInfo() {
            let bookSelect = document.getElementById('book_id');
            let selectedBook = bookSelect.options[bookSelect.selectedIndex];
            let bookPrice = selectedBook.getAttribute('data-price') || 0;
            let availableQuantity = selectedBook.getAttribute('data-quantity') || 0;

            document.getElementById('book_price').value = bookPrice;
            document.getElementById('quantity').max = availableQuantity;
            calculateTotal();
        }

        function calculateTotal() {
            let quantity = parseInt(document.getElementById('quantity').value) || 0;
            let maxQuantity = parseInt(document.getElementById('quantity').max) || 0;

            if (quantity > maxQuantity) {
                document.getElementById('quantity').value = maxQuantity;
                quantity = maxQuantity;
            }

            let bookPrice = parseFloat(document.getElementById('book_price').value) || 0;
            let totalAmount = quantity * bookPrice;
            document.getElementById('total_amount').value = totalAmount.toFixed(2);

            let amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
            if (amountPaid > totalAmount) {
                document.getElementById('amount_paid').value = totalAmount.toFixed(2);
            }

            calculateDue();
        }

        function calculateDue() {
            let totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
            let amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;

            if (amountPaid > totalAmount) {
                document.getElementById('amount_paid').value = totalAmount.toFixed(2);
                amountPaid = totalAmount;
            }

            let amountDue = Math.max(totalAmount - amountPaid, 0);
            document.getElementById('amount_due').value = amountDue.toFixed(2);
        }
    </script>
</body>

</html>
<?php $mysqli->close(); ?>