<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

$application_id = (int)($_GET['application_id'] ?? 0);
if (!$application_id) { header("Location: index.php?page=applications"); exit(); }

$app = $conn->query("
    SELECT a.id, a.status, u.name AS candidate_name, u.email,
           j.title AS job_title
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN jobs j  ON a.job_id  = j.id
    WHERE a.id = $application_id
")->fetch_assoc();
if (!$app) { header("Location: index.php?page=applications"); exit(); }

$success = $error = '';

// ── Create new round ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rt_raw      = trim($_POST['round_type'] ?? '');
    $round_title = $conn->real_escape_string(trim($_POST['round_title'] ?? ''));
    $scheduled_at= $conn->real_escape_string(trim($_POST['scheduled_at'] ?? ''));

    // Map form value → DB enum value (AI MCQ / Technical / HR)
    $type_map = [
        'ai_mcq'    => 'AI MCQ',
        'technical' => 'Technical',
        'video_call'=> 'HR',
    ];
    $round_type = $type_map[$rt_raw] ?? null;

    if (!$round_type)    { $error = 'Please select a round type.'; }
    elseif (!$round_title){ $error = 'Round title is required.'; }
    elseif (!$scheduled_at){ $error = 'Please select a schedule date & time.'; }
    else {
        // Prevent duplicate: check if same type+title already exists for this application
        $esc_title = $conn->real_escape_string($round_title);
        $esc_type  = $conn->real_escape_string($round_type);
        $dup = $conn->query("
            SELECT id FROM interview_rounds
            WHERE application_id=$application_id
              AND round_type='$esc_type'
              AND round_title='$esc_title'
            LIMIT 1
        ")->fetch_assoc();

        if ($dup) {
            $error = '⚠️ A round with this title already exists for this candidate. Use a different title.';
        } else {
            $conn->query("
                INSERT INTO interview_rounds (application_id, round_type, round_title, scheduled_at, status)
                VALUES ($application_id, '$esc_type', '$esc_title', '$scheduled_at', 'pending')
            ");
            $round_id = $conn->insert_id;

            if ($rt_raw === 'ai_mcq') {
                header("Location: add_mcq_questions.php?round_id=$round_id&application_id=$application_id"); exit();
            } elseif ($rt_raw === 'technical') {
                header("Location: add_technical_round.php?round_id=$round_id&application_id=$application_id"); exit();
            } elseif ($rt_raw === 'video_call') {
                header("Location: add_video_round.php?round_id=$round_id&application_id=$application_id"); exit();
            }
        }
    }
}

// ── Existing rounds ────────────────────────────────────────────────────
$rounds = $conn->query("
    SELECT ir.*,
           (SELECT COUNT(*) FROM interview_questions WHERE round_id=ir.id)  AS mcq_count,
           (SELECT COUNT(*) FROM technical_rounds    WHERE round_id=ir.id)  AS tech_count,
           (SELECT meeting_link FROM video_rounds    WHERE round_id=ir.id LIMIT 1) AS meeting_link
    FROM interview_rounds ir
    WHERE ir.application_id = $application_id
    ORDER BY ir.id ASC
");
$round_rows = [];
while ($r = $rounds->fetch_assoc()) $round_rows[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Schedule Round</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f4f7fb}
.header{background:#0b1d39;color:#fff;padding:15px 30px;display:flex;align-items:center;gap:16px}
.header a{color:#93c5fd;text-decoration:none;font-size:14px}
.header span{font-size:18px;font-weight:bold}
.container{max-width:960px;margin:28px auto;padding:0 20px}

/* Candidate card */
.ccard{background:#fff;border-radius:12px;padding:18px 24px;margin-bottom:22px;
       border-left:5px solid #2563eb;display:flex;gap:18px;align-items:center;
       box-shadow:0 1px 6px rgba(0,0,0,.07)}
.avatar{width:52px;height:52px;background:#2563eb;border-radius:50%;
        display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:800;flex-shrink:0}
.ccard h3{font-size:16px;color:#0b1d39;margin-bottom:3px}
.ccard p{color:#64748b;font-size:13px}

/* Form card */
.fcard{background:#fff;border-radius:12px;padding:26px;margin-bottom:22px;box-shadow:0 1px 6px rgba(0,0,0,.07)}
.fcard h2{font-size:18px;color:#0b1d39;margin-bottom:20px;font-weight:800}

/* Round type options */
.rtype-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
@media(max-width:640px){.rtype-grid{grid-template-columns:1fr}}
.ropt input[type=radio]{display:none}
.ropt label{display:flex;flex-direction:column;align-items:center;gap:8px;padding:18px 12px;
            border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;text-align:center;transition:.2s}
.ropt label:hover{border-color:#93c5fd;background:#f8fafc}
.ropt input:checked+label{border-color:#2563eb;background:#eff6ff}
.ropt .ico{font-size:30px}
.ropt .ttl{font-weight:700;color:#0b1d39;font-size:14px}
.ropt .dsc{font-size:11px;color:#64748b}

/* Form fields */
.fg{margin-bottom:16px}
.fg label{display:block;font-weight:700;font-size:13px;color:#374151;margin-bottom:6px}
.fg input,.fg select{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:14px}
.fg input:focus,.fg select:focus{outline:none;border-color:#2563eb}
.fg .hint{font-size:11px;color:#94a3b8;margin-top:4px}
.req{color:#ef4444}

.row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.row2{grid-template-columns:1fr}}

.submit-btn{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;border:none;
            padding:13px 30px;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;
            width:100%;margin-top:4px;transition:.2s}
.submit-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(30,58,138,.3)}

.msg-s{background:#dcfce7;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:600}
.msg-e{background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:600}

/* Rounds table */
.rtbl-wrap{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.07)}
.rtbl-wrap h2{padding:18px 24px;color:#0b1d39;font-size:17px;font-weight:800;border-bottom:1px solid #f1f5f9}
table{width:100%;border-collapse:collapse}
th{background:#f8fafc;padding:11px 14px;text-align:left;font-size:11px;color:#64748b;
   text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e5e7eb}
td{padding:13px 14px;font-size:13px;border-bottom:1px solid #f8fafc;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafafa}

.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block}
.b-pending{background:#fef9c3;color:#854d0e}
.b-ongoing{background:#dbeafe;color:#1e40af}
.b-completed{background:#dcfce7;color:#166534}
.b-pass{background:#dcfce7;color:#16a34a}
.b-fail{background:#fee2e2;color:#dc2626}

.tbadge{padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;display:inline-block}
.t-mcq{background:#f3e8ff;color:#7e22ce}
.t-tech{background:#fef3c7;color:#92400e}
.t-video{background:#d1fae5;color:#065f46}

.alink{display:inline-block;padding:5px 12px;border-radius:7px;font-size:12px;
       font-weight:600;text-decoration:none;margin-right:5px;transition:.15s}
.al-blue{background:#eff6ff;color:#2563eb}
.al-purple{background:#f3e8ff;color:#7e22ce}
.al-green{background:#dcfce7;color:#16a34a}
.al-red{background:#fee2e2;color:#dc2626}
.al-teal{background:#ccfbf1;color:#0f766e}

.schedule-info{font-size:12px;color:#64748b;margin-top:3px;display:flex;align-items:center;gap:4px}
.sched-ok{color:#16a34a;font-weight:700}
.sched-no{color:#f59e0b;font-weight:700}
</style>
</head>
<body>

<div class="header">
    <a href="index.php?page=applications">← Applications</a>
    <span>📋 Schedule Interview Round</span>
</div>

<div class="container">

    <!-- Candidate -->
    <div class="ccard">
        <div class="avatar"><?= strtoupper(substr($app['candidate_name'],0,1)) ?></div>
        <div>
            <h3><?= htmlspecialchars($app['candidate_name']) ?></h3>
            <p><?= htmlspecialchars($app['email']) ?> &nbsp;|&nbsp; <b><?= htmlspecialchars($app['job_title']) ?></b></p>
        </div>
    </div>

    <!-- Form -->
    <div class="fcard">
        <h2>➕ Schedule New Round</h2>

        <?php if($success): ?><div class="msg-s"><?= $success ?></div><?php endif; ?>
        <?php if($error):   ?><div class="msg-e"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <!-- Round type -->
            <div class="fg">
                <label>Select Round Type <span class="req">*</span></label>
                <div class="rtype-grid">
                    <div class="ropt">
                        <input type="radio" name="round_type" id="ai_mcq" value="ai_mcq" required>
                        <label for="ai_mcq"><span class="ico">🤖</span><span class="ttl">AI MCQ Round</span><span class="dsc">Auto-graded multiple choice</span></label>
                    </div>
                    <div class="ropt">
                        <input type="radio" name="round_type" id="technical" value="technical">
                        <label for="technical"><span class="ico">💻</span><span class="ttl">Technical Round</span><span class="dsc">Coding & problem solving</span></label>
                    </div>
                    <div class="ropt">
                        <input type="radio" name="round_type" id="video_call" value="video_call">
                        <label for="video_call"><span class="ico">📹</span><span class="ttl">Video Interview</span><span class="dsc">Live video call interview</span></label>
                    </div>
                </div>
            </div>

            <div class="row2">
                <!-- Title -->
                <div class="fg">
                    <label>Round Title <span class="req">*</span></label>
                    <input type="text" name="round_title" placeholder="e.g. Round 2 - Technical"
                           value="<?= htmlspecialchars($_POST['round_title'] ?? '') ?>" required>
                    <div class="hint">Give a unique name to avoid duplicates</div>
                </div>
                <!-- Schedule -->
                <div class="fg">
                    <label>Schedule Date & Time <span class="req">*</span></label>
                    <input type="datetime-local" name="scheduled_at"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           value="<?= htmlspecialchars($_POST['scheduled_at'] ?? '') ?>" required>
                    <div class="hint">User's test opens after this date/time</div>
                </div>
            </div>

            <button type="submit" class="submit-btn">Schedule &amp; Setup Round →</button>
        </form>
    </div>

    <!-- Existing rounds -->
    <div class="rtbl-wrap">
        <h2>📋 Rounds for This Candidate (<?= count($round_rows) ?>)</h2>
        <?php if (empty($round_rows)): ?>
        <p style="padding:24px;color:#94a3b8;text-align:center">No rounds scheduled yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>#</th><th>Title</th><th>Type</th><th>Schedule</th><th>Status</th><th>Result</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($round_rows as $i => $r):
                $rt = strtolower($r['round_type'] ?? '');
                $is_mcq   = str_contains($rt,'mcq') || $rt==='ai_mcq' || $rt==='ai mcq';
                $is_tech  = str_contains($rt,'tech');
                $is_video = in_array($rt,['hr','video_call','video','videocall','video call']);

                if ($is_mcq)   { $type_lbl='🤖 MCQ';       $tc='t-mcq'; }
                elseif($is_tech){ $type_lbl='💻 Technical'; $tc='t-tech'; }
                else            { $type_lbl='📹 Video';     $tc='t-video'; }

                $res = strtolower($r['result'] ?? 'pending');
            ?>
            <tr>
                <td><b><?= $i+1 ?></b></td>
                <td>
                    <b><?= htmlspecialchars($r['round_title'] ?: $r['round_name'] ?: 'Round '.($i+1)) ?></b>
                    <?php if ($is_tech): ?>
                    <div style="font-size:11px;color:#64748b;margin-top:2px">
                        <?= $r['tech_count'] ?> problem<?= $r['tech_count']!=1?'s':'' ?> added
                    </div>
                    <?php elseif($is_mcq): ?>
                    <div style="font-size:11px;color:#64748b;margin-top:2px">
                        <?= $r['mcq_count'] ?> question<?= $r['mcq_count']!=1?'s':'' ?> added
                    </div>
                    <?php endif; ?>
                </td>
                <td><span class="tbadge <?= $tc ?>"><?= $type_lbl ?></span></td>
                <td>
                    <?php if ($r['scheduled_at']): ?>
                    <span class="sched-ok">📅 <?= date('d M Y', strtotime($r['scheduled_at'])) ?></span>
                    <div class="schedule-info">🕐 <?= date('h:i A', strtotime($r['scheduled_at'])) ?></div>
                    <?php else: ?>
                    <span class="sched-no">⚠️ Not set</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $st = strtolower($r['status'] ?? 'pending'); ?>
                    <span class="badge b-<?= $st ?>"><?= ucfirst($st) ?></span>
                </td>
                <td>
                    <?php if ($res === 'pass'): ?>
                        <span class="badge b-pass">🏆 Passed</span>
                    <?php elseif ($res === 'fail'): ?>
                        <span class="badge b-fail">❌ Failed</span>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_mcq): ?>
                        <a href="add_mcq_questions.php?round_id=<?= $r['id'] ?>&application_id=<?= $application_id ?>" class="alink al-purple">📝 Questions</a>
                    <?php elseif ($is_tech): ?>
                        <a href="add_technical_round.php?round_id=<?= $r['id'] ?>&application_id=<?= $application_id ?>" class="alink al-teal">💻 Problems</a>
                    <?php else: ?>
                        <a href="add_video_round.php?round_id=<?= $r['id'] ?>&application_id=<?= $application_id ?>" class="alink al-green">📹 Meeting</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
