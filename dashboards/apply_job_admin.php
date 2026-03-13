<?php
session_start(); // 👈 must be first line
include "../config/db.php";

// Safe guard: only allow admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_POST['apply'])) {
    $user   = (int)$_POST['user_id'];
    $job    = (int)$_POST['job_id'];
    $resume = basename($_FILES['resume']['name']); // sanitize filename

    // Ensure uploads directory exists
    $uploadDir = "../uploads/resumes/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $resume);

    // Use prepared statement for safety
    $stmt = $conn->prepare("INSERT INTO applications (job_id, user_id, resume) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $job, $user, $resume);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Job Applied Successfully');</script>";
}
?>
<h2>Apply Job (Admin)</h2>

<form method="post" enctype="multipart/form-data">
    <label>Select User:</label><br>
    <select name="user_id">
        <?php
        $u = $conn->query("SELECT id, name FROM users WHERE role='user'");
        while ($row = $u->fetch_assoc()) {
            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
        }
        ?>
    </select><br><br>

    <label>Select Job:</label><br>
    <select name="job_id">
        <?php
        $j = $conn->query("SELECT id, title FROM jobs");
        while ($row = $j->fetch_assoc()) {
            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['title']) . "</option>";
        }
        ?>
    </select><br><br>

    <label>Upload Resume:</label><br>
    <input type="file" name="resume" required><br><br>

    <button name="apply">Apply Job</button>
</form>