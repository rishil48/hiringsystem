<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php");
    exit();
}

$page  = $_GET['page'] ?? 'dashboard';
$hr_id = $_SESSION['id']; // ✅ Logged in HR ka ID

/* COUNTS — sirf is HR ki jobs ke applications aur rounds */
$apps = $conn->query("
    SELECT COUNT(*) c FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.hr_id = $hr_id
")->fetch_assoc()['c'];

$ints = $conn->query("
    SELECT COUNT(*) c FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE j.hr_id = $hr_id
")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html>
<head>
<title>HR Panel</title>
<style>
body{margin:0;font-family:Arial;background:#f4f7fb}
.wrapper{display:flex}
.sidebar{width:220px;background:#0b1d39;height:100vh;color:#fff;position:sticky;top:0}
.sidebar h2{padding:20px}
.sidebar a{display:block;padding:15px 20px;color:#fff;text-decoration:none}
.sidebar a.active,.sidebar a:hover{background:#2563eb}
.content{flex:1;padding:30px}
.card-box{display:flex;gap:20px;margin-bottom:30px}
.stat-card{background:#fff;padding:30px;border-radius:10px;flex:1;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.07)}
.stat-card h1{color:#2563eb;font-size:40px;margin:10px 0}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:12px;border:1px solid #ddd;text-align:center}
th{background:#2563eb;color:#fff}
.btn{padding:6px 12px;border-radius:5px;text-decoration:none;color:#fff;font-size:13px}
.green{background:#16a34a}
.red{background:#dc2626}
.blue{background:#2563eb}
form input,form select,form textarea{
    width:100%;padding:10px;margin-bottom:10px;
    border:1px solid #ccc;border-radius:5px;box-sizing:border-box;
}
.job-box{
    background:#fff;padding:20px;margin-bottom:15px;
    border-radius:8px;border-left:4px solid #2563eb;
    box-shadow:0 2px 6px rgba(0,0,0,0.06);
}
.job-box h3{margin:0 0 8px;color:#0b1d39}
.date-label{display:block;font-weight:bold;margin-bottom:4px;color:#333;font-size:14px}
.date-group{margin-bottom:10px}
.date-group input[type="date"]{
    width:100%;padding:10px;border:1px solid #ccc;
    border-radius:5px;font-size:14px;color:#333;
    background:#fff;cursor:pointer;box-sizing:border-box;
}
.no-data{text-align:center;padding:40px;color:#888;background:#fff;border-radius:8px}
.success{color:green;font-weight:bold;background:#f0fdf4;padding:10px;border-radius:5px;margin-bottom:10px}
</style>
</head>

<body>
<div class="wrapper">

<div class="sidebar">
    <h2>HR Panel</h2>
    <a href="?page=dashboard"    class="<?= $page=='dashboard'   ?'active':'' ?>">Dashboard</a>
    <a href="?page=post_job"     class="<?= $page=='post_job'    ?'active':'' ?>">Post Job</a>
    <a href="?page=applications" class="<?= $page=='applications'?'active':'' ?>">Applications</a>
    <a href="interview_round.php">Interview Rounds</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="content">

<!-- ==================== DASHBOARD ==================== -->
<?php if($page == 'dashboard'): ?>

<h2>Welcome, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h2>

<div class="card-box">
    <div class="stat-card">
        <h3>My Job Postings</h3>
        <?php
        $my_jobs_count = $conn->query("SELECT COUNT(*) c FROM jobs WHERE hr_id = $hr_id")->fetch_assoc()['c'];
        ?>
        <h1><?= $my_jobs_count ?></h1>
    </div>
    <div class="stat-card">
        <h3>Total Applications</h3>
        <h1><?= $apps ?></h1>
    </div>
    <div class="stat-card">
        <h3>Interview Rounds</h3>
        <h1><?= $ints ?></h1>
    </div>
</div>

<h2>My Posted Jobs</h2>

<?php
// ✅ Sirf is HR ki jobs
$jobs = $conn->query("SELECT * FROM jobs WHERE hr_id = $hr_id ORDER BY id DESC");

if($jobs->num_rows == 0):
?>
    <div class="no-data">📭 Aapne abhi koi job post nahi ki hai.<br>
        <a href="?page=post_job" class="btn blue" style="display:inline-block;margin-top:10px">
            + Post Job
        </a>
    </div>
<?php else:
    while($job = $jobs->fetch_assoc()):
?>
<div class="job-box">
    <h3>💼 <?= htmlspecialchars($job['title']) ?></h3>
    <p><b>City:</b> <?= htmlspecialchars($job['city']) ?></p>
    <p><b>Salary:</b> ₹<?= htmlspecialchars($job['salary']) ?></p>
    <p><b>Vacancy:</b> <?= $job['vacancy'] ?></p>
    <?php if($job['start_date']): ?>
    <p><b>📅 Start:</b> <?= date('d M Y', strtotime($job['start_date'])) ?>
       &nbsp;|&nbsp;
       <b>⏳ End:</b> <?= date('d M Y', strtotime($job['end_date'])) ?>
    </p>
    <?php endif; ?>
</div>
<?php endwhile; endif; ?>

<?php endif; ?>


<!-- ==================== POST JOB ==================== -->
<?php if($page == 'post_job'): ?>

<h2>Post New Job</h2>

<?php
if(isset($_POST['add_job'])){
    $title       = $conn->real_escape_string($_POST['title']);
    $city        = $conn->real_escape_string($_POST['city']);
    $salary      = $conn->real_escape_string($_POST['salary']);
    $experience  = $conn->real_escape_string($_POST['experience']);
    $vacancy     = (int)$_POST['vacancy'];
    $description = $conn->real_escape_string($_POST['description']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];

    // ✅ hr_id save ho raha hai
    $conn->query("
        INSERT INTO jobs (title, city, salary, experience, vacancy, description, start_date, end_date, hr_id, created_at)
        VALUES ('$title','$city','$salary','$experience','$vacancy','$description','$start_date','$end_date','$hr_id', NOW())
    ");

    echo "<p class='success'>✅ Job Posted Successfully!</p>";
}
?>

<form method="POST">
    <input type="text"   name="title"       placeholder="Job Title"            required>
    <input type="text"   name="city"        placeholder="City"                 required>
    <input type="number" name="salary"      placeholder="Salary"               required>
    <input type="number" name="experience"  placeholder="Experience (Years)"   required>
    <input type="number" name="vacancy"     placeholder="Vacancy"              required>
    <textarea            name="description" placeholder="Job Description" rows="4" required></textarea>

    <div class="date-group">
        <label class="date-label">📅 Start Date</label>
        <input type="date" name="start_date" required>
    </div>
    <div class="date-group">
        <label class="date-label">📅 End Date</label>
        <input type="date" name="end_date" required>
    </div>

    <button type="submit" name="add_job" class="btn blue" style="padding:10px 25px">Post Job</button>
</form>

<?php endif; ?>


<!-- ==================== APPLICATIONS ==================== -->
<?php if($page == 'applications'): ?>

<h2>Applications</h2>

<?php
// ✅ Sirf is HR ki jobs ke applications
$q = $conn->query("
    SELECT a.id, a.status, u.name, j.title
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN jobs j   ON a.job_id = j.id
    WHERE j.hr_id = $hr_id
    ORDER BY a.applied_at DESC
");
?>

<?php if($q->num_rows == 0): ?>
    <div class="no-data">📭 Abhi koi application nahi aayi aapki jobs pe.</div>
<?php else: ?>
<table>
    <tr>
        <th>Candidate</th>
        <th>Job</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php while($r = $q->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['title']) ?></td>
        <td><?= ucfirst($r['status']) ?></td>
        <td>
            <a class="btn blue"  href="add_rounds.php?application_id=<?= $r['id'] ?>">Schedule Round</a>
            <a class="btn green" href="action.php?select=<?= $r['id'] ?>">Select</a>
            <a class="btn red"   href="action.php?reject=<?= $r['id'] ?>">Reject</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php endif; ?>

<?php endif; ?>

</div>
</div>
</body>
</html>