<?php
// dashboards/index.php  (User Dashboard)
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['id'];

$totalApplied   = $conn->query("SELECT COUNT(*) c FROM applications WHERE user_id=$user_id")->fetch_assoc()['c'];
$totalInterview = $conn->query("SELECT COUNT(*) c FROM applications WHERE user_id=$user_id AND status='interview'")->fetch_assoc()['c'];
$totalSelected  = $conn->query("SELECT COUNT(*) c FROM applications WHERE user_id=$user_id AND status='selected'")->fetch_assoc()['c'];

// ── MCQ Tests — round_type EMPTY bhi handle karo ──────────────────────────
$mcq_tests = $conn->query("
    SELECT ir.id AS round_id, ir.round_title, ir.round_name,
           ir.scheduled_at, ir.round_date, ir.status, ir.result,
           j.title AS job_title, a.id AS app_id,
           (SELECT COUNT(*) FROM interview_questions WHERE round_id = ir.id) AS q_count,
           (SELECT COUNT(*) FROM mcq_answers WHERE round_id = ir.id AND application_id = a.id) AS answered
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = $user_id
      AND ir.status IN ('ongoing','completed','Ongoing','Completed')
      AND (
            ir.round_type IN ('AI MCQ','ai_mcq','Ai MCQ')
         OR ir.round_type = ''
         OR ir.round_type IS NULL
         OR (SELECT COUNT(*) FROM interview_questions WHERE round_id = ir.id) > 0
      )
    ORDER BY ir.id DESC
");

$mcq_count   = $mcq_tests ? $mcq_tests->num_rows : 0;

$mcq_pending = $conn->query("
    SELECT COUNT(*) AS c
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    WHERE a.user_id = $user_id
      AND ir.status IN ('ongoing','Ongoing')
      AND (
            ir.round_type IN ('AI MCQ','ai_mcq','Ai MCQ')
         OR ir.round_type = ''
         OR ir.round_type IS NULL
         OR (SELECT COUNT(*) FROM interview_questions WHERE round_id = ir.id) > 0
      )
      AND (SELECT COUNT(*) FROM mcq_answers WHERE round_id = ir.id AND application_id = a.id) = 0
")->fetch_assoc()['c'];

// All rounds
$rounds = $conn->query("
    SELECT ir.id AS round_id, ir.round_title, ir.round_name,
           ir.round_type, ir.scheduled_at, ir.round_date,
           ir.status, ir.result, j.title AS job_title
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = $user_id
    ORDER BY ir.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial; display: flex; }

.sidebar {
    width: 220px; height: 100vh; background: #0f1e46;
    padding-top: 30px; position: fixed;
    top: 0; left: 0; overflow-y: auto;
}
.sidebar h2 { text-align: center; color: white; padding-bottom: 10px; }
.sidebar a  { display: block; padding: 15px 25px; color: #ccc; text-decoration: none; }
.sidebar a:hover, .sidebar a.active { background: #1e3a8a; color: white; }
.notif-dot {
    display: inline-block; width: 8px; height: 8px;
    background: #facc15; border-radius: 50%;
    margin-left: 8px; vertical-align: middle;
    animation: blink 1.4s infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

.main {
    flex: 1; margin-left: 220px;
    background: linear-gradient(to bottom, #3b5bb5, #1e3a8a);
    padding: 40px; color: white; min-height: 100vh;
}

/* Stat Cards */
.card-container { display: flex; gap: 30px; margin-top: 40px; flex-wrap: wrap; }
.card {
    flex: 1; min-width: 200px; padding: 40px;
    background: rgba(255,255,255,.15); border-radius: 20px;
    text-align: center; font-size: 20px; font-weight: bold; transition: .3s;
}
.card:hover { transform: scale(1.05); background: rgba(255,255,255,.25); }
.number { font-size: 40px; margin-top: 15px; }

/* MCQ section */
.section-box { background: white; color: black; padding: 24px; border-radius: 15px; margin-top: 36px; }
.section-box h2 { margin: 0 0 18px; font-size: 18px; color: #0f1e46; display: flex; align-items: center; gap: 10px; }
.badge-new {
    background: #facc15; color: #78350f; font-size: 11px;
    padding: 2px 9px; border-radius: 20px; font-weight: 700; animation: blink 1.4s infinite;
}
.mcq-item {
    display: flex; justify-content: space-between; align-items: center;
    gap: 14px; flex-wrap: wrap; border: 1px solid #e5e7eb;
    border-radius: 10px; padding: 15px 18px; margin-bottom: 12px;
    border-left: 5px solid #f59e0b; background: #fffbeb; transition: box-shadow .2s;
}
.mcq-item:hover { box-shadow: 0 2px 12px rgba(30,58,138,.1); }
.mcq-item.done  { border-left-color: #16a34a; background: #f0fdf4; }
.mcq-item h3    { font-size: 15px; font-weight: 700; color: #0f1e46; margin: 0 0 3px; }
.mcq-item p     { font-size: 13px; color: #6b7280; margin: 0 0 7px; }
.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip  { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
.c-blue   { background: #dbeafe; color: #1e40af; }
.c-gray   { background: #f3f4f6; color: #374151; }
.c-green  { background: #dcfce7; color: #166534; }
.c-yellow { background: #fef9c3; color: #854d0e; }

.start-btn {
    background: linear-gradient(135deg,#1e3a8a,#2563eb);
    color: white; padding: 10px 22px; border-radius: 8px;
    font-size: 14px; font-weight: 700; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px; transition: all .2s;
}
.start-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(30,58,138,.4); color: white; }
.done-badge {
    background: #dcfce7; color: #166534; border: 1px solid #86efac;
    padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
}
.view-link { font-size: 12px; color: #2563eb; text-decoration: none; display: block; text-align: center; margin-top: 5px; }
.no-data { text-align: center; padding: 24px; color: #9ca3af; font-size: 14px; border: 1px dashed #d1d5db; border-radius: 8px; }

/* Rounds table */
.round-box { background: white; color: black; padding: 20px; border-radius: 15px; margin-top: 36px; }
.round-box h2   { margin: 0 0 16px; color: #0f1e46; font-size: 18px; }
.round-box table { width: 100%; border-collapse: collapse; }
.round-box th   { background: #1e3a8a; color: white; padding: 10px 12px; text-align: left; font-size: 13px; }
.round-box td   { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
.round-box tr:last-child td { border-bottom: none; }
.round-box tr:hover td { background: #f8fafc; }

.type-badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.t-mcq  { background: #f3e8ff; color: #7e22ce; }
.t-tech { background: #fef3c7; color: #92400e; }
.t-hr   { background: #dcfce7; color: #065f46; }

.status-badge { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.s-pending   { background: #fef9c3; color: #854d0e; }
.s-ongoing   { background: #dbeafe; color: #1e40af; }
.s-completed { background: #dcfce7; color: #166534; }

.r-pass { color: #16a34a; font-weight: 700; }
.r-fail { color: #dc2626; font-weight: 700; }
</style>
</head>
<body>

<div class="sidebar">
    <h2>User Panel</h2>
    <a href="index.php" class="active">Dashboard</a>
    <a href="my_applications.php">My Applications</a>
    <a href="available_jobs.php">Available Jobs</a>
    <a href="my_tests.php">
        My Tests
        <?php if($mcq_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="main">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>

    <div class="card-container">
        <div class="card">Total Applied<div class="number"><?= $totalApplied ?></div></div>
        <div class="card">In Interview<div class="number"><?= $totalInterview ?></div></div>
        <div class="card">Selected<div class="number"><?= $totalSelected ?></div></div>
        <div class="card" style="border:2px solid rgba(250,204,21,.6)">
            MCQ Tests<div class="number"><?= $mcq_count ?></div>
            <?php if($mcq_pending > 0): ?>
                <div style="font-size:13px;margin-top:6px;color:#facc15"><?= $mcq_pending ?> Pending ⚡</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MCQ Tests -->
    <div class="section-box">
        <h2>
            🤖 My MCQ Tests
            <?php if($mcq_pending > 0): ?><span class="badge-new"><?= $mcq_pending ?> NEW</span><?php endif; ?>
        </h2>
        <?php if ($mcq_count === 0): ?>
            <div class="no-data">Abhi tak koi MCQ test assign nahi hua hai.</div>
        <?php else:
            $mcq_tests->data_seek(0);
            while ($r = $mcq_tests->fetch_assoc()):
                $title   = !empty(trim($r['round_title'])) ? $r['round_title'] : ($r['round_name'] ?? 'MCQ Round');
                $is_done = ($r['answered'] > 0);
        ?>
        <div class="mcq-item <?= $is_done ? 'done' : '' ?>">
            <div>
                <h3><?= htmlspecialchars($title) ?></h3>
                <p>💼 <?= htmlspecialchars($r['job_title']) ?></p>
                <div class="chips">
                    <span class="chip c-blue">🤖 MCQ</span>
                    <span class="chip c-gray">📝 <?= $r['q_count'] ?> Questions</span>
                    <span class="chip <?= $is_done ? 'c-green' : 'c-yellow' ?>">
                        <?= $is_done ? '✅ Completed' : '⏳ Pending' ?>
                    </span>
                </div>
            </div>
            <div style="text-align:center">
                <?php if($is_done): ?>
                    <span class="done-badge">✅ Submitted</span>
                    <a class="view-link" href="take_mcq.php?round_id=<?= $r['round_id'] ?>">View Result →</a>
                <?php else: ?>
                    <a class="start-btn" href="take_mcq.php?round_id=<?= $r['round_id'] ?>">▶ Start Test</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; endif; ?>
    </div>

    <!-- Rounds Table -->
    <div class="round-box">
        <h2>📋 Interview Rounds</h2>
        <?php if ($rounds && $rounds->num_rows > 0): ?>
        <table>
            <tr><th>Job</th><th>Round</th><th>Type</th><th>Date</th><th>Status</th><th>Result</th><th>Action</th></tr>
            <?php while ($row = $rounds->fetch_assoc()):
                $t     = !empty(trim($row['round_title'])) ? $row['round_title'] : ($row['round_name'] ?? '—');
                $date  = $row['scheduled_at'] ?? $row['round_date'] ?? '';
                $rt    = strtolower(str_replace([' ','_'],'',$row['round_type'] ?? ''));
                $st    = strtolower($row['status'] ?? 'pending');
                $is_mcq = in_array($rt, ['aimcq','']) || empty($row['round_type']);
            ?>
            <tr>
                <td><?= htmlspecialchars($row['job_title']) ?></td>
                <td><b><?= htmlspecialchars($t) ?></b></td>
                <td>
                    <span class="type-badge <?= $is_mcq?'t-mcq':($rt==='technical'?'t-tech':'t-hr') ?>">
                        <?= $is_mcq ? '🤖 MCQ' : ($rt==='technical' ? '💻 Technical' : '👤 HR') ?>
                    </span>
                </td>
                <td><?= $date ? date('d M Y', strtotime($date)) : '—' ?></td>
                <td><span class="status-badge s-<?= $st ?>"><?= ucfirst($row['status']) ?></span></td>
                <td>
                    <?php if($row['result']==='pass'): ?><span class="r-pass">✅ Pass</span>
                    <?php elseif($row['result']==='fail'): ?><span class="r-fail">❌ Fail</span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if($is_mcq && $st==='ongoing'): ?>
                        <a class="start-btn" href="take_mcq.php?round_id=<?= $row['round_id'] ?>" style="padding:5px 12px;font-size:12px">▶ Start</a>
                    <?php elseif($is_mcq && $st==='completed'): ?>
                        <a href="take_mcq.php?round_id=<?= $row['round_id'] ?>" style="color:#2563eb;font-size:12px;text-decoration:none">View Result</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p style="color:#9ca3af;text-align:center;padding:20px">Koi interview rounds nahi hain abhi.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
