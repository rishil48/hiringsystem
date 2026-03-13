<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if (isset($_POST['reset'])) {

    $newpass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_SESSION['reset_email'];

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
    $stmt->bind_param("ss", $newpass, $email);
    $stmt->execute();

    session_destroy();

    echo "<script>alert('Password Reset Successful'); window.location='login.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Reset Password</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<form method="post" class="box">
    <h2>Reset Password</h2>

    <input type="password" name="password" placeholder="Enter New Password" required>

    <button name="reset">Reset Password</button>

    <br><br>
    <a href="login.php">Back to Login</a>
</form>

</body>
</html>