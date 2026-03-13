<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php");
    exit();
}

$offers = $conn->query("
SELECT o.*, u.name, j.title 
FROM offer_letters o
JOIN users u ON o.user_id = u.id
JOIN jobs j ON o.job_id = j.id
ORDER BY o.sent_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Offer Letters</title>
<link rel="stylesheet" href="../assets/hr.css">
</head>
<body>

<div class="wrapper">

<div class="sidebar">
    <h2>HR Panel</h2>
    <ul>
        <li><a href="hr.php">Dashboard</a></li>
        <li><a href="hr_jobs.php">Jobs</a></li>
        <li><a href="hr_applications.php">Applications</a></li>
        <li><a href="hr_offers.php" class="active">Offer Letters</a></li>
        <li><a href="../auth/logout.php">Logout</a></li>
    </ul>
</div>

<div class="content">
<h2>Offer Letter Tracking</h2>

<table border="1" width="100%" cellpadding="10">
<tr>
    <th>Candidate</th>
    <th>Job</th>
    <th>Salary</th>
    <th>Status</th>
    <th>Signed By</th>
</tr>

<?php while($row = $offers->fetch_assoc()): ?>
<tr>
    <td><?= $row['name'] ?></td>
    <td><?= $row['title'] ?></td>
    <td><?= $row['salary'] ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['signature_name'] ?? '-' ?></td>
</tr>
<?php endwhile; ?>

</table>
</div>

</div>
</body>
</html>
