<?php
session_start();
require('../config/db.php');

// التحقق من وجود بيانات المستخدم في الجلسة
if (!isset($_SESSION['fullName'], $_SESSION['email'], $_SESSION['password'])) {
    header("Location: signup.php");
    exit();
}

// توليد رمز جديد
$randomNumber = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['randomNumber'] = $randomNumber;

// إرسال البريد الإلكتروني
$to = $_SESSION['email'];
$subject = 'New Verification Code';
$message = "Dear {$_SESSION['fullName']},\n\nYour new verification code is: $randomNumber\n\nPlease enter this code to verify your account.";
$headers = 'From: your-email@example.com' . "\r\n" .
    'Reply-To: your-email@example.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

mail($to, $subject, $message, $headers);

// توجيه المستخدم إلى صفحة التحقق
header("Location: verify.php");
exit();
?>