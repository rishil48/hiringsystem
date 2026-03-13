<?php
session_start();
include "../config/db.php";
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['submit'])) {

    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo "<script>alert('Email not registered');</script>";
    } else {

        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['reset_email'] = $email;

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rishilshah4803@gmail.com';
            $mail->Password   = 'kdgbhptozfjhrnxq'; // your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('rishilshah4803@gmail.com', 'Hiring System');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body    = "
                <h2>Your OTP is: $otp</h2>
                <p>This OTP is valid for 5 minutes.</p>
            ";

            $mail->send();

            header("Location: verify_otp.php");
            exit();

        } catch (Exception $e) {
            echo "Mail Error: " . $mail->ErrorInfo;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Forgot Password</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<form method="post" class="box">
    <h2>Forgot Password</h2>

    <input type="email" name="email" placeholder="Enter Registered Email" required>

    <button name="submit">Send OTP</button>

    <br><br>
    <a href="login.php">Back to Login</a>
</form>

</body>
</html>