<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
<title>Contact Us</title>

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
}
.container{
    max-width:600px;
    margin:auto;
}
.card{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
}
input, textarea{
    width:100%;
    padding:10px;
    margin-bottom:15px;
    border:none;
    border-radius:8px;
}
button{
    padding:10px 18px;
    background:#38bdf8;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
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

<h1>Contact Us</h1>

<div class="container">
    <div class="card">
        <form method="post">
            <input type="text" name="name" placeholder="Your Name" required>
            <input type="email" name="email" placeholder="Your Email" required>
            <textarea name="message" rows="4" placeholder="Your Message" required></textarea>
            <button type="submit">Send Message</button>
        </form>
    </div>
</div>

</body>
</html>