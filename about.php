<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
<title>About Us</title>

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
    margin-bottom:30px;
    font-size:38px;
}

.container{
    max-width:1100px;
    margin:auto;
}

.card{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    margin-bottom:30px;
}

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

.section-title{
    font-size:24px;
    margin-bottom:15px;
}

.features{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

.feature-box{
    background:rgba(255,255,255,0.08);
    padding:20px;
    border-radius:12px;
}

.image-section{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:20px;
    margin-top:20px;
}

.image-section img{
    width:100%;
    height:220px;
    object-fit:cover;
    border-radius:15px;
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

<h1>About Our Recruitment Management System</h1>

<div class="container">

    <!-- Introduction -->
    <div class="card">
        <div class="section-title">📌 Project Overview</div>
        <p>
            The Recruitment Management System is a web-based application designed
            to streamline and automate the hiring process. It provides a centralized
            platform for Admin, HR, and Candidates to interact efficiently.
        </p>

        <p style="margin-top:15px;">
            This system reduces manual paperwork, improves communication, 
            and ensures a smooth recruitment workflow from job posting to final selection.
        </p>
    </div>

    <!-- Mission & Vision -->
    <div class="card">
        <div class="section-title">🎯 Our Mission</div>
        <p>
            To provide a transparent, efficient, and user-friendly recruitment platform
            that connects talented candidates with the right opportunities.
        </p>

        <div class="section-title" style="margin-top:20px;">🌟 Our Vision</div>
        <p>
            To become a smart digital recruitment solution that simplifies hiring 
            and enhances the overall candidate experience.
        </p>
    </div>

    <!-- Features -->
    <div class="card">
        <div class="section-title">⚙ Key Features</div>

        <div class="features">
            <div class="feature-box">
                <h3>👨‍💼 Admin Module</h3>
                <p>Manage users, monitor recruitment process, and control system settings.</p>
            </div>

            <div class="feature-box">
                <h3>📋 HR Module</h3>
                <p>Post jobs, conduct MCQ tests, schedule interviews (Online/Offline).</p>
            </div>

            <div class="feature-box">
                <h3>👩‍🎓 Candidate Module</h3>
                <p>Apply for jobs, attend tests, track application status, and give feedback.</p>
            </div>
        </div>
    </div>

    <!-- Images Section -->
    <div class="card">
        <div class="section-title">🏢 Our Working Environment</div>

        <div class="image-section">
            <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d" alt="Office Team">
            <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df" alt="Interview">
            <img src="https://images.unsplash.com/photo-1492724441997-5dc865305da7" alt="Career Growth">
        </div>
    </div>

</div>

</body>
</html>