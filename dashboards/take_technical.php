<?php
// dashboards/take_technical.php  (Candidate Side)
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php"); exit();
}

$user_id  = (int)$_SESSION['id'];
$round_id = (int)($_GET['round_id'] ?? 0);
if (!$round_id) { header("Location: my_tests.php"); exit(); }

// Verify round belongs to this user
$round = $conn->query("
    SELECT ir.*, a.id AS app_id, j.title AS job_title
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE ir.id = $round_id AND a.user_id = $user_id
")->fetch_assoc();

if (!$round) { header("Location: my_tests.php"); exit(); }

$app_id = (int)$round['app_id'];

// Schedule check — test sirf scheduled time ke baad hi accessible
$sched = $round['scheduled_at'] ?? '';
if (!empty($sched) && strtotime($sched) > time()) {
    // Not yet time — show countdown
    $sched_fmt = date('d M Y, h:i A', strtotime($sched));
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Not Yet</title>
    <style>body{font-family:Arial;display:flex;align-items:center;justify-content:center;
    min-height:100vh;background:#f0f4f8;text-align:center}
    .box{background:white;border-radius:18px;padding:50px 40px;max-width:450px;
    box-shadow:0 4px 30px rgba(0,0,0,.1);border-top:6px solid #f59e0b}
    .icon{font-size:60px;margin-bottom:16px}.title{font-size:22px;font-weight:800;color:#0f1e46;margin-bottom:8px}
    .sub{color:#64748b;font-size:14px;line-height:1.7}.date{font-size:16px;font-weight:700;
    color:#f59e0b;margin:16px 0}.btn{display:inline-block;margin-top:16px;padding:11px 24px;
    background:#1e3a8a;color:white;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px}
    </style></head><body>
    <div class='box'><div class='icon'>⏰</div>
    <div class='title'>Test Not Available Yet</div>
    <div class='sub'>HR has scheduled this test for:</div>
    <div class='date'>📅 $sched_fmt</div>
    <div class='sub'>Please come back at the scheduled time to start your test.</div>
    <a href='my_tests.php' class='btn'>← Back to My Tests</a></div></body></html>";
    exit();
}
$title  = !empty($round['round_title']) ? $round['round_title'] : ($round['round_name'] ?? 'Technical Round');

// Problems
$probs_result = $conn->query("SELECT * FROM technical_rounds WHERE round_id=$round_id ORDER BY id ASC");
$problems = [];
while ($p = $probs_result->fetch_assoc()) $problems[] = $p;
$total = count($problems);

// Already submitted?
$submitted = false;
if ($total > 0) {
    $first_answered = $conn->query("SELECT candidate_answer FROM technical_rounds WHERE round_id=$round_id AND candidate_answer IS NOT NULL AND candidate_answer != '' LIMIT 1")->fetch_assoc();
    $submitted = (bool)$first_answered;
}

// Get HR evaluation result — check round_results first, fallback to interview_rounds
$result_row = $conn->query("
    SELECT * FROM round_results
    WHERE round_id=$round_id AND application_id=$app_id
    LIMIT 1
")->fetch_assoc();

// Fallback: if round_results empty but interview_rounds has result, use that
if ((!$result_row || $result_row['result'] === 'pending') && !empty($round['result']) && $round['result'] !== 'pending') {
    $result_row = [
        'result'          => $round['result'],
        'percentage'      => $round['result'] === 'pass' ? 100.00 : 0.00,
        'obtained_marks'  => $round['result'] === 'pass' ? 10 : 0,
        'total_marks'     => 10,
    ];
}

$has_result = $result_row && $result_row['result'] !== 'pending';

// Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_technical']) && !$submitted) {
    foreach ($problems as $p) {
        $pid    = (int)$p['id'];
        $answer = $conn->real_escape_string(trim($_POST['answer'][$pid] ?? ''));
        $conn->query("UPDATE technical_rounds SET candidate_answer='$answer' WHERE id=$pid AND round_id=$round_id");
    }
    $conn->query("UPDATE interview_rounds SET status='completed' WHERE id=$round_id");
    $submitted = true;
    // Reload problems with answers
    $probs_result = $conn->query("SELECT * FROM technical_rounds WHERE round_id=$round_id ORDER BY id ASC");
    $problems = [];
    while ($p = $probs_result->fetch_assoc()) $problems[] = $p;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;background:linear-gradient(135deg,#1e3a8a,#0f172a);min-height:100vh;padding:30px 20px}
.container{max-width:860px;margin:0 auto}
.header{background:white;border-radius:14px;padding:22px 28px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.header h1{font-size:20px;color:#0f1e46}
.header p{font-size:13px;color:#6b7280;margin-top:3px}
.badge{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700}
.badge-blue{background:#dbeafe;color:#1e40af}
.badge-green{background:#dcfce7;color:#166534}
.badge-yellow{background:#fef9c3;color:#854d0e}

/* Result card */
.result-card{border-radius:20px;padding:36px;text-align:center;margin-bottom:24px}
.rc-pass{background:white;border-top:8px solid #16a34a}
.rc-fail{background:white;border-top:8px solid #dc2626}
.rc-icon{font-size:60px;margin-bottom:14px}
.rc-title{font-size:26px;font-weight:900;margin-bottom:8px}
.rc-title.t-pass{color:#16a34a}
.rc-title.t-fail{color:#dc2626}
.rc-sub{color:#6b7280;font-size:14px;margin-bottom:20px;line-height:1.6}
.rc-score-pill{display:inline-block;padding:10px 28px;border-radius:30px;font-size:18px;font-weight:900;margin-bottom:18px}
.rsp-pass{background:#dcfce7;color:#16a34a;border:2px solid #86efac}
.rsp-fail{background:#fee2e2;color:#dc2626;border:2px solid #fecaca}
.score-bar-wrap{max-width:340px;margin:0 auto 16px}
.sb-track{background:#e5e7eb;border-radius:20px;height:12px}
.sb-fill{height:12px;border-radius:20px;transition:.6s}
.sbf-green{background:linear-gradient(90deg,#16a34a,#22c55e)}
.sbf-red{background:linear-gradient(90deg,#dc2626,#ef4444)}
.sb-labels{display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-top:4px}

/* Waiting card */
.waiting-card{background:white;border-radius:20px;padding:40px;text-align:center;margin-bottom:24px;border-top:8px solid #f59e0b}
.waiting-card .icon{font-size:60px;margin-bottom:14px}
.waiting-card h2{font-size:24px;color:#854d0e;margin-bottom:8px}
.waiting-card p{color:#6b7280;font-size:14px;margin-bottom:20px;line-height:1.7}
.status-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;
             border-radius:30px;font-size:13px;font-weight:700;margin-bottom:20px}
.sc-review{background:#fef9c3;color:#854d0e;border:2px solid #fcd34d}
.sc-pass{background:#dcfce7;color:#166534;border:2px solid #86efac}
.sc-fail{background:#fee2e2;color:#991b1b;border:2px solid #fecaca}
.pulse{animation:pulse 2s infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}

/* Problem card */
.prob-card{background:white;border-radius:14px;padding:26px;margin-bottom:20px;border-left:5px solid #1e3a8a}
.prob-card.answered{border-left-color:#16a34a}
.prob-card.evaluated-pass{border-left-color:#16a34a}
.prob-card.evaluated-fail{border-left-color:#dc2626}
.prob-num{font-size:12px;color:#6b7280;font-weight:600;margin-bottom:8px}
.topic-tag{background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block;margin-bottom:10px}
.prob-text{font-size:15px;font-weight:700;color:#0f1e46;line-height:1.6;margin-bottom:18px}
label{font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px}
textarea{width:100%;padding:12px;border:2px solid #e5e7eb;border-radius:10px;font-size:13px;font-family:'Courier New',monospace;resize:vertical;min-height:120px;transition:border-color .2s}
textarea:focus{outline:none;border-color:#1e3a8a;box-shadow:0 0 0 3px rgba(30,58,138,.1)}
textarea:disabled{background:#f9fafb;color:#374151;border-color:#e5e7eb}
.char-count{font-size:11px;color:#9ca3af;text-align:right;margin-top:4px}

/* Answer boxes */
.answer-box{background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px;font-size:13px;color:#166534;font-family:'Courier New',monospace;white-space:pre-wrap;line-height:1.5}
.answer-box.no-ans{background:#fef9c3;border-color:#fcd34d;color:#854d0e;font-style:italic;font-family:Arial}

/* Score + feedback from HR */
.hr-eval-box{margin-top:14px;border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.heb-pass{background:#f0fdf4;border:1px solid #86efac}
.heb-fail{background:#fff5f5;border:1px solid #fecaca}
.heb-none{background:#f3f4f6;border:1px solid #e5e7eb}
.score-num{font-size:24px;font-weight:900}
.score-num.sn-pass{color:#16a34a}
.score-num.sn-fail{color:#dc2626}
.score-num.sn-none{color:#9ca3af}
.score-denom{font-size:13px;color:#6b7280}
.eval-info{flex:1}
.eval-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:4px;display:inline-block}
.eb-good{background:#dcfce7;color:#166534}
.eb-avg{background:#fef9c3;color:#854d0e}
.eb-poor{background:#fee2e2;color:#991b1b}
.eb-none{background:#f3f4f6;color:#6b7280}
.feedback-text{font-size:12px;color:#374151;margin-top:4px}

/* Submit */
.submit-wrap{text-align:center;padding:20px 0}
.submit-btn{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white;border:none;padding:16px 50px;border-radius:12px;font-size:17px;font-weight:700;cursor:pointer;transition:.2s}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(30,58,138,.4)}
.back-btn{display:inline-block;background:#0f1e46;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:700;margin:4px}
.back-btn:hover{background:#1e3a8a;color:white}

.progress-bar-bg{background:rgba(255,255,255,.2);border-radius:20px;height:8px;margin-bottom:24px}
.progress-bar{background:white;height:8px;border-radius:20px;transition:width .4s}
.progress-text{color:rgba(255,255,255,.8);font-size:13px;margin-bottom:8px;text-align:right}

/* Section title */
.section-title{color:rgba(255,255,255,.9);font-size:15px;font-weight:700;margin-bottom:14px;
               display:flex;align-items:center;gap:8px}
</style>
</head>
<body>
<div class="container">

<?php
// ══════════════════════════════════════════════════════════════════════════
// CASE 1 — HR ne evaluate kar diya → Result dikhao
// ══════════════════════════════════════════════════════════════════════════
if ($submitted && $has_result):
    $pct = (float)$result_row['percentage'];
    $ob  = $result_row['obtained_marks'];
    $tot = $result_row['total_marks'];
    $res = $result_row['result'];
    $is_pass = $res === 'pass';
?>

<!-- Result Card -->
<div class="result-card <?= $is_pass ? 'rc-pass' : 'rc-fail' ?>">
    <div class="rc-icon"><?= $is_pass ? '🏆' : '😔' ?></div>
    <div class="rc-title <?= $is_pass ? 't-pass' : 't-fail' ?>">
        <?= $is_pass ? 'Congratulations! You Passed!' : 'Better Luck Next Time' ?>
    </div>
    <div class="rc-sub">
        <?= $is_pass
            ? 'You have successfully cleared this technical round. Keep going!'
            : 'You did not meet the passing criteria of 60%. Don\'t give up!' ?>
    </div>
    <div class="rc-score-pill <?= $is_pass ? 'rsp-pass' : 'rsp-fail' ?>">
        <?= $ob ?> / <?= $tot ?> marks &nbsp;(<?= $pct ?>%)
    </div>
    <div class="score-bar-wrap">
        <div class="sb-track">
            <div class="sb-fill <?= $is_pass ? 'sbf-green' : 'sbf-red' ?>"
                 style="width:<?= min($pct,100) ?>%"></div>
        </div>
        <div class="sb-labels">
            <span>0%</span>
            <span style="color:<?= $is_pass?'#16a34a':'#dc2626' ?>;font-weight:700"><?= $pct ?>%</span>
            <span>100%</span>
        </div>
    </div>
    <p style="font-size:12px;color:#9ca3af;margin-bottom:20px">Passing score: 60%</p>
    <a class="back-btn" href="my_tests.php">← Back to My Tests</a>
    <a class="back-btn" href="my_rounds.php">🎯 View Journey</a>
</div>

<!-- Answers + HR Scores -->
<div class="section-title">📋 Your Answers &amp; HR Evaluation</div>
<?php foreach ($problems as $i => $p):
    $sc       = $p['score'];
    $has_ans  = !empty(trim($p['candidate_answer'] ?? ''));
    $sc_int   = (int)$sc;
    $has_sc   = !is_null($sc);
    $card_cls = $has_sc ? ($sc_int >= 6 ? 'evaluated-pass' : 'evaluated-fail') : 'answered';
    $eb_cls   = !$has_sc ? 'eb-none' : ($sc_int >= 7 ? 'eb-good' : ($sc_int >= 4 ? 'eb-avg' : 'eb-poor'));
    $eb_lbl   = !$has_sc ? 'Not Scored' : ($sc_int >= 7 ? '⭐ Good' : ($sc_int >= 4 ? '👍 Average' : '👎 Poor'));
    $sn_cls   = !$has_sc ? 'sn-none' : ($sc_int >= 6 ? 'sn-pass' : 'sn-fail');
    $heb_cls  = !$has_sc ? 'heb-none' : ($sc_int >= 6 ? 'heb-pass' : 'heb-fail');
?>
<div class="prob-card <?= $card_cls ?>">
    <div class="prob-num">Problem <?= $i+1 ?> of <?= $total ?></div>
    <span class="topic-tag">📌 <?= htmlspecialchars($p['topic']) ?></span>
    <div class="prob-text"><?= htmlspecialchars($p['problem_statement']) ?></div>

    <label>✍️ Your Answer:</label>
    <div class="answer-box <?= $has_ans ? '' : 'no-ans' ?>">
        <?= $has_ans ? htmlspecialchars($p['candidate_answer']) : 'No answer submitted' ?>
    </div>

    <!-- HR Score & Feedback -->
    <div class="hr-eval-box <?= $heb_cls ?>">
        <div>
            <div class="score-num <?= $sn_cls ?>"><?= $has_sc ? $sc_int : '—' ?></div>
            <div class="score-denom">/ 10</div>
        </div>
        <div class="eval-info">
            <span class="eval-badge <?= $eb_cls ?>"><?= $eb_lbl ?></span>
            <?php if (!empty($p['feedback'])): ?>
            <div class="feedback-text">💬 HR: <?= htmlspecialchars($p['feedback']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════
// CASE 2 — Submitted but HR ne abhi evaluate nahi kiya
// ══════════════════════════════════════════════════════════════════════════
elseif ($submitted && !$has_result):
?>

<!-- Waiting Card -->
<div class="waiting-card">
    <div class="icon pulse">⏳</div>
    <h2>Answers Submitted!</h2>
    <p>
        Your answers have been submitted successfully.<br>
        <b>HR is currently reviewing your answers.</b><br>
        Result will appear here automatically once evaluation is complete.
    </p>
    <div class="status-chip sc-review">
        ⏳ Status: Under Review
    </div>
    <br>
    <a class="back-btn" href="my_tests.php">← Back to My Tests</a>
</div>

<!-- Submitted answers read-only -->
<div class="section-title">📋 Your Submitted Answers</div>
<?php foreach ($problems as $i => $p):
    $has_ans = !empty(trim($p['candidate_answer'] ?? ''));
?>
<div class="prob-card answered">
    <div class="prob-num">Problem <?= $i+1 ?> of <?= $total ?></div>
    <span class="topic-tag">📌 <?= htmlspecialchars($p['topic']) ?></span>
    <div class="prob-text"><?= htmlspecialchars($p['problem_statement']) ?></div>
    <label>✍️ Your Answer:</label>
    <div class="answer-box <?= $has_ans ? '' : 'no-ans' ?>">
        <?= $has_ans ? htmlspecialchars($p['candidate_answer']) : 'No answer submitted' ?>
    </div>
</div>
<?php endforeach; ?>

<?php
// ══════════════════════════════════════════════════════════════════════════
// CASE 3 — No problems assigned yet
// ══════════════════════════════════════════════════════════════════════════
elseif ($total === 0):
?>
<div style="background:white;border-radius:14px;padding:40px;text-align:center">
    <div style="font-size:48px;margin-bottom:12px">⏳</div>
    <h2 style="color:#0f1e46;margin-bottom:8px">Problems Not Ready Yet</h2>
    <p style="color:#6b7280">HR has not added problems yet. Please check back later.</p>
    <a class="back-btn" href="my_tests.php" style="margin-top:20px;display:inline-block">← Back</a>
</div>

<?php
// ══════════════════════════════════════════════════════════════════════════
// CASE 4 — Test form (not yet submitted)
// ══════════════════════════════════════════════════════════════════════════
else:
?>
<div class="header">
    <div>
        <h1>💻 <?= htmlspecialchars($title) ?></h1>
        <p>💼 <?= htmlspecialchars($round['job_title']) ?> &nbsp;|&nbsp; <?= $total ?> Problem(s) &nbsp;|&nbsp; Write your best answers</p>
    </div>
    <span class="badge badge-blue">Technical Round</span>
</div>

<div class="progress-text" id="progress-text">0 / <?= $total ?> answered</div>
<div class="progress-bar-bg"><div class="progress-bar" id="progress-bar" style="width:0%"></div></div>

<form method="POST" id="techForm">
    <input type="hidden" name="submit_technical" value="1">
    <?php foreach ($problems as $i => $p): ?>
    <div class="prob-card" id="card-<?= $p['id'] ?>">
        <div class="prob-num">Problem <?= $i+1 ?> of <?= $total ?></div>
        <span class="topic-tag">📌 <?= htmlspecialchars($p['topic']) ?></span>
        <div class="prob-text"><?= htmlspecialchars($p['problem_statement']) ?></div>
        <label>✍️ Your Answer / Solution:</label>
        <textarea name="answer[<?= $p['id'] ?>]"
                  id="ans-<?= $p['id'] ?>"
                  placeholder="Write your answer or code here..."
                  oninput="updateProgress(<?= $p['id'] ?>)"></textarea>
        <div class="char-count" id="cc-<?= $p['id'] ?>">0 characters</div>
    </div>
    <?php endforeach; ?>

    <div class="submit-wrap">
        <button type="submit" class="submit-btn" onclick="return confirmSubmit()">
            ✅ Submit Technical Round
        </button>
        <p style="color:rgba(255,255,255,.6);font-size:12px;margin-top:10px">
            Once submitted, you cannot edit your answers.
        </p>
    </div>
</form>

<script>
const total = <?= $total ?>;
const answered = new Set();

function updateProgress(id) {
    const ta  = document.getElementById('ans-' + id);
    const len = ta.value.trim().length;
    document.getElementById('cc-' + id).textContent = ta.value.length + ' characters';
    if (len > 0) {
        answered.add(id);
        document.getElementById('card-' + id).classList.add('answered');
    } else {
        answered.delete(id);
        document.getElementById('card-' + id).classList.remove('answered');
    }
    const pct = (answered.size / total) * 100;
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-text').textContent = answered.size + ' / ' + total + ' answered';
}

function confirmSubmit() {
    if (answered.size < total) {
        return confirm(`You have answered ${answered.size} of ${total} problems. Submit anyway?`);
    }
    return confirm('Are you sure you want to submit your technical round?');
}
</script>
<?php endif; ?>

</div>
</body>
</html>
