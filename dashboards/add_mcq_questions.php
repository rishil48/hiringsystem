<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

$round_id       = (int)($_GET['round_id'] ?? 0);
$application_id = (int)($_GET['application_id'] ?? 0);
if (!$round_id) { header("Location: index.php?page=applications"); exit(); }

// Round + candidate info
$round = $conn->query("
    SELECT ir.*, j.title AS job_title,
           u.name AS candidate_name, u.email AS candidate_email,
           u.id AS user_id, a.id AS app_id
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j  ON a.job_id  = j.id
    JOIN users u ON a.user_id = u.id
    WHERE ir.id = $round_id
")->fetch_assoc();

$success = $error = '';

// ── Add single question (manual or AI import) ───────────────────────────────
if (isset($_POST['add_question'])) {
    $q  = trim($_POST['question']);
    $a  = trim($_POST['option_a']);
    $b  = trim($_POST['option_b']);
    $c  = trim($_POST['option_c']);
    $d  = trim($_POST['option_d']);
    $co = strtolower(trim($_POST['correct_option']));
    $st = $conn->prepare("INSERT INTO interview_questions
          (round_id,question,option_a,option_b,option_c,option_d,correct_option)
          VALUES (?,?,?,?,?,?,?)");
    $st->bind_param("issssss", $round_id, $q, $a, $b, $c, $d, $co);
    $st->execute() ? $success = "added" : $error = "DB error saving question.";
}

// ── AI: generate + save ALL questions at once (server-side) ────────────────
if (isset($_POST['ai_generate_save'])) {
    $topic = trim($_POST['ai_topic_hidden'] ?? '');
    $ai_json = trim($_POST['ai_questions_json'] ?? '');

    if ($topic && $ai_json) {
        $questions = json_decode($ai_json, true);
        $saved = 0;
        if ($questions && is_array($questions)) {
            foreach ($questions as $q) {
                if (!isset($q['question'],$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d'],$q['correct_option'])) continue;
                $co = strtolower($q['correct_option']);
                if (!in_array($co,['a','b','c','d'])) continue;
                $st = $conn->prepare("INSERT INTO interview_questions
                      (round_id,question,option_a,option_b,option_c,option_d,correct_option)
                      VALUES (?,?,?,?,?,?,?)");
                $st->bind_param("issssss",
                    $round_id,
                    $q['question'], $q['option_a'], $q['option_b'],
                    $q['option_c'], $q['option_d'], $co);
                if ($st->execute()) $saved++;
            }
        }
        $success = "ai_saved_$saved";
    }
}

// ── Delete question ─────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM interview_questions WHERE id=".(int)$_GET['delete']." AND round_id=$round_id");
    header("Location: add_mcq_questions.php?round_id=$round_id&application_id=$application_id"); exit();
}

// ── Assign MCQ to candidate ─────────────────────────────────────────────────
if (isset($_POST['assign_mcq'])) {
    $conn->query("UPDATE interview_rounds SET status='ongoing' WHERE id=$round_id");
    $conn->query("DELETE FROM mcq_answers WHERE round_id=$round_id AND application_id=$application_id");
    $success = "assigned";
}

// Counts
$q_count      = (int)$conn->query("SELECT COUNT(*) c FROM interview_questions WHERE round_id=$round_id")->fetch_assoc()['c'];
$round_status = $conn->query("SELECT status FROM interview_rounds WHERE id=$round_id")->fetch_assoc()['status'];
$assigned     = in_array($round_status, ['ongoing','completed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MCQ — Add & Assign</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f4f7fb;color:#111}

.header{background:#0b1d39;color:#fff;padding:14px 28px;display:flex;align-items:center;gap:14px}
.header a{color:#93c5fd;text-decoration:none;font-size:13px}
.header h1{font-size:17px;font-weight:700;color:#fff}

.wrap{max-width:1120px;margin:0 auto;padding:26px 20px}
.two{display:grid;grid-template-columns:430px 1fr;gap:22px}

/* info bar */
.info-bar{background:#fff;border-left:5px solid #7c3aed;border-radius:10px;padding:14px 18px;
  margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;
  box-shadow:0 1px 4px rgba(0,0,0,.07)}
.info-bar h3{font-size:15px;color:#0b1d39;margin-bottom:3px}
.info-bar p{font-size:12px;color:#6b7280}
.pill{background:#ede9fe;border:1px solid #7c3aed;color:#6d28d9;
  padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700}

/* alerts */
.alert{padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600}
.a-ok  {background:#dcfce7;border:1px solid #86efac;color:#166534}
.a-err {background:#fee2e2;border:1px solid #fecaca;color:#991b1b}
.a-warn{background:#fef9c3;border:1px solid #fcd34d;color:#854d0e}

/* card */
.card{background:#fff;border-radius:12px;padding:20px;margin-bottom:18px;
  box-shadow:0 1px 4px rgba(0,0,0,.07)}
.card-title{font-size:14px;font-weight:700;color:#0b1d39;margin-bottom:16px;
  display:flex;align-items:center;gap:8px}

/* form */
.fg{margin-bottom:12px}
.fg label{display:block;font-size:11px;font-weight:700;color:#374151;
  margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}
.fg input,.fg select,.fg textarea{
  width:100%;padding:9px 11px;border:1px solid #d1d5db;border-radius:7px;
  font-size:13px;color:#111;font-family:Arial}
.fg textarea{resize:vertical;min-height:72px}
.fg input:focus,.fg select:focus,.fg textarea:focus{
  outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12)}
.og{display:grid;grid-template-columns:1fr 1fr;gap:9px}

/* buttons */
.btn{padding:9px 18px;border:none;border-radius:7px;font-size:13px;font-weight:700;
  cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .18s}
.b-purple{background:#7c3aed;color:#fff}.b-purple:hover{background:#6d28d9}
.b-green {background:#16a34a;color:#fff}.b-green:hover {background:#15803d}
.b-blue  {background:#2563eb;color:#fff}.b-blue:hover  {background:#1d4ed8}
.b-full{width:100%;justify-content:center;padding:11px;font-size:14px}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* ── AI BOX ── */
.ai-box{background:linear-gradient(135deg,#faf5ff,#ede9fe);
  border:2px dashed #a78bfa;border-radius:12px;padding:18px;margin-bottom:18px}
.ai-title{font-size:14px;font-weight:700;color:#6d28d9;margin-bottom:4px}
.ai-desc {font-size:12px;color:#7c3aed;margin-bottom:12px}
.ai-row{display:flex;gap:8px;align-items:center}
.ai-row input{flex:1;padding:9px 11px;border:1px solid #c4b5fd;border-radius:7px;
  font-size:13px;background:#fff;color:#111}
.ai-row input:focus{outline:none;border-color:#7c3aed}

/* status while loading */
#ai-status{display:none;margin-top:12px;text-align:center;font-size:13px;
  color:#6d28d9;font-weight:600;padding:10px;background:#fff;
  border-radius:8px;border:1px solid #c4b5fd}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid #ede9fe;
  border-top-color:#7c3aed;border-radius:50%;animation:spin .7s linear infinite;
  vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}

/* AI error */
#ai-err{display:none;margin-top:10px;background:#fee2e2;border:1px solid #fecaca;
  border-radius:8px;padding:12px 14px;font-size:13px;color:#991b1b}
#ai-err a{color:#991b1b;font-weight:700}

/* generated cards */
#ai-results{margin-top:12px}
.gen-header{font-size:13px;font-weight:700;color:#6d28d9;margin-bottom:10px}
.gen-q{background:#fff;border:1px solid #c4b5fd;border-radius:9px;padding:13px;
  margin-bottom:9px;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}
.gen-q h4{font-size:13px;font-weight:700;color:#111;margin-bottom:9px;line-height:1.5}
.gen-opts{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:9px}
.gen-opt{font-size:12px;padding:4px 9px;border-radius:5px;background:#f9fafb;color:#374151}
.gen-opt.correct{background:#dcfce7;color:#166534;font-weight:700;border:1px solid #86efac}

/* save-all button area */
#save-all-wrap{display:none;margin-top:12px}

/* assign box */
.assign-box{background:linear-gradient(135deg,#f0fdf4,#dcfce7);
  border:2px solid #16a34a;border-radius:12px;padding:20px;text-align:center;margin-bottom:16px}
.assign-box .icon{font-size:36px;margin-bottom:8px}
.assign-box h3{font-size:15px;color:#166534;font-weight:700;margin-bottom:5px}
.assign-box p{font-size:13px;color:#16a34a;margin-bottom:14px;line-height:1.6}
.cand-tag{background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.3);
  padding:4px 12px;border-radius:6px;font-size:12px;font-weight:700;
  color:#15803d;display:inline-block;margin-bottom:11px}
.assigned-badge{background:#dcfce7;border:1px solid #86efac;color:#166534;
  padding:8px 16px;border-radius:7px;font-weight:700;font-size:13px;
  display:inline-flex;align-items:center;gap:5px}

/* need-more */
.need-more{background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;
  padding:13px;text-align:center;color:#92400e;font-size:13px;margin-bottom:16px}
.pbar{height:5px;background:#fef3c7;border-radius:10px;margin-top:8px;overflow:hidden}
.pfill{height:100%;background:#f59e0b;border-radius:10px;transition:width .4s}

/* question list */
.q-item{background:#fafafa;border:1px solid #e5e7eb;border-radius:9px;
  padding:13px 15px;margin-bottom:9px;animation:fadeIn .3s ease}
.q-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px}
.q-num{font-size:11px;color:#7c3aed;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.q-text{font-size:13px;font-weight:700;color:#111;margin-bottom:8px;line-height:1.5}
.q-opts{display:grid;grid-template-columns:1fr 1fr;gap:4px}
.q-opt{font-size:11px;padding:4px 8px;border-radius:4px;background:#f3f4f6;color:#374151}
.q-opt.c{background:#dcfce7;color:#166534;font-weight:700;border:1px solid #86efac}
.del-a{font-size:11px;color:#dc2626;background:#fee2e2;text-decoration:none;
  padding:3px 8px;border-radius:4px}
.del-a:hover{background:#fecaca}
.empty{text-align:center;padding:28px;color:#9ca3af;font-size:13px}
</style>
</head>
<body>

<div class="header">
  <a href="add_rounds.php?application_id=<?= $application_id ?>">← Back to Rounds</a>
  <h1>🤖 MCQ Round — Add Questions & Auto-Assign</h1>
</div>

<div class="wrap">

  <!-- info bar -->
  <div class="info-bar">
    <div>
      <h3><?= htmlspecialchars($round['round_title']) ?></h3>
      <p>👤 <b><?= htmlspecialchars($round['candidate_name']) ?></b> &nbsp;·&nbsp; 💼 <?= htmlspecialchars($round['job_title']) ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="pill"><?= $q_count ?> Questions</span>
      <?php if($assigned): ?>
        <span class="pill" style="background:#dcfce7;border-color:#16a34a;color:#166534;">✅ ASSIGNED</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- alerts -->
  <?php if($success==='added'): ?>
    <div class="alert a-ok">✅ Question added!</div>
  <?php elseif($success==='assigned'): ?>
    <div class="alert a-ok">🚀 MCQ assigned to <b><?= htmlspecialchars($round['candidate_name']) ?></b> — they can attempt it now!</div>
  <?php elseif(str_starts_with($success,'ai_saved_')): ?>
    <?php $n = (int)substr($success,9); ?>
    <div class="alert a-ok">✨ <?= $n ?> AI-generated question<?= $n!=1?'s':'' ?> saved successfully!</div>
  <?php endif; ?>
  <?php if($error): ?><div class="alert a-err">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="two">

    <!-- ══ LEFT ══ -->
    <div>

      <!-- AI GENERATE BOX -->
      <div class="ai-box">
        <div class="ai-title">✨ AI Auto-Generate Questions</div>
        <div class="ai-desc">Type a topic → AI generates 5 questions → save all in one click</div>

        <div class="ai-row">
          <input type="text" id="ai_topic" placeholder="e.g. PHP, MySQL, JavaScript, Python OOP...">
          <button class="btn b-purple" id="gen-btn" onclick="generateAI()">🤖 Generate</button>
        </div>

        <!-- Status / spinner -->
        <div id="ai-status"><span class="spinner"></span><span id="ai-status-text">Generating...</span></div>

        <!-- Error display -->
        <div id="ai-err"></div>

        <!-- Generated questions preview -->
        <div id="ai-results"></div>

        <!-- Save-all form (hidden until questions generated) -->
        <div id="save-all-wrap">
          <form method="POST" id="save-all-form">
            <input type="hidden" name="ai_topic_hidden"    id="f-topic">
            <input type="hidden" name="ai_questions_json"  id="f-json">
            <button type="submit" name="ai_generate_save" class="btn b-blue b-full">
              💾 Save All Questions to Round
            </button>
          </form>
        </div>
      </div>

      <!-- MANUAL ADD -->
      <div class="card">
        <div class="card-title">✏️ Add Question Manually</div>
        <form method="POST">
          <div class="fg">
            <label>Question</label>
            <textarea name="question" placeholder="Write question..." required></textarea>
          </div>
          <div class="og">
            <div class="fg"><label>Option A</label><input type="text" name="option_a" placeholder="Option A" required></div>
            <div class="fg"><label>Option B</label><input type="text" name="option_b" placeholder="Option B" required></div>
            <div class="fg"><label>Option C</label><input type="text" name="option_c" placeholder="Option C" required></div>
            <div class="fg"><label>Option D</label><input type="text" name="option_d" placeholder="Option D" required></div>
          </div>
          <div class="fg">
            <label>✅ Correct Answer</label>
            <select name="correct_option" required>
              <option value="">-- Select --</option>
              <option value="a">Option A</option>
              <option value="b">Option B</option>
              <option value="c">Option C</option>
              <option value="d">Option D</option>
            </select>
          </div>
          <button type="submit" name="add_question" class="btn b-purple b-full">➕ Add Question</button>
        </form>
      </div>

    </div>

    <!-- ══ RIGHT ══ -->
    <div>

      <!-- ASSIGN BOX -->
      <?php if($q_count >= 3): ?>
      <div class="assign-box">
        <div class="icon">🚀</div>
        <h3>Auto-Assign MCQ to Candidate</h3>
        <div class="cand-tag">👤 <?= htmlspecialchars($round['candidate_name']) ?> · <?= htmlspecialchars($round['candidate_email']) ?></div>
        <p><b><?= $q_count ?> questions</b> ready.<br>Candidate will see the test in their dashboard immediately.</p>
        <?php if($assigned): ?>
          <span class="assigned-badge">✅ Assigned — Candidate Can Attempt Now</span>
          <br><br><a href="view_round_result.php?round_id=<?= $round_id ?>" style="color:#16a34a;font-size:13px">👁 View Results →</a>
        <?php else: ?>
          <form method="POST" onsubmit="return confirm('Assign MCQ to <?= addslashes($round['candidate_name']) ?>?')">
            <button type="submit" name="assign_mcq" class="btn b-green b-full" style="font-size:15px;padding:13px">
              ⚡ Auto-Assign MCQ to Candidate
            </button>
          </form>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="need-more">
        ⚠️ Add at least <b>3 questions</b> to enable assign
        <div class="pbar"><div class="pfill" style="width:<?= min(100,($q_count/3)*100) ?>%"></div></div>
        <div style="margin-top:5px;font-size:12px"><?= $q_count ?>/3 added</div>
      </div>
      <?php endif; ?>

      <!-- QUESTION LIST -->
      <div class="card">
        <div class="card-title" style="justify-content:space-between">
          📋 Questions Added
          <span class="pill"><?= $q_count ?></span>
        </div>
        <?php if($q_count===0): ?>
          <div class="empty">📭 No questions yet.<br>Use AI above or add manually.</div>
        <?php else:
          $qi=1;
          $qs=$conn->query("SELECT * FROM interview_questions WHERE round_id=$round_id ORDER BY id ASC");
          while($q=$qs->fetch_assoc()):
        ?>
        <div class="q-item">
          <div class="q-meta">
            <span class="q-num">Q<?= $qi++ ?></span>
            <a class="del-a" href="?round_id=<?= $round_id ?>&application_id=<?= $application_id ?>&delete=<?= $q['id'] ?>"
               onclick="return confirm('Delete?')">🗑 Delete</a>
          </div>
          <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
          <div class="q-opts">
            <div class="q-opt <?= $q['correct_option']==='a'?'c':'' ?>">A. <?= htmlspecialchars($q['option_a']) ?></div>
            <div class="q-opt <?= $q['correct_option']==='b'?'c':'' ?>">B. <?= htmlspecialchars($q['option_b']) ?></div>
            <div class="q-opt <?= $q['correct_option']==='c'?'c':'' ?>">C. <?= htmlspecialchars($q['option_c']) ?></div>
            <div class="q-opt <?= $q['correct_option']==='d'?'c':'' ?>">D. <?= htmlspecialchars($q['option_d']) ?></div>
          </div>
        </div>
        <?php endwhile; endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
let generatedQuestions = [];

async function generateAI() {
    const topic = document.getElementById('ai_topic').value.trim();
    if (!topic) { alert('Please enter a topic!'); return; }

    // reset UI
    const errEl    = document.getElementById('ai-err');
    const statusEl = document.getElementById('ai-status');
    const resultsEl= document.getElementById('ai-results');
    const saveWrap = document.getElementById('save-all-wrap');
    const genBtn   = document.getElementById('gen-btn');

    errEl.style.display    = 'none';
    errEl.innerHTML        = '';
    resultsEl.innerHTML    = '';
    saveWrap.style.display = 'none';
    generatedQuestions     = [];

    statusEl.style.display = 'block';
    document.getElementById('ai-status-text').textContent = 'Generating questions for "' + topic + '"...';
    genBtn.disabled = true;

    try {
        const res = await fetch('ai_proxy.php', {
            method:  'POST',
            headers: {'Content-Type': 'application/json'},
            body:    JSON.stringify({ topic: topic, type: 'mcq' })
        });

        // Check for non-JSON responses (e.g. PHP errors)
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const raw = await res.text();
            showErr('Server error (not JSON). PHP may have an error. Check: <br><code style="font-size:11px">' + esc(raw.substring(0,300)) + '</code>');
            return;
        }

        const data = await res.json();

        if (data.error) {
            showErr(data.error);
            return;
        }

        generatedQuestions = data.questions;
        renderGenerated(topic, generatedQuestions);

    } catch(e) {
        showErr('Network error: ' + e.message + '<br>Make sure <b>ai_proxy.php</b> is in the dashboards/ folder.');
    } finally {
        statusEl.style.display = 'none';
        genBtn.disabled = false;
    }
}

function showErr(msg) {
    const el = document.getElementById('ai-err');
    el.innerHTML = '❌ ' + msg;
    el.style.display = 'block';
}

function renderGenerated(topic, questions) {
    const resultsEl = document.getElementById('ai-results');
    const saveWrap  = document.getElementById('save-all-wrap');

    let html = `<div class="gen-header">✨ ${questions.length} Questions Generated for "${esc(topic)}"</div>`;

    questions.forEach((q, i) => {
        const map = {a:q.option_a, b:q.option_b, c:q.option_c, d:q.option_d};
        const opts = ['a','b','c','d'].map(o =>
            `<div class="gen-opt ${o===q.correct_option?'correct':''}">${o.toUpperCase()}. ${esc(map[o])}</div>`
        ).join('');

        html += `
        <div class="gen-q">
            <h4>Q${i+1}. ${esc(q.question)}</h4>
            <div class="gen-opts">${opts}</div>
            <span style="font-size:11px;color:#16a34a;font-weight:700">
                ✅ Correct: Option ${q.correct_option.toUpperCase()}
            </span>
        </div>`;
    });

    resultsEl.innerHTML = html;

    // Fill hidden form inputs
    document.getElementById('f-topic').value = topic;
    document.getElementById('f-json').value  = JSON.stringify(questions);
    saveWrap.style.display = 'block';
}

function esc(t) {
    return String(t)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// Enter key triggers generate
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ai_topic').addEventListener('keydown', e => {
        if (e.key === 'Enter') generateAI();
    });
});
</script>
</body>
</html>
