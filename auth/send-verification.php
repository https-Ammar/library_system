<?php
session_start();
require('../config/db.php');

// توليد رمز تحقق عشوائي مكون من 6 أرقام
$randomNumber = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

// تخزين الرمز في الجلسة
$_SESSION['randomNumber'] = $randomNumber;

// بيانات المستخدم من الجلسة
$fullName = $_SESSION['fullName'];
$email = $_SESSION['email'];

// إرسال البريد الإلكتروني
$to = $email;
$subject = 'Verification Code';
$message = "Dear $fullName,\n\nYour verification code is: $randomNumber\n\nPlease enter this code to verify your account.";
$headers = 'From: your-email@example.com' . "\r\n" .
    'Reply-To: your-email@example.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

// إرسال البريد (في بيئة حقيقية استخدم مكتبة مثل PHPMailer)
mail($to, $subject, $message, $headers);

// توجيه المستخدم إلى صفحة التحقق
header("Location: verify.php");
exit();
?>