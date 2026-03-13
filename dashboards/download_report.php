<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$users = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$hrs   = $conn->query("SELECT COUNT(*) c FROM users WHERE role='hr'")->fetch_assoc()['c'];
$jobs  = $conn->query("SELECT COUNT(*) c FROM jobs")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Report</title>

<style>
body{
    font-family: Arial, sans-serif;
    padding:40px;
}

h2{
    text-align:center;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:30px;
}

table, th, td{
    border:1px solid black;
}

th, td{
    padding:12px;
    text-align:left;
}

.print-btn{
    margin-top:30px;
    padding:10px 20px;
    background:#007bff;
    color:white;
    border:none;
    cursor:pointer;
}
</style>

</head>
<body>

<h2>Admin System Report</h2>

<table>
<tr>
    <th>Report Type</th>
    <th>Count</th>
</tr>
<tr>
    <td>Total Users</td>
    <td><?= $users ?></td>
</tr>
<tr>
    <td>Total HR</td>
    <td><?= $hrs ?></td>
</tr>
<tr>
    <td>Total Jobs</td>
    <td><?= $jobs ?></td>
</tr>
</table>

<center>
<button class="print-btn" onclick="window.print()">Download / Save as PDF</button>
</center>

</body>
</html> 