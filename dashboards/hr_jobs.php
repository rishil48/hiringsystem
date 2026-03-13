<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";

if(isset($_POST['post_job'])){

    $title = mysqli_real_escape_string($conn,$_POST['title']);
    $city = mysqli_real_escape_string($conn,$_POST['city']);
    $salary = mysqli_real_escape_string($conn,$_POST['salary']);
    $experience = intval($_POST['experience']);
    $vacancy = intval($_POST['vacancy']);
    $description = mysqli_real_escape_string($conn,$_POST['description']);

    $insert = $conn->query("
        INSERT INTO jobs 
        (title, city, salary, experience, description, vacancy, created_at)
        VALUES 
        ('$title','$city','$salary','$experience','$description','$vacancy',NOW())
    ");

    if($insert){
        $message = "Job Posted Successfully!";
    }else{
        $message = "Error Posting Job!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Post Job</title>
<style>
body{font-family:Arial;background:#f4f7fb;margin:0;padding:40px}
.container{max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:10px}
input,textarea{
width:100%;
padding:10px;
margin-bottom:15px;
border:1px solid #ccc;
border-radius:5px;
}
button{
background:#2563eb;
color:white;
padding:10px 20px;
border:none;
border-radius:5px;
cursor:pointer;
}
button:hover{
background:#1e40af;
}
.success{color:green;font-weight:bold;}
.error{color:red;font-weight:bold;}
</style>
</head>
<body>

<div class="container">

<h2>Post New Job</h2>

<?php if($message): ?>
<p class="<?= $insert?'success':'error' ?>">
<?= $message ?>
</p>
<?php endif; ?>

<form method="POST">

<input type="text" name="title" placeholder="Job Title" required>

<input type="text" name="city" placeholder="City" required>

<input type="text" name="salary" placeholder="Salary" required>

<input type="number" name="experience" placeholder="Experience (Years)" required>

<input type="number" name="vacancy" placeholder="Vacancy" required>

<textarea name="description" placeholder="Job Description" required></textarea>

<button type="submit" name="post_job">Post Job</button>

</form>

</div>

</body>
</html>