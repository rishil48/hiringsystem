<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','hr'])) {
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['schedule'])){
    $application_id = $_POST['application_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $mode = $_POST['mode'];
    $video_link = $_POST['video_link'];

    $conn->query("
        INSERT INTO interviews
        (application_id, interview_date, interview_time, mode, video_link, status)
        VALUES
        ('$application_id','$date','$time','$mode','$video_link','Scheduled')
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Schedule Interview</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<h2>Schedule Interview</h2>

<form method="post" class="card">
<select name="application_id" required>
<option value="">Select Candidate</option>
<?php
$q = $conn->query("
SELECT a.id,u.name,j.title
FROM applications a
JOIN users u ON a.user_id=u.id
JOIN jobs j ON a.job_id=j.id
");
while($r=$q->fetch_assoc()){
    echo "<option value='{$r['id']}'>{$r['name']} - {$r['title']}</option>";
}
?>
</select>

<input type="date" name="date" required>
<input type="time" name="time" required>

<select name="mode" required>
<option value="">Select Mode</option>
<option value="Online">Online</option>
<option value="Offline">Offline</option>
</select>

<input type="url" name="video_link" placeholder="Google Meet / Zoom Link">

<button name="schedule">Schedule Interview</button>
</form>

</body>
</html>
