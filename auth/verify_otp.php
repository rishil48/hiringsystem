<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['otp'])) {
    header("Location: forgot_password.php");
    exit();
}

if (isset($_POST['verify'])) {

    $entered_otp = $_POST['otp'];

    if ($entered_otp == $_SESSION['otp']) {
        header("Location: reset_password.php");
        exit();
    } else {
        echo "<script>alert('Invalid OTP');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Verify OTP</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<form method="post" class="box">
    <h2>Enter OTP</h2>

    <input type="text" name="otp" placeholder="Enter 6 Digit OTP" required>

    <button name="verify">Verify OTP</button>

    <br><br>
    <a href="forgot_password.php">Back</a>
</form>

</body>
</html>