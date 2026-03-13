<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
?>

<!DOCTYPE html>
<html>
<head>
<title>My Applications</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f8;
    padding:30px;
}

.card{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-left:6px solid #2563eb;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
    border-radius:8px;
}

.status{ font-weight:bold; }
.pending{ color:#f59e0b; }
.interview{ color:#2563eb; }
.selected{ color:#16a34a; }
.rejected{ color:#dc2626; }

.round-box{
    margin-top:10px;
    padding:10px;
    background:#f1f5f9;
    border-radius:6px;
}
</style>
</head>

<body>

<h2>My Applied Jobs & Interview Details</h2>

<?php
$query = $conn->query("
    SELECT j.title, j.city, a.status, a.resume, 
           a.total_rounds, a.cleared_rounds
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.user_id = $user_id
");

if($query && $query->num_rows > 0):
    while($row = $query->fetch_assoc()):
    $statusClass = strtolower($row['status']);
?>

<div class="card">
    <h3><?= htmlspecialchars($row['title']) ?></h3>
    <p><strong>City:</strong> <?= htmlspecialchars($row['city']) ?></p>

    <p><strong>Status:</strong> 
        <span class="<?= $statusClass ?>">
            <?= htmlspecialchars($row['status']) ?>
        </span>
    </p>

    <p><strong>Resume:</strong> 
        <a href="../uploads/resumes/<?= $row['resume'] ?>" target="_blank">
            View Resume
        </a>
    </p>

    <div class="round-box">
        <strong>Total Rounds:</strong> <?= $row['total_rounds'] ?><br>
        <strong>Cleared:</strong> <?= $row['cleared_rounds'] ?><br>

        <?php if($row['total_rounds'] > 0 && $row['cleared_rounds'] == $row['total_rounds']): ?>
            <span style="color:green;font-weight:bold;">All Rounds Cleared ✅</span>
        <?php elseif($row['cleared_rounds'] > 0): ?>
            <span style="color:#2563eb;font-weight:bold;">
                Reached Round <?= $row['cleared_rounds'] ?>
            </span>
        <?php else: ?>
            <span style="color:#f59e0b;font-weight:bold;">
                Interview Not Started
            </span>
        <?php endif; ?>
    </div>
</div>

<?php
    endwhile;
else:
    echo "<p>No applications found.</p>";
endif;
?>

</body>
</html>