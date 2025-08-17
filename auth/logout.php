<?php
session_start();
unset($_SESSION['userId']);
header('Location: ./signin.php');
exit;
?>