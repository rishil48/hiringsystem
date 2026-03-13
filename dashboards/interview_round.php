<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

// ── Helper: upsert round_results ─────────────────────────────────────────
function save_round_result($conn, $round_id, $result) {
    $row = $conn->query("SELECT a.id as app_id FROM interview_rounds ir JOIN applications a ON ir.application_id=a.id WHERE ir.id=$round_id")->fetch_assoc();
    if (!$row) return;
    $app_id    = (int)$row['app_id'];
    $pct       = $result === 'pass' ? 100.00 : 0.00;
    $ob        = $result === 'pass' ? 10 : 0;
    $tot       = 10;
    $existing  = $conn->query("SELECT id FROM round_results WHERE round_id=$round_id AND application_id=$app_id")->fetch_assoc();
    if ($existing) {
        $conn->query("UPDATE round_results SET result='$result', percentage=$pct, obtained_marks=$ob, total_marks=$tot WHERE round_id=$round_id AND application_id=$app_id");
    } else {
        $conn->query("INSERT INTO round_results (application_id, round_id, total_marks, obtained_marks, percentage, result) VALUES ($app_id, $round_id, $tot, $ob, $pct, '$result')");
    }
}

// Status update
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $conn->query("UPDATE interview_rounds SET status='completed', result='pass' WHERE id=$id");
    save_round_result($conn, $id, 'pass');
    header("Location: interview_round.php"); exit();
}
if (isset($_GET['fail'])) {
    $id = (int)$_GET['fail'];
    $conn->query("UPDATE interview_rounds SET status='completed', result='fail' WHERE id=$id");
    save_round_result($conn, $id, 'fail');
    header("Location: interview_round.php"); exit();
}

$hr_id = (int)$_SESSION['id'];

// Filters
$filter_status = $_GET['status'] ?? 'all';
$filter_type   = $_GET['type']   ?? 'all';
$filter_search = trim($_GET['search'] ?? '');

$where = "WHERE j.hr_id = $hr_id";
if ($filter_status !== 'all') $where .= " AND LOWER(ir.status) = '".strtolower($filter_status)."'";
if ($filter_type   !== 'all') $where .= " AND LOWER(ir.round_type) = '".strtolower($filter_type)."'";
if ($filter_search !== '')    $where .= " AND (u.name LIKE '%$filter_search%' OR j.title LIKE '%$filter_search%' OR ir.round_title LIKE '%$filter_search%')";

