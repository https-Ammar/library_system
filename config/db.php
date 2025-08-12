<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'LibraryManagement');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die('Database connection failed: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

function query($sql)
{
    global $mysqli;
    $result = $mysqli->query($sql);
    if (!$result) {
        die('Query error: ' . $mysqli->error);
    }
    return $result;
}
?>