<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "hiring_system";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8mb4');

$conn->query("SET time_zone = '+00:00'");
?>