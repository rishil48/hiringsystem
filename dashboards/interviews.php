<?php
session_start();
include "../config/db.php";

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['admin','hr'])){
    header("Location: ../auth/login.php");
    exit();
}

$q = $conn->query("
SELECT i.*,u.name,j.title
FROM interviews i
JOIN applications a ON i.application_id=a.id
JOIN users u ON a.user_id=u.id
JOIN jobs j ON a.job_id=j.id
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Interview Management</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<h2>Interview Management</h2>

<div class="card">
<table>
<tr>
<th>Candidate</th>
<th>Job</th>
<th>Date</th>
<th>Time</th>
<th>Mode</th>
<th>Video Call</th>
<th>Status</th>
</tr>

<?php while($r=$q->fetch_assoc()){ ?>
<tr>
<td><?= $r['name'] ?></td>
<td><?= $r['title'] ?></td>
<td><?= $r['interview_date'] ?></td>
<td><?= $r['interview_time'] ?></td>
<td><?= $r['mode'] ?></td>
<td>
<?php if($r['mode']=="Online" && !empty($r['video_link'])){ ?>
<a class="btn-video" href="<?= $r['video_link'] ?>" target="_blank">🎥 Join Call</a>
<?php } else { ?>
Offline Interview
<?php } ?>
</td>
<td><?= $r['status'] ?></td>
</tr>
<?php } ?>

</table>
</div>

</body>
</html>
