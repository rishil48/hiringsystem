<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    min-height:100vh;
    background:linear-gradient(-45deg,#0f172a,#1e3a8a,#2563eb,#0ea5e9);
    background-size:400% 400%;
    animation:gradientBG 12s ease infinite;
    color:#fff;
}

/* Animated Background */
@keyframes gradientBG{
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}

/* TOPBAR */
.topbar{
    position:relative;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
    backdrop-filter:blur(15px);
    background:rgba(255,255,255,0.1);
    box-shadow:0 5px 20px rgba(0,0,0,0.2);
}

.toggle-btn{
    position:absolute;
    left:20px;
    font-size:20px;
    background:#2563eb;
    color:white;
    border:none;
    padding:8px 14px;
    cursor:pointer;
    border-radius:8px;
    transition:0.3s;
}

.toggle-btn:hover{
    background:#1d4ed8;
    transform:scale(1.05);
}

/* SIDEBAR */
.sidebar{
    position:fixed;
    top:0;
    left:-260px;
    width:260px;
    height:100vh;
    background:rgba(0,0,0,0.6);
    backdrop-filter:blur(20px);
    padding-top:80px;
    transition:0.4s;
}

.sidebar.active{
    left:0;
}

.sidebar a{
    display:block;
    padding:15px 30px;
    color:#e2e8f0;
    text-decoration:none;
    transition:0.3s;
    font-weight:500;
}

.sidebar a:hover{
    background:#2563eb;
    padding-left:40px;
    border-left:5px solid #38bdf8;
}

/* MAIN CONTENT */
.main{
    padding:50px;
    transition:0.4s;
}

.main.shift{
    margin-left:260px;
}

/* CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:30px;
    margin-top:40px;
}

.card-link{
    text-decoration:none;
    color:inherit;
}

.card{
    padding:40px;
    border-radius:20px;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(15px);
    box-shadow:0 15px 35px rgba(0,0,0,0.3);
    text-align:center;
    font-size:18px;
    font-weight:bold;
    transition:0.4s;
}

.card:hover{
    transform:translateY(-10px) scale(1.03);
    box-shadow:0 20px 40px rgba(0,0,0,0.5);
    background:linear-gradient(135deg,#2563eb,#38bdf8);
}
</style>
</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
</div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
  <a href="admin.php">Dashboard</a>
  <a href="manage_hr.php">Manage HR</a>
  <a href="manage_users.php">Manage Users</a>
  <a href="jobs.php">Jobs</a>
  <a href="applications.php">Applications</a>
  <a href="interviews.php">Interviews</a>
  <a href="reports.php">Reports</a>
  <a href="../auth/logout.php">Logout</a>
</div>

<!-- MAIN -->
<div class="main" id="main">

    <div class="cards">
        <a href="manage_hr.php" class="card-link">
            <div class="card">Manage HR</div>
        </a>

        <a href="manage_users.php" class="card-link">
            <div class="card">Manage Users</div>
        </a>

        <a href="reports.php" class="card-link">
            <div class="card">View Reports</div>
        </a>

        <a href="jobs.php" class="card-link">
            <div class="card">Create Jobs</div>
        </a>
    </div>

</div>

<script>
function toggleSidebar(){
    document.getElementById("sidebar").classList.toggle("active");
    document.getElementById("main").classList.toggle("shift");
}
</script>

</body>
</html>