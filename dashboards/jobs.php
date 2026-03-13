<?php
session_start();
include "../config/db.php";

$message = "";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr')) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_POST['create_job'])) {

    $title = $_POST['title'];
    $city = $_POST['city'];
    $salary = $_POST['salary'];
    $experience = $_POST['experience'];
    $vacancy = $_POST['vacancy'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO jobs (title, city, salary, experience, description, vacancy, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssississ", $title, $city, $salary, $experience, $description, $vacancy, $start_date, $end_date);

    if ($stmt->execute()) {
        $job_id = $conn->insert_id;
        header("Location: add_rounds.php?job_id=" . $job_id);
        exit();
    } else {
        $message = "Error creating job!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Create Job</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}

body{
    background: linear-gradient(135deg,#0f172a,#1e293b);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.container{
    background:#1e293b;
    padding:30px;
    border-radius:12px;
    width:420px;
    box-shadow:0 10px 25px rgba(0,0,0,0.4);
    color:white;
}

h2{text-align:center;margin-bottom:20px;}

input, textarea{
    width:100%;
    padding:10px;
    margin-bottom:15px;
    border:none;
    border-radius:8px;
    background:#ffffff;
    color:#0f172a;
    font-size:14px;
}

input::placeholder, textarea::placeholder{
    color:#64748b;
}

/* ✅ Date input fix */
input[type="date"]{
    width:100%;
    padding:10px;
    border:none;
    border-radius:8px;
    background:#ffffff;
    color:#0f172a;
    font-size:14px;
    cursor:pointer;
    margin-bottom:0;
}

input[type="date"]::-webkit-calendar-picker-indicator{
    cursor:pointer;
    opacity:0.7;
}

.date-group{
    margin-bottom:15px;
}

.date-group label{
    display:block;
    font-size:13px;
    color:#94a3b8;
    margin-bottom:6px;
    padding-left:2px;
}

button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#06b6d4;
    color:white;
    font-weight:600;
    cursor:pointer;
    font-size:15px;
}

button:hover{background:#0891b2;}

.success{color:#22c55e;text-align:center;margin-bottom:10px;}
.error{color:#ef4444;text-align:center;margin-bottom:10px;}
</style>
</head>

<body>

<div class="container">
<h2>Create Job Profile</h2>

<?php if(!empty($message)){ ?>
<div class="error"><?= $message ?></div>
<?php } ?>

<form method="post">
    <input type="text" name="title" placeholder="Job Name" required>
    <input type="text" name="city" placeholder="City" required>
    <input type="number" name="salary" placeholder="Salary" required>
    <input type="text" name="experience" placeholder="Experience (e.g. 2 Years)" required>
    <input type="number" name="vacancy" placeholder="Vacancy" required>
    <textarea name="description" placeholder="Job Description" rows="4" required></textarea>

    <div class="date-group">
        <label>📅 Start Date</label>
        <input type="date" name="start_date" required>
    </div>

    <div class="date-group">
        <label>📅 End Date</label>
        <input type="date" name="end_date" required>
    </div>

    <button name="create_job">Create Job</button>
</form>

</div>

</body>
</html>