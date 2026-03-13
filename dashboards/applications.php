<?php
session_start();
include "../config/db.php";
if ($_SESSION['role'] != 'admin') exit();
?>

<h2>All Applications</h2>

<table border="1" width="100%">
<tr>
<th>Candidate</th>
<th>Job</th>
<th>Resume</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php
$q = $conn->query("
SELECT a.*, u.name, j.title
FROM applications a
JOIN users u ON a.user_id = u.id
JOIN jobs j ON a.job_id = j.id
");

while ($r = $q->fetch_assoc()) {
    echo "<tr>
        <td>{$r['name']}</td>
        <td>{$r['title']}</td>
        <td><a href='../uploads/resumes/{$r['resume']}' target='_blank'>View</a></td>
        <td>{$r['status']}</td>
        <td>
            <a href='schedule_interview.php?id={$r['id']}'>Schedule Interview</a>
        </td>
    </tr>";
}
?>
</table>
