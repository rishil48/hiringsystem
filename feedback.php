<?php
session_start();

/* Create dummy data only first time */
if (!isset($_SESSION['feedback'])) {
    $_SESSION['feedback'] = [
        [
            "name" => "Rahul Sharma",
            "rating" => "⭐⭐⭐⭐⭐ Excellent",
            "message" => "The recruitment process was very smooth and professional."
        ],
        [
            "name" => "Priya Patel",
            "rating" => "⭐⭐⭐⭐ Good",
            "message" => "MCQ test system was easy to use. Interview scheduling was clear."
        ],
        [
            "name" => "Amit Verma",
            "rating" => "⭐⭐⭐ Average",
            "message" => "Overall good experience but interview timing could improve."
        ]
    ];
}

/* When form submitted */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = htmlspecialchars($_POST['name']);
    $rating = htmlspecialchars($_POST['rating']);
    $message = htmlspecialchars($_POST['message']);

    $_SESSION['feedback'][] = [
        "name" => $name,
        "rating" => $rating,
        "message" => $message
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Feedback</title>

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
    max-width:800px;
    margin:auto;
}

.card{
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(15px);
    padding:25px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
    margin-bottom:25px;
}

input, textarea, select{
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

button:hover{
    background:#0ea5e9;
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

.feedback-box{
    background:rgba(255,255,255,0.08);
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
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

<h1>Candidate Feedback</h1>

<div class="container">

    <!-- Feedback Form -->
    <div class="card">
        <h3>Give Your Feedback</h3>
        <form method="post">
            <input type="text" name="name" placeholder="Your Name" required>
            
            <select name="rating" required>
                <option value="">Select Rating</option>
                <option>⭐⭐⭐⭐⭐ Excellent</option>
                <option>⭐⭐⭐⭐ Good</option>
                <option>⭐⭐⭐ Average</option>
                <option>⭐⭐ Poor</option>
            </select>

            <textarea name="message" rows="4" placeholder="Write your feedback..." required></textarea>

            <button type="submit">Submit Feedback</button>
        </form>
    </div>

    <!-- Feedback Display -->
    <div class="card">
        <h3>Recent Feedback</h3>

        <?php foreach($_SESSION['feedback'] as $fb): ?>
            <div class="feedback-box">
                <strong><?= $fb['name']; ?></strong><br>
                <?= $fb['rating']; ?><br>
                <p><?= $fb['message']; ?></p>
            </div>
        <?php endforeach; ?>

    </div>

</div>

</body>
</html>