<?php
$servername = "localhost";
$username = "u596103429_elhoda";
$password = "#fAGpBf0cB";
$dbname = "u596103429_noor";

$mysqli = new mysqli($servername, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

$mysqli->query("SET time_zone = '+02:00'");
?>
