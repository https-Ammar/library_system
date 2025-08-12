<?php
// إعدادات الاتصال
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "LibraryManagement";

// إنشاء الاتصال
$conn = new mysqli($servername, $username, $password, $dbname);

// التحقق من الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ضبط ترميز الاتصال
$conn->set_charset("utf8mb4");
?>