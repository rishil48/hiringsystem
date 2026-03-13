<?php
session_start();
include "config/db.php";
?>

<!DOCTYPE html>
<html>
<head>
<title>Home</title>

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
    margin-bottom:20px;
    font-size:40px;
}

.container{
    max-width:1100px;
    margin:auto;
}

/* Navbar */
.nav{
    position:absolute;
    left:40px;
    top:20px;
}
.nav a{
    color:white;
    margin-right:15px;
    text-decoration:none;
    font-weight:bold;
}

/* Login */
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
    margin-left:10px;
}

/* Glass Card */
.card{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    margin-bottom:30px;
    text-align:center;
}

/* Button */
.btn{
    display:inline-block;
    padding:10px 18px;
    background:#38bdf8;
    color:#000;
    text-decoration:none;
    border-radius:8px;
    font-weight:bold;
    transition:0.3s;
    margin-top:15px;
}
.btn:hover{
    background:#0ea5e9;
}

/* Features */
.features{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:25px;
    margin-top:30px;
}

.feature-card{
    background:rgba(255,255,255,0.08);
    padding:20px;
    border-radius:12px;
    text-align:center;
}

/* Image Section */
.image-section{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:25px;
    margin-top:30px;
}

.image-section img{
    width:100%;
    border-radius:15px;
    height:220px;
    object-fit:cover;
    box-shadow:0 8px 20px rgba(0,0,0,0.4);
}
</style>
</head>

<body>

<div class="nav">
    <a href="home.php">Home</a>
    <a href="index.php">Jobs</a>
    <a href="about.php">About</a>
    <a href="contact.php">Contact</a>
    <a href="feedback.php">Feedback</a>
</div>

<div class="top-login">
<?php if(!isset($_SESSION['id'])): ?>
    <a href="auth/login.php">Login</a>
<?php else: ?>
    <a href="dashboards/<?=$_SESSION['role']?>.php">Dashboard</a>
<?php endif; ?>
</div>

<h1>Welcome To Our Recruitment System</h1>

<div class="container">

    <!-- Hero Card -->
    
    <!-- Features Section -->
    <div class="features">
        <div class="feature-card">
            <h3>📄 Easy Applications</h3>
            <p>Apply for jobs in one click and track application status.</p>
        </div>

        <div class="feature-card">
            <h3>📝 Online MCQ Tests</h3>
            <p>Automatic MCQ test system for quick candidate evaluation.</p>
        </div>

        <div class="feature-card">
            <h3>🎥 Interview Scheduling</h3>
            <p>Online & Offline interview round management system.</p>
        </div>
    </div>

    <!-- Image Section -->
    <div class="image-section">
        <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df" alt="Interview">
        <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d" alt="Office Team">
        <img src="https://images.unsplash.com/photo-1492724441997-5dc865305da7" alt="Career Growth">
    </div>
<div class="card">
        <h2>Your Career Starts Here 🚀</h2>
        <p>
            Explore jobs, apply online, attend MCQ tests, and schedule interviews easily.
            A complete Recruitment Management System for Admin, HR, and Candidates.
        </p>
        <a href="index.php" class="btn">Explore Jobs</a>
    </div>

</div>

</body>
</html>