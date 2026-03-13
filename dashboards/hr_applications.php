<?php
session_start();
include "../config/db.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') exit();

$q = $conn->query("
SELECT u.name, j.title
FROM applications a
JOIN users u ON a.user_id=u.id
JOIN jobs j ON a.job_id=j.id
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Applications</title>
<link rel="stylesheet" href="../assets/hr.css">
</head>
<body>

<div class="wrapper">

<div class="sidebar">
    <h2>HR Panel</h2>
    <ul>
        <li><a href="hr.php">Dashboard</a></li>
        <li><a href="hr_jobs.php">Jobs</a></li>
        <li><a href="hr_applications.php" class="active">Applications</a></li>
        <li><a href="../auth/logout.php">Logout</a></li>
    </ul>
</div>

<div class="content">
<div class="header"><h1>Applications</h1></div>

<table>
<tr><th>Candidate</th><th>Job</th></tr>
<?php while($r=$q->fetch_assoc()){ ?>
<tr>
<td><?= $r['name'] ?></td>
<td><?= $r['title'] ?></td>
</tr>
<?php } ?>
</table>
</div>

</div>
</body>
</html>
