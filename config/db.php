<?php
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "LibraryManagement";
$mysqli = new mysqli($servername, $username, $password, $dbname);
if ($mysqli->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
?>