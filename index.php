<?php
session_start();
include "config/db.php";

/* 
   If user already logged in
   and you want to send them directly to dashboard
   then uncomment below lines:

if (isset($_SESSION['role'])) {
    header("Location: dashboards/" . $_SESSION['role'] . ".php");
    exit();
}
*/

// Fetch all jobs
$jobs = $conn->query("SELECT * FROM jobs ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Available Jobs</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body{
    background:linear-gradient(135deg,#1e3a8a,#2563eb,#0f172a);
    min-height:100vh;
    padding:40px;
    color:white;
}

h1{
    text-align:center;
    margin-bottom:40px;
}

.jobs{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:30px;
}

.card{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    padding:25px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    transition:0.3s;
}

.card:hover{
    transform:translateY(-8px);
}

.card h3{
    margin-bottom:10px;
}

.card p{
    margin-bottom:15px;
    font-size:14px;
}

.btn{
    display:inline-block;
    padding:10px 18px;
    background:#38bdf8;
    color:#000;
    text-decoration:none;
    border-radius:8px;
    font-weight:bold;
    transition:0.3s;
}

.btn:hover{
    background:#0ea5e9;
}
.top-login{
    position:absolute;
    right:40px;
    top:20px;
}

.top-login a{
    background:white;
    color:#000;
    padding:8px 15px;
    border-radius:6px;
    text-decoration:none;
    font-weight:bold;
}
</style>
</head>

<body>

<div class="top-login">
<?php if(!isset($_SESSION['id'])): ?>
    <a href="auth/login.php">Login</a>
<?php else: ?>
    <a href="dashboards/<?=$_SESSION['role']?>.php">Dashboard</a>
<?php endif; ?>
</div>

<h1>Available Jobs</h1>

<div class="jobs">

<?php while($job = $jobs->fetch_assoc()): ?>
    <div class="card">
        <h3><?= htmlspecialchars($job['title']) ?></h3>
        <p><?= htmlspecialchars($job['description']) ?></p>

        <?php if(isset($_SESSION['id'])): ?>
            <a href="dashboards/apply_job.php?job_id=<?= $job['id'] ?>" class="btn">
                Apply Now
            </a>
        <?php else: ?>
            <a href="auth/login.php?redirect=dashboards/apply_job.php?job_id=<?= $job['id'] ?>" class="btn">
                Apply Now
            </a>
        <?php endif; ?>
    </div>
<?php endwhile; ?>

</div>

</body>
</html>