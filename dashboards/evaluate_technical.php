<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

$round_id = (int)($_GET['round_id'] ?? 0);
if (!$round_id) { echo "Invalid round."; exit(); }

// Get round + application info
$round = $conn->query("
    SELECT ir.*, a.id AS app_id, a.user_id,
           u.name AS candidate_name, u.email AS candidate_email,
           j.title AS job_title
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN users u        ON a.user_id = u.id
    JOIN jobs j         ON a.job_id  = j.id
    WHERE ir.id = $round_id
")->fetch_assoc();

if (!$round) { echo "Round not found."; exit(); }
$app_id = $round['app_id'];

// ── Handle Score Submit ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_scores'])) {
    $scores   = $_POST['score']    ?? [];
    $feedbacks= $_POST['feedback'] ?? [];

    $total_marks    = 0;
    $obtained_marks = 0;

    foreach ($scores as $tech_id => $score) {
        $tech_id  = (int)$tech_id;
        $score    = max(0, min(10, (int)$score));
        $feedback = $conn->real_escape_string(trim($feedbacks[$tech_id] ?? ''));
        $conn->query("UPDATE technical_rounds SET score=$score, feedback='$feedback' WHERE id=$tech_id AND round_id=$round_id");
        $total_marks    += 10;
        $obtained_marks += $score;
    }

    $percentage = $total_marks > 0 ? round(($obtained_marks / $total_marks) * 100, 2) : 0;
    $result     = $percentage >= 60 ? 'pass' : 'fail';

    // Save to round_results (upsert)
    $existing = $conn->query("SELECT id FROM round_results WHERE round_id=$round_id AND application_id=$app_id")->fetch_assoc();
    if ($existing) {
        $conn->query("UPDATE round_results SET total_marks=$total_marks, obtained_marks=$obtained_marks,
                      percentage=$percentage, result='$result' WHERE round_id=$round_id AND application_id=$app_id");
    } else {
        $conn->query("INSERT INTO round_results (application_id, round_id, total_marks, obtained_marks, percentage, result)
                      VALUES ($app_id, $round_id, $total_marks, $obtained_marks, $percentage, '$result')");
    }

    // Update interview_rounds status + result
    $conn->query("UPDATE interview_rounds SET status='completed', result='$result' WHERE id=$round_id");

    $success = "✅ Evaluation saved! Candidate scored $obtained_marks/$total_marks ($percentage%) — " . strtoupper($result);
}

// Get all problems with candidate answers
$problems = $conn->query("SELECT * FROM technical_rounds WHERE round_id=$round_id ORDER BY id ASC");

// Get existing result if any
$existing_result = $conn->query("SELECT * FROM round_results WHERE round_id=$round_id AND application_id=$app_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Evaluate Technical Round</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;display:flex;min-height:100vh;background:#f0f4f8}
.sidebar{width:220px;min-height:100vh;background:#0f1e46;position:fixed;top:0;left:0;padding-top:24px}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px}
.sidebar a{display:block;padding:13px 22px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover{background:#1e3a8a;color:white}
.main{margin-left:220px;padding:32px;flex:1}

/* Header */
.page-hdr{background:linear-gradient(135deg,#0f1e46,#1e3a8a);color:white;border-radius:16px;
           padding:24px 28px;margin-bottom:24px;display:flex;justify-content:space-between;
           align-items:center;flex-wrap:wrap;gap:12px}
.page-hdr h1{font-size:20px;font-weight:800}
.page-hdr .meta{font-size:13px;color:rgba(255,255,255,.7);margin-top:4px}
.result-pill{padding:8px 18px;border-radius:20px;font-size:13px;font-weight:800}
.rp-pass{background:rgba(34,197,94,.25);color:#4ade80;border:1px solid rgba(34,197,94,.4)}
.rp-fail{background:rgba(239,68,68,.25);color:#f87171;border:1px solid rgba(239,68,68,.4)}
.rp-none{background:rgba(255,255,255,.15);color:rgba(255,255,255,.8)}

/* Alert */
.alert{padding:14px 18px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #86efac}

/* Score summary bar */
.score-bar-card{background:white;border-radius:14px;padding:20px 24px;margin-bottom:20px;
                display:flex;align-items:center;gap:24px;flex-wrap:wrap;
                box-shadow:0 1px 8px rgba(0,0,0,.07)}
.score-circle{width:80px;height:80px;border-radius:50%;display:flex;flex-direction:column;
              align-items:center;justify-content:center;font-weight:900;flex-shrink:0}
.sc-pass{background:linear-gradient(135deg,#16a34a,#22c55e);color:white}
.sc-fail{background:linear-gradient(135deg,#dc2626,#ef4444);color:white}
.sc-none{background:#e2e8f0;color:#64748b}
.sc-pct{font-size:22px;line-height:1}
.sc-lbl{font-size:10px;margin-top:2px;opacity:.85}
.score-info{flex:1}
.score-info h3{font-size:16px;color:#0f1e46;margin-bottom:8px}
.prog-track{background:#e2e8f0;border-radius:20px;height:10px;margin-bottom:6px}
.prog-fill{height:10px;border-radius:20px;transition:.5s}
.pf-green{background:linear-gradient(90deg,#16a34a,#22c55e)}
.pf-red{background:linear-gradient(90deg,#dc2626,#ef4444)}
.pf-orange{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.score-nums{font-size:13px;color:#64748b}
.score-nums b{color:#0f1e46}

/* Problem card */
.problem-card{background:white;border-radius:14px;margin-bottom:18px;overflow:hidden;
              box-shadow:0 1px 8px rgba(0,0,0,.07);border-left:5px solid #e2e8f0}
.problem-card.has-answer{border-left-color:#1e3a8a}
.problem-card.scored-pass{border-left-color:#16a34a}
.problem-card.scored-fail{border-left-color:#dc2626}

.pc-header{background:#f8fafc;padding:14px 20px;display:flex;justify-content:space-between;
           align-items:center;flex-wrap:wrap;gap:8px;border-bottom:1px solid #e2e8f0}
.pc-num{font-size:13px;font-weight:700;color:#0f1e46}
.pc-topic{font-size:12px;color:#64748b;background:#e2e8f0;padding:3px 10px;border-radius:20px}

.pc-body{padding:18px 20px;display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:800px){.pc-body{grid-template-columns:1fr}}

.answer-box{border-radius:10px;padding:14px 16px}
.ab-question{background:#f1f5f9;border:1px solid #e2e8f0}
.ab-expected{background:#f0fdf4;border:1px solid #bbf7d0}
.ab-candidate{background:#eff6ff;border:1px solid #bfdbfe}
.ab-empty{background:#fef9c3;border:1px solid #fde68a}
.ab-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.ab-label.lq{color:#475569}
.ab-label.le{color:#16a34a}
.ab-label.lc{color:#1d4ed8}
.ab-label.lm{color:#854d0e}
.ab-text{font-size:13px;color:#374151;line-height:1.6;white-space:pre-wrap}

/* Score input row */
.score-row{padding:14px 20px;background:#fafafa;border-top:1px solid #e2e8f0;
           display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.score-label{font-size:13px;font-weight:700;color:#0f1e46}
.score-stars{display:flex;gap:4px}
.score-stars input[type=radio]{display:none}
.score-stars label{width:30px;height:30px;border-radius:6px;background:#e2e8f0;color:#94a3b8;
                    font-size:12px;font-weight:700;display:flex;align-items:center;
                    justify-content:center;cursor:pointer;transition:.2s}
.score-stars input:checked ~ label,
.score-stars label:hover,
.score-stars label:hover ~ label{background:#e2e8f0;color:#94a3b8}
.score-group{display:flex;gap:4px;align-items:center}
.score-group input[type=number]{width:60px;padding:6px 10px;border:2px solid #e2e8f0;
    border-radius:8px;font-size:14px;font-weight:700;text-align:center;color:#0f1e46}
.score-group input:focus{outline:none;border-color:#1e3a8a}
.score-group .max{font-size:13px;color:#64748b}
.score-badge{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700}
.sb-good{background:#dcfce7;color:#16a34a}
.sb-mid{background:#fef9c3;color:#854d0e}
.sb-bad{background:#fee2e2;color:#dc2626}
.sb-none{background:#f3f4f6;color:#94a3b8}
.feedback-input{flex:1;min-width:200px}
.feedback-input input{width:100%;padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px}
.feedback-input input:focus{outline:none;border-color:#1e3a8a}

/* Submit */
.submit-wrap{background:white;border-radius:14px;padding:22px 24px;
             box-shadow:0 1px 8px rgba(0,0,0,.07);display:flex;align-items:center;
             justify-content:space-between;flex-wrap:wrap;gap:12px}
.submit-wrap p{font-size:13px;color:#64748b}
.submit-btn{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white;border:none;
            padding:13px 32px;border-radius:10px;font-size:15px;font-weight:700;
            cursor:pointer;transition:.2s}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(30,58,138,.4)}
.no-answer{font-style:italic;color:#94a3b8;font-size:13px}
</style>
</head>
<body>
<div class="sidebar">
    <h2>HR Panel</h2>
    <a href="hr.php">Dashboard</a>
    <a href="interview_round.php">Interview Rounds</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="main">

    <!-- Header -->
    <div class="page-hdr">
        <div>
            <h1>📊 Evaluate Technical Round</h1>
            <div class="meta">
                👤 <?= htmlspecialchars($round['candidate_name']) ?>
                &nbsp;(<?= htmlspecialchars($round['candidate_email']) ?>)
                &nbsp;|&nbsp; 💼 <?= htmlspecialchars($round['job_title']) ?>
            </div>
        </div>
        <?php if ($existing_result): ?>
            <?php $rr = $existing_result['result']; ?>
            <span class="result-pill <?= $rr==='pass'?'rp-pass':($rr==='fail'?'rp-fail':'rp-none') ?>">
                <?= $rr==='pass'?'🏆 PASSED':($rr==='fail'?'❌ FAILED':'⏳ Pending') ?>
                &nbsp;—&nbsp; <?= $existing_result['percentage'] ?>%
            </span>
        <?php else: ?>
            <span class="result-pill rp-none">⏳ Not Evaluated Yet</span>
        <?php endif; ?>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Score Summary -->
    <?php if ($existing_result):
        $pct = (float)$existing_result['percentage'];
        $ob  = $existing_result['obtained_marks'];
        $tot = $existing_result['total_marks'];
        $res = $existing_result['result'];
        $pf_class = $pct >= 60 ? 'pf-green' : ($pct >= 40 ? 'pf-orange' : 'pf-red');
        $sc_class = $pct >= 60 ? 'sc-pass' : 'sc-fail';
    ?>
    <div class="score-bar-card">
        <div class="score-circle <?= $sc_class ?>">
            <div class="sc-pct"><?= $pct ?>%</div>
            <div class="sc-lbl"><?= $res==='pass'?'PASS':'FAIL' ?></div>
        </div>
        <div class="score-info">
            <h3>Total Score: <?= $ob ?> / <?= $tot ?> marks</h3>
            <div class="prog-track">
                <div class="prog-fill <?= $pf_class ?>" style="width:<?= min($pct,100) ?>%"></div>
            </div>
            <div class="score-nums">
                Passing marks: <b>60%</b> &nbsp;|&nbsp;
                Obtained: <b><?= $ob ?></b> &nbsp;|&nbsp;
                Total: <b><?= $tot ?></b> &nbsp;|&nbsp;
                Result: <b><?= strtoupper($res) ?></b>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Problems Form -->
    <form method="POST">
    <?php
    $p_count = 0;
    $problems->data_seek(0);
    while ($p = $problems->fetch_assoc()):
        $p_count++;
        $has_answer  = !empty(trim($p['candidate_answer'] ?? ''));
        $has_score   = !is_null($p['score']);
        $score_val   = (int)($p['score'] ?? 0);
        $card_class  = '';
        if ($has_score) $card_class = $score_val >= 6 ? 'scored-pass' : 'scored-fail';
        elseif($has_answer) $card_class = 'has-answer';
    ?>
    <div class="problem-card <?= $card_class ?>">

        <!-- Card header -->
        <div class="pc-header">
            <span class="pc-num">Problem <?= $p_count ?></span>
            <span class="pc-topic">📌 <?= htmlspecialchars($p['topic']) ?></span>
            <?php if ($has_score): ?>
            <span class="score-badge <?= $score_val>=7?'sb-good':($score_val>=4?'sb-mid':'sb-bad') ?>">
                Score: <?= $score_val ?>/10
            </span>
            <?php elseif ($has_answer): ?>
            <span class="score-badge sb-none">⏳ Not Scored</span>
            <?php else: ?>
            <span class="score-badge sb-none">❌ No Answer</span>
            <?php endif; ?>
        </div>

        <!-- Question + Expected + Candidate answers -->
        <div class="pc-body">
            <!-- Left: Question + Expected -->
            <div>
                <div class="answer-box ab-question" style="margin-bottom:12px">
                    <div class="ab-label lq">❓ Question</div>
                    <div class="ab-text"><?= htmlspecialchars($p['problem_statement']) ?></div>
                </div>
                <?php if (!empty($p['expected_answer'])): ?>
                <div class="answer-box ab-expected">
                    <div class="ab-label le">✅ Expected Answer (Key)</div>
                    <div class="ab-text"><?= htmlspecialchars($p['expected_answer']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Candidate Answer -->
            <div>
                <?php if ($has_answer): ?>
                <div class="answer-box ab-candidate" style="height:100%;min-height:120px">
                    <div class="ab-label lc">👤 Candidate's Answer</div>
                    <div class="ab-text"><?= htmlspecialchars($p['candidate_answer']) ?></div>
                </div>
                <?php else: ?>
                <div class="answer-box ab-empty" style="height:100%;min-height:120px;display:flex;align-items:center;justify-content:center">
                    <div style="text-align:center">
                        <div style="font-size:32px;margin-bottom:8px">📭</div>
                        <div class="ab-label lm">No Answer Submitted</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Score input row -->
        <div class="score-row">
            <div class="score-label">Score:</div>
            <div class="score-group">
                <input type="number" name="score[<?= $p['id'] ?>]"
                       value="<?= $score_val ?>"
                       min="0" max="10" required
                       oninput="updateBadge(this, <?= $p['id'] ?>)">
                <span class="max">/ 10</span>
                <span class="score-badge" id="badge_<?= $p['id'] ?>">
                    <?php
                    if ($has_score) echo $score_val>=7?'<span class="sb-good">Good</span>':($score_val>=4?'<span class="sb-mid">Average</span>':'<span class="sb-bad">Poor</span>');
                    else echo '<span class="sb-none">Enter Score</span>';
                    ?>
                </span>
            </div>
            <div class="feedback-input">
                <input type="text" name="feedback[<?= $p['id'] ?>]"
                       value="<?= htmlspecialchars($p['feedback'] ?? '') ?>"
                       placeholder="💬 Optional feedback for candidate...">
            </div>
        </div>

    </div>
    <?php endwhile; ?>

    <?php if ($p_count === 0): ?>
        <div style="text-align:center;padding:40px;background:white;border-radius:14px;color:#94a3b8">
            <div style="font-size:48px;margin-bottom:12px">📭</div>
            <p>No problems found for this round.</p>
        </div>
    <?php else: ?>
    <!-- Submit -->
    <div class="submit-wrap">
        <p>Score each problem out of 10. <b>≥60%</b> = Pass, <b>&lt;60%</b> = Fail.<br>
           Candidate will see result immediately after you submit.</p>
        <button type="submit" name="submit_scores" class="submit-btn">
            💾 Save Evaluation &amp; Publish Result
        </button>
    </div>
    <?php endif; ?>
    </form>

</div>

<script>
function updateBadge(input, id) {
    const val = parseInt(input.value) || 0;
    const badge = document.getElementById('badge_' + id);
    if (val >= 7)      badge.innerHTML = '<span class="score-badge sb-good">Good</span>';
    else if (val >= 4) badge.innerHTML = '<span class="score-badge sb-mid">Average</span>';
    else               badge.innerHTML = '<span class="score-badge sb-bad">Poor</span>';
}
</script>
</body>
</html>
