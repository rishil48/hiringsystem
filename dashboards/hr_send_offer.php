<?php
session_start();
include "../config/db.php";
include "../config/mail.php";

if($_SESSION['role']!='hr') exit();

$app_id=$_GET['app_id'];

$app=$conn->query("
SELECT a.*,u.name,u.email,j.title 
FROM applications a
JOIN users u ON a.user_id=u.id
JOIN jobs j ON a.job_id=j.id
WHERE a.id=$app_id
")->fetch_assoc();

if(isset($_POST['send'])){
    $salary=$_POST['salary'];
    $joining=$_POST['joining'];
    $msg=$_POST['message'];

    $conn->query("
    INSERT INTO offer_letters
    (application_id,user_id,job_id,salary,joining_date,message)
    VALUES
    ($app_id,{$app['user_id']},{$app['job_id']},'$salary','$joining','$msg')
    ");

    $body="
    <h3>Offer Letter</h3>
    <p>Dear {$app['name']},</p>
    <p>We are pleased to offer you the position of <b>{$app['title']}</b>.</p>
    <p><b>Salary:</b> $salary</p>
    <p><b>Joining Date:</b> $joining</p>
    <p>$msg</p>
    ";

    sendOfferMail($app['email'],"Offer Letter",$body);

    echo "<script>alert('Offer Sent');location.href='hr_applications.php';</script>";
}
?>

<form method="post">
<h2>Send Offer Letter</h2>
Salary <input name="salary" required><br><br>
Joining Date <input type="date" name="joining" required><br><br>
Message <textarea name="message" required></textarea><br><br>
<button name="send">Send Offer</button>
</form>
