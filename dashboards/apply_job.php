<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['job_id'])) {
    die("Invalid Job");
}

$job_id = (int)$_GET['job_id'];

if (isset($_POST['apply'])) {

    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $contact  = $_POST['contact'];
    $skills   = $_POST['skills'];
    $exp      = $_POST['experience'];
    $relocation = $_POST['relocation'];
    $joining = $_POST['joining'];
    $expected_salary = $_POST['expected_salary'];

    $resume = $_FILES['resume']['name'];
    $tmp    = $_FILES['resume']['tmp_name'];

    if (!is_dir("../uploads/resumes")) {
        mkdir("../uploads/resumes", 0777, true);
    }

    move_uploaded_file($tmp, "../uploads/resumes/".$resume);

    $uid = $_SESSION['id'];

    $conn->query("
        INSERT INTO applications
        (job_id, user_id, name, email, contact, skills, experience, relocation_response, immediate_joining, expected_salary, resume, status)
        VALUES
        ($job_id, $uid, '$name', '$email', '$contact', '$skills', '$exp', '$relocation', '$joining', '$expected_salary', '$resume', 'Pending')
    ");

    echo "<script>
        alert('Job Applied Successfully');
        window.location='user.php';
    </script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Apply Job</title>

<style>
body{
    font-family:Arial, sans-serif;
    background:#f1f5f9;
}

.form-box{
    width:480px;
    margin:60px auto;
    background:#ffffff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    margin-bottom:25px;
}

input, textarea{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:6px;
    font-size:14px;
}

textarea{
    resize:none;
}

.radio-group{
    margin-bottom:20px;
}

.radio-title{
    margin-bottom:10px;
    font-weight:bold;
}

.radio-option{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:8px;
}

.radio-option input{
    width:auto;
}

button{
    width:100%;
    padding:12px;
    background:#2563eb;
    color:#fff;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:16px;
    font-weight:bold;
}

button:hover{
    background:#1d4ed8;
}
</style>

</head>

<body>

<div class="form-box">
<h2>Apply Job</h2>

<form method="post" enctype="multipart/form-data">

    
    <!-- Relocation -->
    <div class="radio-group">
        <div class="radio-title">
            Are you willing to shift to another city?
        </div>

        <div class="radio-option">
            <input type="radio" name="relocation" value="Yes" required>
            <label>Yes</label>
        </div>

        <div class="radio-option">
            <input type="radio" name="relocation" value="No">
            <label>No</label>
        </div>
    </div>

    <!-- Immediate Joining -->
    <div class="radio-group">
        <div class="radio-title">
            Are you available for immediate joining?
        </div>

        <div class="radio-option">
            <input type="radio" name="joining" value="Yes" required>
            <label>Yes</label>
        </div>

        <div class="radio-option">
            <input type="radio" name="joining" value="No">
            <label>No</label>
        </div>
    </div>

    <!-- Expected Salary -->
    <input type="text" name="expected_salary" placeholder="Expected Salary (e.g. 4 LPA / 30000 per month)" required>

    <label><strong>Upload Resume</strong></label>
    <input type="file" name="resume" required>

    <button type="submit" name="apply">Apply Job</button>

</form>
</div>

</body>
</html>