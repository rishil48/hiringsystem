<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php"); exit();
}

$user_id = (int)$_SESSION['id'];

// All applications of this user
$apps_q = $conn->query("
    SELECT a.id AS app_id, a.status AS app_status, j.title AS job_title, j.city
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.user_id = $user_id
    ORDER BY a.id DESC
");

$applications = [];
while ($app = $apps_q->fetch_assoc()) {
    $app_id = $app['app_id'];

    // All rounds for this application
    $rounds_q = $conn->query("
        SELECT
            ir.id           AS round_id,
            ir.round_title,
            ir.round_name,
            ir.round_type,
            ir.status,
            ir.result,
            ir.scheduled_at,
            ir.round_date,
            (SELECT COUNT(*) FROM interview_questions WHERE round_id = ir.id) AS mcq_total,
            (SELECT COUNT(*) FROM technical_rounds    WHERE round_id = ir.id) AS tech_total,
            (SELECT COUNT(*) FROM mcq_answers WHERE round_id = ir.id AND application_id = $app_id) AS mcq_answered,
            (SELECT COUNT(*) FROM technical_rounds WHERE round_id = ir.id
                AND candidate_answer IS NOT NULL AND candidate_answer != '') AS tech_answered,
            (SELECT percentage FROM round_results WHERE round_id = ir.id AND application_id = $app_id LIMIT 1) AS score_pct,
            (SELECT result FROM round_results WHERE round_id = ir.id AND application_id = $app_id LIMIT 1) AS rr_result
        FROM interview_rounds ir
        WHERE ir.application_id = $app_id
        ORDER BY ir.id ASC
    ");

    $rounds = [];
    $prev_passed = true; // first round is always unlocked if assigned

    while ($r = $rounds_q->fetch_assoc()) {
        $rt = strtolower(trim($r['round_type'] ?? ''));
        $st = strtolower(trim($r['status']     ?? 'pending'));

        // Type detection
        $r['is_video']     = in_array($rt, ['hr','video_call','video','videocall']);
        $r['is_technical'] = ($rt === 'technical') || (!$r['is_video'] && $r['tech_total'] > 0);
        $r['is_mcq']       = !$r['is_video'] && !$r['is_technical'];

        // Title
        if (empty(trim($r['round_title'])) && !empty($r['round_name']))
            $r['round_title'] = $r['round_name'];
        if (empty(trim($r['round_title'])))
            $r['round_title'] = 'Interview Round';

        // Use round_results result if available
        if (!empty($r['rr_result']) && $r['rr_result'] !== 'pending')
            $r['result'] = $r['rr_result'];

        // Is submitted/done
        if ($r['is_video']) {
            $r['is_done'] = ($st === 'completed');
        } elseif ($r['is_mcq']) {
            $r['is_done'] = ($r['mcq_answered'] > 0);
        } else {
            $r['is_done'] = ($r['tech_answered'] > 0 || $st === 'completed');
        }

        // Locked: previous round must be passed
        $r['is_locked'] = !$prev_passed;

        // No questions yet
        $r['no_q'] = !$r['is_video'] && (
            ($r['is_mcq']       && $r['mcq_total']  == 0) ||
            ($r['is_technical'] && $r['tech_total'] == 0)
        );

        // Score display
        $r['score_pct'] = (int)($r['score_pct'] ?? 0);

        // Update prev_passed for next round
        $res = $r['result'] ?? 'pending';
        $prev_passed = ($r['is_done'] && $res === 'pass') || $r['is_video'];

        $rounds[] = $r;
    }

    if (count($rounds) > 0 || $app['app_status'] !== 'pending') {
        $app['rounds'] = $rounds;
        $applications[] = $app;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>My Interview Rounds</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;display:flex;min-height:100vh;background:#eef2f7}

/* Sidebar */
.sidebar{width:220px;min-height:100vh;background:#0f1e46;padding-top:28px;position:fixed;top:0;left:0}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px}
.sidebar a{display:block;padding:14px 24px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#1e3a8a;color:white}

/* Main */
.main{margin-left:220px;padding:32px;flex:1}
.page-title{font-size:24px;font-weight:800;color:#0f1e46;margin-bottom:6px}
.page-sub{font-size:14px;color:#64748b;margin-bottom:28px}

/* No data */
.empty-box{text-align:center;padding:60px;background:white;border-radius:16px;color:#94a3b8}
.empty-box .ei{font-size:52px;margin-bottom:14px}

/* Application card */
.app-card{background:white;border-radius:18px;margin-bottom:32px;
          box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden}
.app-header{background:linear-gradient(135deg,#0f1e46,#1e3a8a);color:white;
            padding:20px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.app-header h2{font-size:18px;font-weight:800}
.app-header .meta{font-size:13px;color:rgba(255,255,255,.7);margin-top:4px}
.app-status{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700}
.as-accepted{background:rgba(34,197,94,.2);color:#4ade80}
.as-pending{background:rgba(245,158,11,.2);color:#fbbf24}
.as-rejected{background:rgba(239,68,68,.2);color:#f87171}

/* Timeline wrapper */
.timeline{padding:28px 24px}
.timeline-title{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;
                letter-spacing:1px;margin-bottom:20px}

/* Journey steps */
.steps-row{display:flex;align-items:flex-start;gap:0;overflow-x:auto;padding-bottom:8px}

/* Connector line */
.connector{flex:1;height:3px;margin-top:32px;min-width:30px;max-width:70px;border-radius:2px}
.conn-done{background:linear-gradient(90deg,#16a34a,#22c55e)}
.conn-active{background:linear-gradient(90deg,#1e3a8a,#3b82f6)}
.conn-locked{background:#e2e8f0}

/* Round step */
.step-wrap{display:flex;flex-direction:column;align-items:center;min-width:130px;max-width:150px}

.step-circle{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;
             justify-content:center;font-size:24px;margin-bottom:10px;position:relative;
             flex-shrink:0;transition:.3s}
/* States */
.sc-pass{background:linear-gradient(135deg,#16a34a,#22c55e);box-shadow:0 4px 14px rgba(22,163,74,.4)}
.sc-fail{background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 4px 14px rgba(220,38,38,.3)}
.sc-active{background:linear-gradient(135deg,#1e3a8a,#2563eb);box-shadow:0 4px 14px rgba(30,58,138,.4)}
.sc-pending{background:linear-gradient(135deg,#f59e0b,#fbbf24);box-shadow:0 4px 14px rgba(245,158,11,.3)}
.sc-locked{background:#e2e8f0;box-shadow:none}
.sc-review{background:linear-gradient(135deg,#7c3aed,#8b5cf6);box-shadow:0 4px 14px rgba(124,58,237,.3)}

/* Lock badge on circle */
.lock-ico{position:absolute;bottom:-4px;right:-4px;background:#94a3b8;color:white;
          width:20px;height:20px;border-radius:50%;font-size:10px;display:flex;
          align-items:center;justify-content:center;border:2px solid white}

.step-name{font-size:13px;font-weight:700;color:#0f1e46;text-align:center;margin-bottom:4px}
.step-type{font-size:11px;color:#64748b;text-align:center;margin-bottom:8px}

/* Status chip below step */
.step-chip{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-align:center;white-space:nowrap}
.chip-pass{background:#dcfce7;color:#16a34a}
.chip-fail{background:#fee2e2;color:#dc2626}
.chip-active{background:#dbeafe;color:#1e40af}
.chip-pending{background:#fef9c3;color:#854d0e}
.chip-locked{background:#f1f5f9;color:#94a3b8}
.chip-review{background:#ede9fe;color:#6d28d9}
.chip-submitted{background:#d1fae5;color:#065f46}

/* Score under chip */
.step-score{font-size:11px;color:#64748b;margin-top:4px;text-align:center}
.step-score b{color:#0f1e46}

/* Action button */
.step-btn{margin-top:8px;padding:7px 16px;border-radius:8px;font-size:12px;font-weight:700;
          text-decoration:none;display:inline-block;border:none;cursor:pointer;white-space:nowrap;
          transition:.2s;text-align:center}
.step-btn:hover{transform:translateY(-1px)}
.btn-start{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white}
.btn-tech{background:linear-gradient(135deg,#92400e,#d97706);color:white}
.btn-video{background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white}
.btn-view{background:#eff6ff;color:#1e40af}
.btn-disabled{background:#e2e8f0;color:#94a3b8;cursor:not-allowed;pointer-events:none}
.btn-waiting{background:#fef9c3;color:#854d0e;cursor:not-allowed;pointer-events:none}
.btn-locked{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;pointer-events:none}

/* Progress bar inside card */
.progress-bar-wrap{padding:0 24px 20px}
.pb-label{display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px}
.pb-track{background:#e2e8f0;border-radius:20px;height:8px}
.pb-fill{height:8px;border-radius:20px;background:linear-gradient(90deg,#1e3a8a,#22c55e);transition:.5s}

/* Unlock banner */
.unlock-banner{margin:0 24px 20px;background:linear-gradient(135deg,#1e3a8a,#2563eb);
               color:white;border-radius:12px;padding:14px 18px;display:flex;
               align-items:center;gap:12px;font-size:13px;font-weight:600}
.unlock-banner .ub-icon{font-size:28px}

/* No rounds yet */
.no-rounds{padding:30px 24px;text-align:center;color:#94a3b8}
</style>
</head>
<body>

<div class="sidebar">
    <h2>User Panel</h2>
    <a href="index.php">Dashboard</a>
    <a href="my_applications.php">My Applications</a>
    <a href="available_jobs.php">Available Jobs</a>
    <a href="my_rounds.php" class="active">My Rounds</a>
    <a href="my_tests.php">My Tests</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="main">
    <div class="page-title">🎯 My Interview Journey</div>
    <div class="page-sub">Track all your interview rounds — pass one to unlock the next!</div>

    <?php if (empty($applications)): ?>
        <div class="empty-box">
            <div class="ei">📭</div>
            <p style="font-size:16px;font-weight:700;color:#374151;margin-bottom:6px">No Rounds Assigned Yet</p>
            <p>HR will assign interview rounds after reviewing your application.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($applications as $app): ?>
    <?php
        $rounds      = $app['rounds'];
        $total_r     = count($rounds);
        $done_r      = count(array_filter($rounds, fn($r) => $r['is_done']));
        $passed_r    = count(array_filter($rounds, fn($r) => ($r['result'] ?? '') === 'pass'));
        $progress_pct= $total_r > 0 ? round(($done_r / $total_r) * 100) : 0;

        // Find any newly unlocked round
        $newly_unlocked = null;
        for ($i = 1; $i < count($rounds); $i++) {
            if (!$rounds[$i]['is_locked'] && !$rounds[$i]['is_done'] &&
                isset($rounds[$i-1]) && $rounds[$i-1]['is_done'] && ($rounds[$i-1]['result'] ?? '') === 'pass') {
                $newly_unlocked = $rounds[$i];
                break;
            }
        }
    ?>
    <div class="app-card">

        <!-- App Header -->
        <div class="app-header">
            <div>
                <h2>💼 <?= htmlspecialchars($app['job_title']) ?></h2>
                <div class="meta">📍 <?= htmlspecialchars($app['city']) ?> &nbsp;|&nbsp; <?= $total_r ?> Round<?= $total_r!=1?'s':'' ?> &nbsp;|&nbsp; <?= $done_r ?> Completed</div>
            </div>
            <?php
                $as = strtolower($app['app_status']);
                $as_class = match($as) { 'accepted' => 'as-accepted', 'rejected' => 'as-rejected', default => 'as-pending' };
                $as_icon  = match($as) { 'accepted' => '✅', 'rejected' => '❌', default => '⏳' };
            ?>
            <span class="app-status <?= $as_class ?>"><?= $as_icon ?> <?= ucfirst($app['app_status']) ?></span>
        </div>

        <?php if ($total_r === 0): ?>
            <div class="no-rounds">📋 No rounds assigned yet. HR will assign soon.</div>
        <?php else: ?>

        <!-- Unlock Banner -->
        <?php if ($newly_unlocked): ?>
        <div class="unlock-banner">
            <div class="ub-icon">🎉</div>
            <div>
                <b>New Round Unlocked!</b> — You passed the previous round.<br>
                <span style="opacity:.85">Next: <?= htmlspecialchars($newly_unlocked['round_title']) ?> is now available!</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress Bar -->
        <div class="progress-bar-wrap">
            <div class="pb-label">
                <span>Progress: <?= $done_r ?>/<?= $total_r ?> rounds completed</span>
                <span><?= $progress_pct ?>%</span>
            </div>
            <div class="pb-track">
                <div class="pb-fill" style="width:<?= $progress_pct ?>%"></div>
            </div>
        </div>

        <!-- Timeline Steps -->
        <div class="timeline">
            <div class="timeline-title">📋 Round-by-Round Journey</div>
            <div class="steps-row">
            <?php foreach ($rounds as $idx => $r):
                $res    = $r['result']   ?? 'pending';
                $st     = strtolower($r['status'] ?? 'pending');
                $locked = $r['is_locked'];
                $done   = $r['is_done'];
                $no_q   = $r['no_q'];
                $pct    = $r['score_pct'];

                // Circle class + icon
                if ($locked) {
                    $cc = 'sc-locked'; $ci = '🔒';
                } elseif ($done) {
                    if ($res === 'pass')       { $cc = 'sc-pass';   $ci = '🏆'; }
                    elseif ($res === 'fail')   { $cc = 'sc-fail';   $ci = '❌'; }
                    elseif ($res === 'pending'){ $cc = 'sc-review'; $ci = '⏳'; }
                    else                       { $cc = 'sc-review'; $ci = '⏳'; }
                } elseif ($st === 'ongoing' || (!$locked && !$done)) {
                    $cc = 'sc-active'; $ci = ($r['is_mcq'] ? '📝' : ($r['is_technical'] ? '💻' : '📹'));
                } else {
                    $cc = 'sc-pending'; $ci = '⏳';
                }

                // Chip label + class
                if ($locked) {
                    $chip = '🔒 Locked'; $chip_c = 'chip-locked';
                } elseif ($done) {
                    if ($res === 'pass')       { $chip = '🏆 Passed';       $chip_c = 'chip-pass'; }
                    elseif ($res === 'fail')   { $chip = '❌ Failed';        $chip_c = 'chip-fail'; }
                    else                       { $chip = '⏳ Under Review';  $chip_c = 'chip-review'; }
                } elseif ($no_q) {
                    $chip = '⏳ Preparing'; $chip_c = 'chip-pending';
                } else {
                    $chip = '🔵 Ready';    $chip_c = 'chip-active';
                }

                // Type label
                if ($r['is_video'])     $type_lbl = '📹 Video/HR';
                elseif($r['is_technical']) $type_lbl = '💻 Technical';
                else                    $type_lbl = '🤖 AI MCQ';

                // Button
                if ($locked) {
                    $btn_text = '🔒 Locked'; $btn_class = 'btn-locked'; $btn_href = '#';
                } elseif ($done) {
                    if ($r['is_video'])          { $btn_text = '👁 View'; $btn_href = "take_video.php?round_id={$r['round_id']}"; }
                    elseif ($r['is_technical'])  { $btn_text = '👁 View'; $btn_href = "take_technical.php?round_id={$r['round_id']}"; }
                    else                         { $btn_text = '👁 Result'; $btn_href = "take_mcq.php?round_id={$r['round_id']}"; }
                    $btn_class = 'btn-view';
                } elseif ($no_q) {
                    $btn_text = '⏳ Waiting'; $btn_class = 'btn-waiting'; $btn_href = '#';
                } else {
                    if ($r['is_video'])          { $btn_text = '📹 Join';  $btn_class = 'btn-video'; $btn_href = "take_video.php?round_id={$r['round_id']}"; }
                    elseif ($r['is_technical'])  { $btn_text = '💻 Start'; $btn_class = 'btn-tech';  $btn_href = "take_technical.php?round_id={$r['round_id']}"; }
                    else                         { $btn_text = '▶ Start';  $btn_class = 'btn-start'; $btn_href = "take_mcq.php?round_id={$r['round_id']}"; }
                }
            ?>
                <!-- Connector before (except first) -->
                <?php if ($idx > 0):
                    $prev = $rounds[$idx-1];
                    $prev_done = $prev['is_done'];
                    $prev_pass = ($prev['result'] ?? '') === 'pass';
                    $conn_c = ($prev_done && $prev_pass) ? 'conn-done' : ($prev_done ? 'conn-active' : 'conn-locked');
                ?>
                <div class="connector <?= $conn_c ?>"></div>
                <?php endif; ?>

                <!-- Step -->
                <div class="step-wrap">
                    <div class="step-circle <?= $cc ?>">
                        <?= $ci ?>
                        <?php if($locked): ?><div class="lock-ico">🔒</div><?php endif; ?>
                    </div>
                    <div class="step-name"><?= htmlspecialchars($r['round_title']) ?></div>
                    <div class="step-type"><?= $type_lbl ?></div>
                    <span class="step-chip <?= $chip_c ?>"><?= $chip ?></span>
                    <?php if ($pct > 0): ?>
                    <div class="step-score">Score: <b><?= $pct ?>%</b></div>
                    <?php endif; ?>
                    <a class="step-btn <?= $btn_class ?>" href="<?= $btn_href ?>"><?= $btn_text ?></a>
                </div>

            <?php endforeach; ?>
            </div>
        </div>

        <!-- Detailed list below timeline -->
        <div style="padding:0 24px 24px">
            <?php foreach ($rounds as $idx => $r):
                $res  = $r['result'] ?? 'pending';
                $done = $r['is_done'];
                $locked = $r['is_locked'];
                $pct  = $r['score_pct'];
            ?>
            <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;margin-bottom:10px;
                        border-left:4px solid <?= $locked?'#e2e8f0':($done&&$res==='pass'?'#16a34a':($done&&$res==='fail'?'#dc2626':'#1e3a8a')) ?>;
                        background:<?= $locked?'#f8fafc':'white' ?>;opacity:<?= $locked?'.6':'1' ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                    <div>
                        <b style="color:#0f1e46;font-size:14px">
                            Round <?= $idx+1 ?>: <?= htmlspecialchars($r['round_title']) ?>
                        </b>
                        <?php if(!empty($r['scheduled_at']) || !empty($r['round_date'])): ?>
                        <span style="font-size:12px;color:#64748b;margin-left:8px">
                            📅 <?= date('d M Y', strtotime($r['scheduled_at'] ?: $r['round_date'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <?php if($pct > 0): ?>
                        <div style="background:#e2e8f0;border-radius:20px;height:6px;width:80px;overflow:hidden">
                            <div style="height:6px;border-radius:20px;width:<?= $pct ?>%;
                                        background:<?= $pct>=60?'#16a34a':($pct>=40?'#f59e0b':'#dc2626') ?>"></div>
                        </div>
                        <span style="font-size:12px;font-weight:700;color:#0f1e46"><?= $pct ?>%</span>
                        <?php endif; ?>

                        <?php if($locked): ?>
                            <span style="background:#f1f5f9;color:#94a3b8;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">🔒 Locked — Pass previous round</span>
                        <?php elseif($done && $res==='pass'): ?>
                            <span style="background:#dcfce7;color:#16a34a;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">🏆 Passed</span>
                        <?php elseif($done && $res==='fail'): ?>
                            <span style="background:#fee2e2;color:#dc2626;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">❌ Failed</span>
                        <?php elseif($done): ?>
                            <span style="background:#ede9fe;color:#6d28d9;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">⏳ Submitted — Under Review</span>
                        <?php elseif($r['no_q']): ?>
                            <span style="background:#fef9c3;color:#854d0e;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">⏳ HR Preparing...</span>
                        <?php else: ?>
                            <span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700">🔵 Ready to Attempt</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; // rounds exist ?>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>
