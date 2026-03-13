<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Available Jobs</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    padding:30px;
}
.card{
    background:#fff;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
}
h3{
    margin:0 0 10px;
}
button{
    padding:8px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
.apply-btn{
    background:#2563eb;
    color:white;
}
.applied{
    background:green;
    color:white;
}
.date-info{
    background:#f0f4ff;
    padding:8px 12px;
    border-radius:6px;
    margin:10px 0;
    font-size:14px;
    color:#444;
}
.expired{
    background:#fee2e2;
    color:#b91c1c;
    padding:8px 15px;
    border:none;
    border-radius:5px;
    font-weight:bold;
}
</style>

</head>
<body>

<h2>Available Jobs</h2>

<?php
$jobs = $conn->query("SELECT * FROM jobs");
$today = date('Y-m-d'); // Aaj ki date

while($job = $jobs->fetch_assoc()){

    // Check already applied
    $check = $conn->query("SELECT * FROM applications 
                           WHERE user_id=$user_id 
                           AND job_id=".$job['id']);

    echo "<div class='card'>";
    echo "<h3>".$job['title']."</h3>";
    echo "<p><b>City:</b> ".$job['city']."</p>";
    echo "<p><b>Salary:</b> ₹".$job['salary']."</p>";
    echo "<p><b>Experience:</b> ".$job['experience']." Year</p>";
    echo "<p><b>Vacancy:</b> ".$job['vacancy']."</p>";

    // ✅ Start Date & End Date display
    echo "<div class='date-info'>";
    echo "📅 <b>Start Date:</b> ".date('d M Y', strtotime($job['start_date']))."&nbsp;&nbsp;";
    echo "⏳ <b>Last Date:</b> ".date('d M Y', strtotime($job['end_date']));
    echo "</div>";

    // ✅ Check if job is expired
    if($today > $job['end_date']){
        echo "<button class='expired' disabled>❌ Expired</button>";
    } elseif($check->num_rows > 0){
        echo "<button class='applied'>✅ Already Applied</button>";
    } else {
        echo "
        <a href='apply_job.php?job_id=".$job['id']."'>
            <button type='button' class='apply-btn'>Apply Now</button>
        </a>
        ";
    }

    echo "</div>";
}
?>

</body>
</html>