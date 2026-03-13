<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr') {
    header("Location: ../auth/login.php"); exit();
}

$round_id = $_GET['round_id'] ?? null;
$application_id = $_GET['application_id'] ?? null;
if (!$round_id) { header("Location: index.php?page=applications"); exit(); }

$round = $conn->query("SELECT ir.*, j.title as job_title, u.name as candidate_name
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.user_id = u.id
    WHERE ir.id = $round_id")->fetch_assoc();

$success = $error = '';

if (isset($_POST['add_problem'])) {
    $topic    = trim($_POST['topic']);
    $problem  = trim($_POST['problem_statement']);
    $expected = trim($_POST['expected_answer']);
    $stmt = $conn->prepare("INSERT INTO technical_rounds (round_id, topic, problem_statement, expected_answer) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $round_id, $topic, $problem, $expected);
    if ($stmt->execute()) $success = "✅ Problem added!";
    else $error = "Error saving problem.";
}

if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM technical_rounds WHERE id=".(int)$_GET['delete']." AND round_id=$round_id");
    header("Location: add_technical_round.php?round_id=$round_id&application_id=$application_id"); exit();
}

// ── Assign to candidate ───────────────────────────────────────────────────
if (isset($_POST['assign_round'])) {
    $p_check = $conn->query("SELECT COUNT(*) c FROM technical_rounds WHERE round_id=$round_id")->fetch_assoc()['c'];
    if ($p_check > 0) {
        $conn->query("UPDATE interview_rounds SET status='ongoing', round_type='Technical' WHERE id=$round_id");
        $success = "✅ Technical round assigned to candidate!";
    } else {
        $error = "Add at least 1 problem before assigning.";
    }
}

$problems = $conn->query("SELECT * FROM technical_rounds WHERE round_id=$round_id ORDER BY id ASC");
$p_count  = $problems->num_rows;
$status   = strtolower($round['status'] ?? 'pending');
?>
<!DOCTYPE html>
<html>
<head>
<title>Technical Round Setup</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f4f7fb; }
.header { background: #0b1d39; color: #fff; padding: 15px 30px; display: flex; align-items: center; gap: 20px; }
.header a { color: #93c5fd; text-decoration: none; font-size: 14px; }
.container { max-width: 950px; margin: 30px auto; padding: 0 20px; }
.info-bar { background: #fef3c7; border-left: 5px solid #d97706; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
.info-bar h3 { color: #92400e; margin-bottom: 4px; }
.info-bar p { color: #78350f; font-size: 14px; }
.p-count { background: #d97706; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: bold; }
.card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; }
.card h2 { margin-bottom: 18px; color: #0b1d39; font-size: 18px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 5px; color: #374151; }
.form-group input, .form-group textarea { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
.form-group textarea { resize: vertical; min-height: 90px; }
.btn { padding: 10px 22px; border: none; border-radius: 7px; cursor: pointer; font-size: 14px; font-weight: bold; }
.btn-orange { background: #d97706; color: #fff; }
.btn-orange:hover { background: #b45309; }
.btn-green  { background: #16a34a; color: #fff; width: 100%; padding: 13px; font-size: 15px; }
.btn-green:hover { background: #15803d; }
.msg-success { background: #dcfce7; color: #166534; padding: 10px 14px; border-radius: 7px; margin-bottom: 14px; font-size: 14px; }
.msg-error   { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 7px; margin-bottom: 14px; font-size: 14px; }
.p-item { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.p-topic { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 8px; }
.p-statement { font-size: 14px; color: #111; margin-bottom: 8px; font-weight: bold; }
.p-expected { font-size: 13px; color: #555; background: #f0fdf4; padding: 8px; border-radius: 5px; border-left: 3px solid #86efac; }
.p-actions { margin-top: 10px; text-align: right; }
.p-actions a { font-size: 12px; color: #dc2626; text-decoration: none; padding: 4px 10px; background: #fee2e2; border-radius: 4px; }
.two-col { display: flex; gap: 20px; }
.ai-box { border: 2px dashed #d97706; border-radius: 10px; padding: 20px; margin-bottom: 20px; background: #fffbeb; }
.ai-box h3 { color: #d97706; margin-bottom: 5px; }
.ai-box p  { font-size: 13px; color: #92400e; margin-bottom: 14px; }
#ai-loading2 { display:none; text-align:center; padding:15px; color:#d97706; }
#ai-results2 { display:none; }
.ai-p-card { background:#fff; border:1px solid #fde68a; border-radius:8px; padding:14px; margin-bottom:12px; }
.import-btn { background:#d97706; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:13px; margin-top:8px; }
.assign-box { background:#f0fdf4; border:2px solid #86efac; border-radius:10px; padding:18px; text-align:center; margin-top:16px; }
.assign-box p { font-size:13px; color:#6b7280; margin-bottom:12px; }
.assigned-badge { background:#dcfce7; border:2px solid #86efac; border-radius:10px; padding:14px; text-align:center; color:#166534; font-weight:700; font-size:15px; margin-top:16px; }
</style>
</head>
<body>

<div class="header">
    <a href="add_rounds.php?application_id=<?= $application_id ?>">← Back to Rounds</a>
    <span style="color:#fff;font-size:18px;font-weight:bold;">💻 Technical Round — Add Problems</span>
</div>

<div class="container">
    <div class="info-bar">
        <div>
            <h3>💻 <?= htmlspecialchars($round['round_title']) ?></h3>
            <p>Candidate: <b><?= htmlspecialchars($round['candidate_name']) ?></b> &nbsp;|&nbsp; Job: <?= htmlspecialchars($round['job_title']) ?></p>
        </div>
        <span class="p-count"><?= $p_count ?> Problems</span>
    </div>

    <?php if($success): ?><div class="msg-success"><?= $success ?></div><?php endif; ?>
    <?php if($error):   ?><div class="msg-error"><?= $error ?></div><?php endif; ?>

    <!-- ── AI Generate Box ─────────────────────────────────────────────── -->
    <div class="ai-box">
        <h3>✨ AI Generate Technical Problems</h3>
        <p>Topic likho — AI 3 coding problems generate karega aur directly save karega!</p>
        <div style="display:flex;gap:10px;align-items:center;">
            <input type="text" id="ai_tech_topic"
                   placeholder="e.g. PHP Arrays, MySQL Joins, JavaScript Promises..."
                   style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:6px;"
                   onkeypress="if(event.key==='Enter') generateTechProblems()">
            <button onclick="generateTechProblems()" class="btn btn-orange" style="white-space:nowrap;">
                🤖 Generate Problems
            </button>
        </div>
        <div id="ai-loading2">
            <div style="font-size:30px;margin:10px 0">⏳</div>
            <p>AI is generating problems...</p>
        </div>
        <div id="ai-results2"></div>
    </div>

    <div class="two-col">
        <!-- Manual Form -->
        <div class="card" style="flex:1">
            <h2>✏️ Add Problem Manually</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Topic / Technology</label>
                    <input type="text" name="topic" placeholder="e.g. MySQL, JavaScript, PHP OOP" required>
                </div>
                <div class="form-group">
                    <label>Problem Statement</label>
                    <textarea name="problem_statement" placeholder="Write the technical problem..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Expected Answer / Hints (Optional)</label>
                    <textarea name="expected_answer" placeholder="Key points expected in the answer..."></textarea>
                </div>
                <button type="submit" name="add_problem" class="btn btn-orange">➕ Add Problem</button>
            </form>
        </div>

        <!-- Problems List + Assign -->
        <div style="flex:1.2">
            <div class="card">
                <h2>📋 Problems Added (<?= $p_count ?>)</h2>
                <?php
                $problems = $conn->query("SELECT * FROM technical_rounds WHERE round_id=$round_id ORDER BY id ASC");
                if ($p_count == 0): ?>
                    <div style="text-align:center;padding:30px;color:#9ca3af;">
                        <div style="font-size:40px;">📭</div>
                        <p>No problems added yet.</p>
                    </div>
                <?php else:
                    $pi = 1;
                    while ($p = $problems->fetch_assoc()): ?>
                    <div class="p-item">
                        <div>
                            <span class="p-topic">🏷 <?= htmlspecialchars($p['topic']) ?></span>
                            <small style="color:#9ca3af;">Problem #<?= $pi++ ?></small>
                        </div>
                        <div class="p-statement"><?= nl2br(htmlspecialchars($p['problem_statement'])) ?></div>
                        <?php if($p['expected_answer']): ?>
                        <div class="p-expected"><b>Expected:</b> <?= nl2br(htmlspecialchars($p['expected_answer'])) ?></div>
                        <?php endif; ?>
                        <div class="p-actions">
                            <a href="?round_id=<?= $round_id ?>&application_id=<?= $application_id ?>&delete=<?= $p['id'] ?>"
                               onclick="return confirm('Delete this problem?')">🗑 Delete</a>
                        </div>
                    </div>
                    <?php endwhile;
                endif; ?>
            </div>

            <!-- Assign Box -->
            <?php if ($status === 'ongoing' || $status === 'completed'): ?>
                <div class="assigned-badge">✅ Already Assigned to Candidate</div>
            <?php elseif ($p_count > 0): ?>
                <div class="assign-box">
                    <p><?= $p_count ?> problem(s) ready. Assign this round to candidate now?</p>
                    <form method="POST">
                        <input type="hidden" name="assign_round" value="1">
                        <button type="submit" class="btn btn-green">⚡ Assign to Candidate</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function generateTechProblems() {
    const topic = document.getElementById('ai_tech_topic').value.trim();
    if (!topic) { alert('Please enter a topic!'); return; }

    document.getElementById('ai-loading2').style.display = 'block';
    document.getElementById('ai-results2').style.display = 'none';
    document.getElementById('ai-results2').innerHTML = '';

    try {
        // ✅ Server-side proxy call (not direct Anthropic API)
        const response = await fetch('ai_technical_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ topic: topic })
        });

        const data = await response.json();
        document.getElementById('ai-loading2').style.display = 'none';

        if (data.error) {
            document.getElementById('ai-results2').innerHTML =
                `<p style="color:red;margin-top:10px">❌ ${data.error}</p>`;
            document.getElementById('ai-results2').style.display = 'block';
            return;
        }

        if (data.success && data.problems) {
            let html = `<div style="margin-top:15px;">
                <h4 style="color:#d97706;margin-bottom:12px;">✨ ${data.problems.length} Problems Generated</h4>`;

            data.problems.forEach((p, i) => {
                html += `
                <div class="ai-p-card" id="ai-p-${i}">
                    <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">
                        ${escHtml(p.topic)}
                    </span>
                    <p style="margin:10px 0;font-size:14px;font-weight:bold;">${escHtml(p.problem_statement)}</p>
                    <p style="font-size:13px;color:#555;background:#f0fdf4;padding:8px;border-radius:5px;">
                        💡 ${escHtml(p.expected_answer || 'No hint provided')}
                    </p>
                    <button class="import-btn" onclick='importProblem(${i}, ${JSON.stringify(p)})'>
                        ⬇ Import Problem
                    </button>
                </div>`;
            });
            html += '</div>';
            document.getElementById('ai-results2').innerHTML = html;
            document.getElementById('ai-results2').style.display = 'block';
        }

    } catch(err) {
        document.getElementById('ai-loading2').style.display = 'none';
        document.getElementById('ai-results2').innerHTML =
            `<p style="color:red;margin-top:10px">❌ Connection error. Check XAMPP is running.</p>`;
        document.getElementById('ai-results2').style.display = 'block';
    }
}

function escHtml(t) {
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function importProblem(idx, p) {
    const btn = document.querySelector(`#ai-p-${idx} .import-btn`);
    btn.textContent = 'Importing...';
    btn.disabled = true;

    const form = new FormData();
    form.append('topic', p.topic);
    form.append('problem_statement', p.problem_statement);
    form.append('expected_answer', p.expected_answer || '');
    form.append('add_problem', '1');

    const response = await fetch(window.location.href, { method: 'POST', body: form });
    if (response.ok) {
        btn.textContent = '✅ Imported!';
        btn.style.background = '#16a34a';
        setTimeout(() => location.reload(), 1000);
    } else {
        btn.textContent = '❌ Failed';
        btn.disabled = false;
    }
}
</script>
</body>
</html>
