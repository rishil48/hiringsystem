<?php
// candidate/take_mcq.php  OR  dashboards/take_mcq.php
session_start();
include "../config/db.php";

// ── Auth: sirf 'user' role allowed ──────────────────────────────────────────
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

$user_id  = (int) $_SESSION['id'];
$round_id = (int) ($_GET['round_id'] ?? 0);

if ($round_id === 0) {
    header("Location: my_tests.php");
    exit();
}

// ── Round fetch karo — user ka hi round hai ye confirm karo ─────────────────
$round = $conn->query("
    SELECT ir.id, ir.round_title, ir.round_name, ir.status, ir.result,
           a.id AS app_id, j.title AS job_title
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs         j ON a.job_id = j.id
    WHERE ir.id = $round_id
      AND a.user_id = $user_id
")->fetch_assoc();

if (!$round) {
    // Round milaa hi nahi ya is user ka nahi
    header("Location: my_tests.php");
    exit();
}

$app_id    = (int) $round['app_id'];
$round_title = !empty(trim($round['round_title'])) ? $round['round_title'] : ($round['round_name'] ?? 'MCQ Round');

// ── Questions fetch karo ─────────────────────────────────────────────────────
$questions_result = $conn->query("
    SELECT * FROM interview_questions WHERE round_id = $round_id ORDER BY id ASC
");
$questions = [];
while ($q = $questions_result->fetch_assoc()) {
    $questions[] = $q;
}
$total_q = count($questions);

// ── Already attempt kiya? ────────────────────────────────────────────────────
$already_attempted = $conn->query("
    SELECT COUNT(*) c FROM mcq_answers
    WHERE round_id = $round_id AND application_id = $app_id
")->fetch_assoc()['c'] > 0;

// ── Submit handle karo ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_mcq']) && !$already_attempted) {
    $correct_count = 0;

    foreach ($questions as $q) {
        $qid      = (int) $q['id'];
        $selected = $_POST['answer'][$qid] ?? null;
        $selected = in_array($selected, ['a','b','c','d']) ? $selected : null;
        $is_correct = ($selected !== null && $selected === $q['correct_option']) ? 1 : 0;
        if ($is_correct) $correct_count++;

        $sel_escaped = $selected ? "'$selected'" : "NULL";
        $conn->query("
            INSERT INTO mcq_answers (round_id, application_id, question_id, selected_option, is_correct)
            VALUES ($round_id, $app_id, $qid, $sel_escaped, $is_correct)
        ");
    }

    // Score & result
    $percentage = $total_q > 0 ? round(($correct_count / $total_q) * 100) : 0;
    $result     = $percentage >= 60 ? 'pass' : 'fail';

    // Round update karo
    $conn->query("UPDATE interview_rounds SET status='completed', result='$result' WHERE id=$round_id");

    // round_results mein save karo
    $obtained = $correct_count;
    $total    = $total_q;
    $existing = $conn->query("SELECT id FROM round_results WHERE round_id=$round_id AND application_id=$app_id")->fetch_assoc();
    if (!$existing) {
        $conn->query("
            INSERT INTO round_results (application_id, round_id, total_marks, obtained_marks, percentage, result)
            VALUES ($app_id, $round_id, $total, $obtained, $percentage, '$result')
        ");
    }

    $already_attempted = true;
    $round['result']   = $result;
    $round['status']   = 'completed';

    // ── AUTO-ASSIGN TECHNICAL ROUND if MCQ passed ────────────────────────
    if ($result === 'pass') {
        // Check: kya already technical round exist karta hai is application ke liye?
        $tech_exists = $conn->query("
            SELECT id FROM interview_rounds
            WHERE application_id = $app_id
              AND LOWER(round_type) = 'technical'
            LIMIT 1
        ")->fetch_assoc();

        if (!$tech_exists) {
            // Auto create technical round
            $tech_title    = 'Technical Round';
            $tech_type     = 'technical';
            $tech_status   = 'pending'; // pending jab tak HR problems add na kare
            $scheduled_at  = date('Y-m-d H:i:s', strtotime('+3 days'));

            $conn->query("
                INSERT INTO interview_rounds
                    (application_id, round_type, round_title, scheduled_at, status)
                VALUES
                    ($app_id, '$tech_type', '$tech_title', '$scheduled_at', '$tech_status')
            ");

            $new_tech_round_id = $conn->insert_id;
            $round['auto_tech_round_id'] = $new_tech_round_id;
        }
    }
}

// ── Result fetch karo ────────────────────────────────────────────────────────
$score_data = null;
if ($already_attempted) {
    $score_data = $conn->query("
        SELECT
            COUNT(*) AS total,
            SUM(is_correct) AS correct,
            ROUND(SUM(is_correct)*100/COUNT(*)) AS percentage
        FROM mcq_answers
        WHERE round_id = $round_id AND application_id = $app_id
    ")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($round_title) ?> — MCQ Test</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: linear-gradient(135deg,#1e3a8a,#3b5bb5); min-height: 100vh; padding: 30px 20px; }

.container { max-width: 820px; margin: 0 auto; }

/* Header */
.test-header {
    background: white; border-radius: 16px;
    padding: 24px 28px; margin-bottom: 24px;
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;
}
.test-header h1 { font-size: 22px; color: #0f1e46; }
.test-header p  { font-size: 13px; color: #6b7280; margin-top: 4px; }

/* Timer */
.timer-box {
    background: #0f1e46; color: white;
    padding: 10px 20px; border-radius: 10px;
    font-size: 22px; font-weight: 800; letter-spacing: 2px;
    min-width: 100px; text-align: center;
}
.timer-box.warning { background: #dc2626; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }

/* Progress */
.progress-wrap { background: white; border-radius: 12px; padding: 16px 24px; margin-bottom: 20px; }
.progress-bar-bg { background: #e5e7eb; border-radius: 20px; height: 10px; margin-top: 8px; }
.progress-bar    { background: linear-gradient(90deg,#1e3a8a,#3b82f6); height: 10px; border-radius: 20px; transition: width .4s; }
.progress-text   { font-size: 13px; color: #6b7280; display: flex; justify-content: space-between; margin-top: 6px; }

/* Question card */
.q-card {
    background: white; border-radius: 14px;
    padding: 24px 28px; margin-bottom: 18px;
    border-left: 5px solid #1e3a8a;
}
.q-num  { font-size: 12px; color: #6b7280; font-weight: 600; margin-bottom: 8px; }
.q-text { font-size: 16px; font-weight: 700; color: #0f1e46; margin-bottom: 18px; line-height: 1.5; }

.options { display: flex; flex-direction: column; gap: 10px; }
.option  {
    display: flex; align-items: center; gap: 12px;
    border: 2px solid #e5e7eb; border-radius: 10px;
    padding: 12px 16px; cursor: pointer; transition: all .2s;
    font-size: 14px; color: #374151;
}
.option:hover { border-color: #1e3a8a; background: #eff6ff; }
.option input[type=radio] { width: 18px; height: 18px; accent-color: #1e3a8a; flex-shrink: 0; }
.option.selected-opt { border-color: #1e3a8a; background: #dbeafe; color: #1e40af; font-weight: 600; }

/* Result styles (shown after attempt) */
.option.correct-opt { border-color: #16a34a; background: #dcfce7; color: #166534; font-weight: 700; }
.option.wrong-opt   { border-color: #dc2626; background: #fee2e2; color: #991b1b; }
.option.show-correct { border-color: #16a34a; background: #dcfce7; color: #166534; }

/* Submit */
.submit-wrap { text-align: center; margin: 30px 0; }
.submit-btn {
    background: linear-gradient(135deg,#1e3a8a,#2563eb);
    color: white; border: none; padding: 16px 50px;
    border-radius: 12px; font-size: 18px; font-weight: 700;
    cursor: pointer; transition: all .2s;
}
.submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(30,58,138,.4); }
.submit-btn:disabled { background: #9ca3af; cursor: not-allowed; transform: none; }

/* Result card */
.result-card {
    background: white; border-radius: 20px;
    padding: 40px; text-align: center; margin-bottom: 30px;
}
.result-card.pass { border-top: 8px solid #16a34a; }
.result-card.fail { border-top: 8px solid #dc2626; }
.result-icon   { font-size: 64px; margin-bottom: 14px; }
.result-title  { font-size: 28px; font-weight: 800; margin-bottom: 8px; }
.result-title.pass { color: #166534; }
.result-title.fail { color: #991b1b; }
.score-big     { font-size: 56px; font-weight: 900; margin: 16px 0 4px; }
.score-big.pass { color: #16a34a; }
.score-big.fail { color: #dc2626; }
.score-sub     { font-size: 15px; color: #6b7280; margin-bottom: 24px; }
.score-chips   { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }
.sc-chip       { padding: 8px 18px; border-radius: 20px; font-size: 14px; font-weight: 700; }
.sc-green { background: #dcfce7; color: #166534; }
.sc-red   { background: #fee2e2; color: #991b1b; }
.sc-blue  { background: #dbeafe; color: #1e40af; }

.back-btn {
    display: inline-block; background: #0f1e46; color: white;
    padding: 12px 28px; border-radius: 10px; text-decoration: none;
    font-size: 15px; font-weight: 700; transition: .2s;
}
.back-btn:hover { background: #1e3a8a; color: white; }

.no-q-box {
    background: white; border-radius: 14px; padding: 40px; text-align: center; color: #9ca3af;
}
.no-q-box .icon { font-size: 48px; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="container">

<?php if ($already_attempted && $score_data): ?>
    <!-- ══ RESULT VIEW ═══════════════════════════════════════════════════════ -->
    <?php
    $pct     = (int)$score_data['percentage'];
    $correct = (int)$score_data['correct'];
    $total   = (int)$score_data['total'];
    $wrong   = $total - $correct;
    $res     = $round['result'] === 'pass' ? 'pass' : 'fail';

    // Fetch submitted answers
    $answers = [];
    $ans_res = $conn->query("SELECT question_id, selected_option, is_correct FROM mcq_answers WHERE round_id=$round_id AND application_id=$app_id");
    while ($a = $ans_res->fetch_assoc()) $answers[$a['question_id']] = $a;
    ?>

    <div class="result-card <?= $res ?>">
        <div class="result-icon"><?= $res==='pass' ? '🏆' : '😔' ?></div>
        <div class="result-title <?= $res ?>"><?= $res==='pass' ? 'Congratulations! You Passed!' : 'Test Failed' ?></div>
        <div style="color:#6b7280;font-size:14px;margin-bottom:10px">
            <?= htmlspecialchars($round_title) ?> — <?= htmlspecialchars($round['job_title']) ?>
        </div>
        <div class="score-big <?= $res ?>"><?= $pct ?>%</div>
        <div class="score-sub"><?= $correct ?> out of <?= $total ?> correct &nbsp;|&nbsp; Pass mark: 60%</div>
        <div class="score-chips">
            <span class="sc-chip sc-green">✅ Correct: <?= $correct ?></span>
            <span class="sc-chip sc-red">❌ Wrong: <?= $wrong ?></span>
            <span class="sc-chip sc-blue">📝 Total: <?= $total ?></span>
        </div>
        <?php if($res === 'pass' && isset($round['auto_tech_round_id'])): ?>
        <div style="background:#dcfce7;border:2px solid #86efac;border-radius:12px;padding:18px 22px;margin:18px 0;text-align:left">
            <div style="font-size:17px;font-weight:800;color:#166534;margin-bottom:6px">🎉 Technical Round Unlocked!</div>
            <div style="font-size:13px;color:#166534;margin-bottom:12px">
                You passed the MCQ round! A <b>Technical Round</b> has been automatically assigned to you.
                HR will add problems soon — check <b>My Tests</b>.
            </div>
            <a href="my_tests.php" style="background:#16a34a;color:white;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;display:inline-block">Go to My Tests →</a>
        </div>
        <?php endif; ?>
        <a class="back-btn" href="my_tests.php">← Back to My Tests</a>
    </div>

    <!-- Show answers review -->
    <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:14px 20px;margin-bottom:20px;color:white">
        <h3 style="font-size:16px;margin-bottom:4px">📋 Answer Review</h3>
        <p style="font-size:13px;opacity:.8">Green = correct answer &nbsp;|&nbsp; Red = your wrong answer</p>
    </div>

    <?php foreach ($questions as $i => $q):
        $qid       = $q['id'];
        $user_ans  = $answers[$qid]['selected_option'] ?? null;
        $correct_a = $q['correct_option'];
        $was_right = isset($answers[$qid]) && $answers[$qid]['is_correct'];
    ?>
    <div class="q-card">
        <div class="q-num">Question <?= $i+1 ?> of <?= $total_q ?> <?= $was_right ? '✅' : '❌' ?></div>
        <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
        <div class="options">
            <?php foreach (['a','b','c','d'] as $opt):
                $opt_text = $q['option_'.$opt];
                $cls = '';
                if ($opt === $correct_a)          $cls = 'correct-opt';
                elseif ($opt === $user_ans)        $cls = 'wrong-opt';
            ?>
            <label class="option <?= $cls ?>">
                <input type="radio" disabled <?= $opt===$user_ans?'checked':'' ?>>
                <span><b><?= strtoupper($opt) ?>.</b> <?= htmlspecialchars($opt_text) ?></span>
                <?php if($opt===$correct_a): ?> <span style="margin-left:auto;font-size:11px;font-weight:700;color:#166534">✓ Correct</span><?php endif; ?>
                <?php if($opt===$user_ans && $opt!==$correct_a): ?> <span style="margin-left:auto;font-size:11px;font-weight:700;color:#991b1b">✗ Your Answer</span><?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php elseif ($total_q === 0): ?>
    <!-- ══ NO QUESTIONS ══════════════════════════════════════════════════════ -->
    <div class="no-q-box">
        <div class="icon">⏳</div>
        <h2 style="color:#374151;margin-bottom:8px">Questions Not Ready Yet</h2>
        <p>HR ne abhi questions add nahi kiye. Thodi der baad try karo.</p>
        <a class="back-btn" href="my_tests.php" style="margin-top:20px;display:inline-block">← Back</a>
    </div>

<?php else: ?>
    <!-- ══ TEST VIEW ══════════════════════════════════════════════════════════ -->
    <form method="POST" id="mcqForm">
        <input type="hidden" name="submit_mcq" value="1">

        <!-- Header -->
        <div class="test-header">
            <div>
                <h1>📝 <?= htmlspecialchars($round_title) ?></h1>
                <p>💼 <?= htmlspecialchars($round['job_title']) ?> &nbsp;|&nbsp; <?= $total_q ?> Questions &nbsp;|&nbsp; Pass: 60%</p>
            </div>
            <div class="timer-box" id="timer">30:00</div>
        </div>

        <!-- Progress -->
        <div class="progress-wrap">
            <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;color:#374151">
                <span>Progress</span>
                <span id="answered-count">0</span>/<span><?= $total_q ?> Answered</span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar" id="progress-bar" style="width:0%"></div>
            </div>
        </div>

        <!-- Questions -->
        <?php foreach ($questions as $i => $q): ?>
        <div class="q-card" id="qcard-<?= $q['id'] ?>">
            <div class="q-num">Question <?= $i+1 ?> of <?= $total_q ?></div>
            <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
            <div class="options">
                <?php foreach (['a','b','c','d'] as $opt): ?>
                <label class="option" id="opt-<?= $q['id'] ?>-<?= $opt ?>">
                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $opt ?>"
                           onchange="markAnswered(<?= $q['id'] ?>, '<?= $opt ?>')">
                    <span><b><?= strtoupper($opt) ?>.</b> <?= htmlspecialchars($q['option_'.$opt]) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="submit-wrap">
            <button type="submit" class="submit-btn" id="submitBtn">
                ✅ Submit Test
            </button>
            <p style="color:rgba(255,255,255,.7);font-size:13px;margin-top:10px">
                Ek baar submit karne ke baad wapas nahi aa sakte.
            </p>
        </div>
    </form>

    <script>
    // ── Timer ──────────────────────────────────────────────────────────────
    let timeLeft = 30 * 60;
    const timerEl = document.getElementById('timer');
    const timerInterval = setInterval(() => {
        timeLeft--;
        const m = String(Math.floor(timeLeft/60)).padStart(2,'0');
        const s = String(timeLeft % 60).padStart(2,'0');
        timerEl.textContent = m + ':' + s;
        if (timeLeft <= 300) timerEl.classList.add('warning');
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            document.getElementById('mcqForm').submit();
        }
    }, 1000);

    // ── Progress tracking ──────────────────────────────────────────────────
    const totalQ = <?= $total_q ?>;
    const answered = new Set();

    function markAnswered(qid, opt) {
        answered.add(qid);
        // highlight selected
        document.querySelectorAll(`[id^="opt-${qid}-"]`).forEach(el => el.classList.remove('selected-opt'));
        document.getElementById(`opt-${qid}-${opt}`).classList.add('selected-opt');
        // update progress
        document.getElementById('answered-count').textContent = answered.size;
        document.getElementById('progress-bar').style.width = (answered.size/totalQ*100) + '%';
    }

    // Submit confirm
    document.getElementById('mcqForm').addEventListener('submit', function(e) {
        if (answered.size < totalQ) {
            if (!confirm(`Aapne ${totalQ - answered.size} questions skip kiye hain. Phir bhi submit karo?`)) {
                e.preventDefault();
            }
        }
    });
    </script>
<?php endif; ?>

</div>
</body>
</html>