$rounds = $conn->query("
    SELECT
        ir.id           AS round_id,
        ir.round_title,
        ir.round_type,
        ir.status,
        ir.result,
        ir.scheduled_at,
        ir.round_date,
        a.id            AS app_id,
        u.name          AS candidate,
        u.email         AS candidate_email,
        j.title         AS job_title,
        (SELECT COUNT(*) FROM interview_questions WHERE round_id = ir.id) AS mcq_q_count,
        (SELECT COUNT(*) FROM technical_rounds    WHERE round_id = ir.id) AS tech_q_count,
        (SELECT COUNT(*) FROM mcq_answers         WHERE round_id = ir.id AND application_id = a.id) AS mcq_answered,
        (SELECT COUNT(*) FROM technical_rounds    WHERE round_id = ir.id
            AND candidate_answer IS NOT NULL AND candidate_answer != '') AS tech_answered,
        (SELECT percentage FROM round_results     WHERE round_id = ir.id AND application_id = a.id LIMIT 1) AS score_pct
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN users u        ON a.user_id = u.id
    JOIN jobs j         ON a.job_id = j.id
    $where
    ORDER BY ir.id DESC
");

// Stats
$stats = $conn->query("
    SELECT
        COUNT(*)                        AS total,
        SUM(LOWER(ir.status)='pending')    AS pending,
        SUM(LOWER(ir.status)='ongoing')    AS ongoing,
        SUM(LOWER(ir.status)='completed')  AS completed,
        SUM(ir.result='pass')              AS passed,
        SUM(ir.result='fail')              AS failed
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN users u ON a.user_id = u.id
    JOIN jobs j  ON a.job_id  = j.id
    WHERE j.hr_id = $hr_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Interview Rounds</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;display:flex;min-height:100vh;background:#f0f4f8}

/* Sidebar */
.sidebar{width:220px;min-height:100vh;background:#0b1d39;padding-top:20px;position:fixed;top:0;left:0}
.sidebar h2{color:white;padding:0 20px 20px;font-size:18px}
.sidebar a{display:block;padding:15px 20px;color:#ccc;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#2563eb;color:white}

/* Main */
.content{flex:1;margin-left:220px;padding:32px}
.page-title{font-size:24px;color:#0b1d39;margin-bottom:24px;font-weight:800}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
.stat-box{background:white;border-radius:12px;padding:16px 12px;text-align:center;box-shadow:0 1px 6px rgba(0,0,0,.07);border-top:4px solid #e5e7eb}
.stat-box.s1{border-top-color:#2563eb}
.stat-box.s2{border-top-color:#f59e0b}
.stat-box.s3{border-top-color:#3b82f6}
.stat-box.s4{border-top-color:#8b5cf6}
.stat-box.s5{border-top-color:#16a34a}
.stat-box.s6{border-top-color:#dc2626}
.stat-box .n{font-size:34px;font-weight:900;margin:6px 0 2px}
.stat-box .l{font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px}

/* Filter bar */
.filter-bar{background:white;border-radius:12px;padding:14px 18px;margin-bottom:18px;
            display:flex;gap:10px;flex-wrap:wrap;align-items:center;
            box-shadow:0 1px 6px rgba(0,0,0,.06)}
.filter-bar input,.filter-bar select{
    padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;color:#111}
.filter-bar input{flex:1;min-width:180px}
.filter-bar input:focus,.filter-bar select:focus{outline:none;border-color:#2563eb}
.fbtn{background:#2563eb;color:white;border:none;padding:8px 18px;
      border-radius:8px;font-size:13px;font-weight:700;cursor:pointer}
.fclear{background:#f3f4f6;color:#374151;text-decoration:none;
        padding:8px 14px;border-radius:8px;font-size:13px}

/* Table */
.tbl-wrap{background:white;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.07);overflow:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
thead th{background:#f8fafc;padding:11px 14px;text-align:left;font-size:11px;
         color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
         border-bottom:2px solid #e5e7eb}
tbody td{padding:13px 14px;font-size:13px;border-bottom:1px solid #f3f4f6;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:#fafafa}

/* Candidate */
.cname{font-weight:700;color:#0b1d39;font-size:14px}
.cemail{font-size:11px;color:#9ca3af;margin-top:2px}

/* Badges */
.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
.b-mcq{background:#ede9fe;color:#6d28d9}
.b-tech{background:#fef3c7;color:#92400e}
.b-video{background:#d1fae5;color:#065f46}
.b-pending{background:#fef9c3;color:#854d0e}
.b-ongoing{background:#dbeafe;color:#1e40af}
.b-completed{background:#dcfce7;color:#166534}
.b-failed{background:#fee2e2;color:#991b1b}

/* Result */
.res{padding:5px 11px;border-radius:8px;font-size:12px;font-weight:700;
     display:inline-flex;align-items:center;gap:4px}
.r-pass{background:#dcfce7;color:#166534;border:1px solid #86efac}
.r-fail{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.r-review{background:#fef9c3;color:#854d0e;border:1px solid #fcd34d}
.r-none{background:#f3f4f6;color:#9ca3af}

/* Score bar */
.sc-wrap{display:flex;align-items:center;gap:6px;margin-top:5px}
.sc-bg{flex:1;background:#e5e7eb;border-radius:20px;height:5px;min-width:50px}
.sc-fill{height:5px;border-radius:20px}
.sc-green{background:#16a34a}
.sc-orange{background:#f59e0b}
.sc-red{background:#dc2626}

/* Progress: answered/total */
.prog{font-size:11px;color:#6b7280;margin-top:3px;display:flex;align-items:center;gap:4px}
.prog-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.pd-green{background:#16a34a}
.pd-yellow{background:#f59e0b}
.pd-gray{background:#d1d5db}

/* Action btns */
.abt{padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;
     text-decoration:none;display:inline-block;margin:2px;white-space:nowrap;border:none;cursor:pointer}
.ab-blue{background:#eff6ff;color:#1e40af}
.ab-yellow{background:#fef3c7;color:#92400e}
.ab-purple{background:#ede9fe;color:#6d28d9}
.ab-green{background:#dcfce7;color:#166534}
.ab-teal{background:#d1fae5;color:#065f46}
.ab-red{background:#fee2e2;color:#991b1b}
.ab-gray{background:#f3f4f6;color:#6b7280}

.no-data{text-align:center;padding:50px;color:#9ca3af}
.no-data .ic{font-size:48px;margin-bottom:12px}
</style>
</head>
<body>

<div class="sidebar">
    <h2>HR Panel</h2>
    <a href="hr.php?page=dashboard">Dashboard</a>
    <a href="hr.php?page=post_job">Post Job</a>
    <a href="hr.php?page=applications">Applications</a>
    <a href="interview_round.php" class="active">Interview Rounds</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="content">
    <div class="page-title">📋 Interview Round Tracking</div>

    <!-- ── Stats ──────────────────────────────────────────────────────── -->
    <div class="stats-grid">
        <div class="stat-box s1">
            <div class="l">Total</div>
            <div class="n" style="color:#2563eb"><?= $stats['total'] ?? 0 ?></div>
        </div>
        <div class="stat-box s2">
            <div class="l">⏳ Pending</div>
            <div class="n" style="color:#f59e0b"><?= $stats['pending'] ?? 0 ?></div>
        </div>
        <div class="stat-box s3">
            <div class="l">🔄 Ongoing</div>
            <div class="n" style="color:#3b82f6"><?= $stats['ongoing'] ?? 0 ?></div>
        </div>
        <div class="stat-box s4">
            <div class="l">✅ Completed</div>
            <div class="n" style="color:#8b5cf6"><?= $stats['completed'] ?? 0 ?></div>
        </div>
        <div class="stat-box s5">
            <div class="l">🏆 Passed</div>
            <div class="n" style="color:#16a34a"><?= $stats['passed'] ?? 0 ?></div>
        </div>
        <div class="stat-box s6">
            <div class="l">❌ Failed</div>
            <div class="n" style="color:#dc2626"><?= $stats['failed'] ?? 0 ?></div>
        </div>
    </div>

    <!-- ── Filters ─────────────────────────────────────────────────────── -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search candidate, job, round..."
               value="<?= htmlspecialchars($filter_search) ?>">

        <select name="status">
            <option value="all"       <?= $filter_status==='all'?'selected':'' ?>>All Status</option>
            <option value="pending"   <?= $filter_status==='pending'?'selected':'' ?>>⏳ Pending</option>
            <option value="ongoing"   <?= $filter_status==='ongoing'?'selected':'' ?>>🔄 Ongoing</option>
            <option value="completed" <?= $filter_status==='completed'?'selected':'' ?>>✅ Completed</option>
        </select>

        <select name="type">
            <option value="all"       <?= $filter_type==='all'?'selected':'' ?>>All Types</option>
            <option value="ai_mcq"    <?= $filter_type==='ai_mcq'?'selected':'' ?>>🤖 AI MCQ</option>
            <option value="technical" <?= $filter_type==='technical'?'selected':'' ?>>💻 Technical</option>
            <option value="hr"        <?= $filter_type==='hr'?'selected':'' ?>>📹 Video/HR</option>
        </select>

        <button type="submit" class="fbtn">Filter</button>
        <a href="interview_round.php" class="fclear">Clear</a>
    </form>

    <!-- ── Table ───────────────────────────────────────────────────────── -->
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Candidate</th>
                    <th>Job</th>
                    <th>Round</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Score / Result</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rounds || $rounds->num_rows === 0): ?>
                <tr><td colspan="9">
                    <div class="no-data">
                        <div class="ic">📭</div>
                        <p>No rounds found.</p>
                    </div>
                </td></tr>
            <?php else:
                $i = 1;
                while ($r = $rounds->fetch_assoc()):
                    $rt  = strtolower(trim($r['round_type'] ?? ''));
                    $st  = strtolower(trim($r['status']     ?? 'pending'));
                    $res = $r['result'] ?? 'pending';
                    $pct = (int)($r['score_pct'] ?? 0);
                    $date = $r['scheduled_at'] ?: $r['round_date'];

                    // Type
                    if (in_array($rt, ['hr','video_call','video','videocall'])) {
                        $tl = '📹 Video/HR'; $tc = 'b-video';
                    } elseif ($rt === 'technical') {
                        $tl = '💻 Technical'; $tc = 'b-tech';
                    } else {
                        $tl = '🤖 AI MCQ';   $tc = 'b-mcq';
                    }

                    // Status badge
                    $sc = match($st) {
                        'ongoing'   => 'b-ongoing',
                        'completed' => 'b-completed',
                        'failed'    => 'b-failed',
                        default     => 'b-pending'
                    };
                    $si = match($st) {
                        'ongoing'   => '🔄',
                        'completed' => '✅',
                        'failed'    => '❌',
                        default     => '⏳'
                    };

                    // Answered
                    if ($rt === 'technical') {
                        $q_total    = (int)$r['tech_q_count'];
                        $q_answered = (int)$r['tech_answered'];
                    } else {
                        $q_total    = (int)$r['mcq_q_count'];
                        $q_answered = (int)$r['mcq_answered'];
                    }
                    $is_video = in_array($rt, ['hr','video_call','video','videocall']);

                    // Score bar color
                    $sb = $pct >= 60 ? 'sc-green' : ($pct >= 40 ? 'sc-orange' : 'sc-red');
            ?>
                <tr>
                    <td style="color:#9ca3af;font-weight:700;font-size:12px"><?= $i++ ?></td>

                    <!-- Candidate -->
                    <td>
                        <div class="cname">👤 <?= htmlspecialchars($r['candidate']) ?></div>
                        <div class="cemail"><?= htmlspecialchars($r['candidate_email']) ?></div>
                    </td>

                    <!-- Job -->
                    <td><b style="color:#374151">💼 <?= htmlspecialchars($r['job_title']) ?></b></td>

                    <!-- Round -->
                    <td>
                        <b style="color:#0b1d39"><?= htmlspecialchars($r['round_title'] ?: 'Round') ?></b>
                        <?php if (!$is_video): ?>
                        <div class="prog">
                            <div class="prog-dot <?= $q_answered>0?'pd-green':($q_total>0?'pd-yellow':'pd-gray') ?>"></div>
                            <?= $q_answered ?>/<?= $q_total ?> <?= $rt==='technical'?'answered':'answered' ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Type -->
                    <td><span class="badge <?= $tc ?>"><?= $tl ?></span></td>

                    <!-- Status -->
                    <td><span class="badge <?= $sc ?>"><?= $si ?> <?= ucfirst($st) ?></span></td>

                    <!-- Score / Result -->
                    <td style="min-width:130px">
                        <?php if ($st === 'completed' || $st === 'failed'): ?>
                            <?php if ($res === 'pass'): ?>
                                <span class="res r-pass">🏆 Passed</span>
                            <?php elseif ($res === 'fail'): ?>
                                <span class="res r-fail">❌ Failed</span>
                            <?php else: ?>
                                <span class="res r-review">⏳ Review</span>
                            <?php endif; ?>
                            <?php if ($pct > 0): ?>
                            <div class="sc-wrap">
                                <div class="sc-bg">
                                    <div class="sc-fill <?= $sb ?>" style="width:<?= min($pct,100) ?>%"></div>
                                </div>
                                <b style="font-size:12px"><?= $pct ?>%</b>
                            </div>
                            <?php endif; ?>
                        <?php elseif ($st === 'ongoing'): ?>
                            <span class="res r-review">🔄 In Progress</span>
                        <?php else: ?>
                            <span class="res r-none">— Not Started</span>
                        <?php endif; ?>
                    </td>

                    <!-- Date -->
                    <td style="font-size:12px;color:#6b7280">
                        <?= $date ? date('d M Y', strtotime($date)) : '—' ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <?php if ($rt === 'technical'): ?>
                            <a class="abt ab-yellow" href="add_technical_round.php?round_id=<?= $r['round_id'] ?>&application_id=<?= $r['app_id'] ?>">⚙️ Setup</a>
                            <?php if ($st === 'completed'): ?>
                            <a class="abt ab-purple" href="evaluate_technical.php?round_id=<?= $r['round_id'] ?>">📊 Evaluate</a>
                            <?php endif; ?>
                        <?php elseif ($is_video): ?>
                            <a class="abt ab-teal" href="add_video_round.php?round_id=<?= $r['round_id'] ?>&application_id=<?= $r['app_id'] ?>">📹 Meeting</a>
                        <?php else: ?>
                            <a class="abt ab-yellow" href="add_mcq_questions.php?round_id=<?= $r['round_id'] ?>&application_id=<?= $r['app_id'] ?>">📝 Questions</a>
                        <?php endif; ?>

                        <?php if ($st === 'ongoing' || $st === 'pending'): ?>
                            <a class="abt ab-green" href="?complete=<?= $r['round_id'] ?>"
                               onclick="return confirm('Mark as Passed?')">✅ Pass</a>
                            <a class="abt ab-red"   href="?fail=<?= $r['round_id'] ?>"
                               onclick="return confirm('Mark as Failed?')">❌ Fail</a>
                        <?php endif; ?>

                        <a class="abt ab-blue" href="add_rounds.php?application_id=<?= $r['app_id'] ?>">👁 View</a>
                    </td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
