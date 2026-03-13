<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php"); exit();
}

$user_id = (int)$_SESSION['id'];

// Check for offer letter
$offer = $conn->query("
    SELECT ol.*, j.title AS job_title
    FROM offer_letters ol
    JOIN jobs j ON ol.job_id = j.id
    WHERE ol.user_id = $user_id AND ol.status = 'Sent'
    ORDER BY ol.sent_at DESC LIMIT 1
")->fetch_assoc();

// All applications with their rounds
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
            (SELECT COUNT(*) FROM interview_questions  WHERE round_id = ir.id) AS mcq_total,
            (SELECT COUNT(*) FROM technical_rounds     WHERE round_id = ir.id) AS tech_total,
            (SELECT COUNT(*) FROM mcq_answers          WHERE round_id = ir.id AND application_id = $app_id) AS mcq_answered,
            (SELECT COUNT(*) FROM technical_rounds     WHERE round_id = ir.id
                AND candidate_answer IS NOT NULL AND candidate_answer != '') AS tech_answered,
            (SELECT percentage FROM round_results      WHERE round_id = ir.id AND application_id = $app_id LIMIT 1) AS score_pct,
            (SELECT result    FROM round_results       WHERE round_id = ir.id AND application_id = $app_id LIMIT 1) AS rr_result,
            (SELECT meeting_link FROM video_rounds     WHERE round_id = ir.id LIMIT 1) AS meeting_link
        FROM interview_rounds ir
        WHERE ir.application_id = $app_id
        ORDER BY ir.id ASC
    ");

    $rounds = [];
    $prev_passed = true; // First round always unlocked

    while ($r = $rounds_q->fetch_assoc()) {
        $rt = strtolower(trim($r['round_type'] ?? ''));
        $st = strtolower(trim($r['status']     ?? 'pending'));

        // DB enum: 'AI MCQ', 'Technical', 'HR'
        $r['is_video']     = in_array($rt, ['hr','video_call','video','videocall','video call']);
        $r['is_technical'] = ($rt === 'technical') || (!$r['is_video'] && (int)$r['tech_total'] > 0);
        $r['is_mcq']       = !$r['is_video'] && !$r['is_technical'];

        // Title
        if (empty(trim($r['round_title'])) && !empty($r['round_name']))
            $r['round_title'] = $r['round_name'];
        if (empty(trim($r['round_title'])))
            $r['round_title'] = 'Round ' . (count($rounds)+1);

        // Use round_results if available, fallback to interview_rounds.result
        if (!empty($r['rr_result']) && strtolower($r['rr_result']) !== 'pending')
            $r['result'] = strtolower($r['rr_result']);
        elseif (!empty($r['result']) && strtolower($r['result']) !== 'pending')
            $r['result'] = strtolower($r['result']);

        // Done?
        if ($r['is_video'])
            $r['is_done'] = ($st === 'completed' || in_array(strtolower($r['result'] ?? ''), ['pass','fail']));
        elseif ($r['is_mcq'])
            $r['is_done'] = ((int)$r['mcq_answered'] > 0 || $st === 'completed');
        else
            $r['is_done'] = ((int)$r['tech_answered'] > 0 || $st === 'completed' || in_array(strtolower($r['result'] ?? ''), ['pass','fail']));

        // Locked?
        $r['is_locked'] = !$prev_passed;

        // No questions?
        $r['no_q'] = !$r['is_video'] && (
            ($r['is_mcq']       && (int)$r['mcq_total']  == 0) ||
            ($r['is_technical'] && (int)$r['tech_total'] == 0)
        );

        $r['score_pct'] = (int)($r['score_pct'] ?? 0);

        // Schedule lock — test sirf scheduled time ke baad start ho
        $sched = $r['scheduled_at'] ?? '';
        $r['is_scheduled_yet'] = empty($sched) || strtotime($sched) <= time();
        // If not scheduled yet, treat as no_q (show "Preparing")
        if (!$r['is_scheduled_yet'] && !$r['is_done']) {
            $r['no_q'] = true;
        }

        // Update prev_passed
        $res = $r['result'] ?? 'pending';
        if ($r['is_video'])
            $prev_passed = (in_array(strtolower($r['result'] ?? ''), ['pass','fail']) || $st === 'completed' || true);
        else
            $prev_passed = ($r['is_done'] && $res === 'pass');

        $rounds[] = $r;
    }

    if (count($rounds) > 0) {
        $app['rounds'] = $rounds;
        $applications[] = $app;
    }
}

// Global stats
$total_rounds     = array_sum(array_map(fn($a) => count($a['rounds']), $applications));
$pending_rounds   = 0; $completed_rounds = 0; $passed_rounds = 0;
foreach ($applications as $app) {
    foreach ($app['rounds'] as $r) {
        if ($r['is_done']) {
            $completed_rounds++;
            if (($r['result'] ?? '') === 'pass') $passed_rounds++;
        } else {
            $pending_rounds++;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>My Tests</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;display:flex;min-height:100vh;background:#eef2f7}

/* Sidebar */
.sidebar{width:220px;min-height:100vh;background:#0f1e46;padding-top:28px;position:fixed;top:0;left:0}
.sidebar h2{text-align:center;color:white;padding:0 12px 20px;font-size:17px}
.sidebar a{display:block;padding:14px 24px;color:#94a3b8;text-decoration:none;font-size:14px}
.sidebar a:hover,.sidebar a.active{background:#1e3a8a;color:white}
.notif{display:inline-block;width:8px;height:8px;background:#facc15;border-radius:50%;margin-left:6px;animation:blink 1.4s infinite;vertical-align:middle}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* Main */
.main{margin-left:220px;padding:32px;flex:1}
.page-title{font-size:24px;font-weight:800;color:#0f1e46;margin-bottom:4px}
.page-sub{font-size:14px;color:#64748b;margin-bottom:24px}

/* Top stats */
.top-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.ts{background:white;border-radius:14px;padding:18px 16px;text-align:center;
    box-shadow:0 1px 6px rgba(0,0,0,.07);border-top:4px solid #e2e8f0}
.ts.s1{border-top-color:#1e3a8a}.ts.s2{border-top-color:#f59e0b}
.ts.s3{border-top-color:#22c55e}.ts.s4{border-top-color:#8b5cf6}
.ts .n{font-size:32px;font-weight:900;margin:6px 0 4px}
.ts .l{font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px}

/* Application block */
.app-block{background:white;border-radius:18px;margin-bottom:28px;overflow:hidden;
           box-shadow:0 2px 12px rgba(0,0,0,.07)}
.app-head{background:linear-gradient(135deg,#0f1e46,#1e3a8a);color:white;
          padding:18px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.app-head h2{font-size:17px;font-weight:800}
.app-head .meta{font-size:12px;color:rgba(255,255,255,.7);margin-top:3px}
.app-badge{padding:5px 14px;border-radius:20px;font-size:11px;font-weight:700}
.ab-accepted{background:rgba(34,197,94,.25);color:#4ade80}
.ab-pending{background:rgba(245,158,11,.25);color:#fbbf24}
.ab-rejected{background:rgba(239,68,68,.25);color:#f87171}

/* Progress bar */
.prog-wrap{padding:16px 24px 0}
.prog-info{display:flex;justify-content:space-between;font-size:12px;color:#64748b;margin-bottom:6px}
.prog-track{background:#e2e8f0;border-radius:20px;height:8px}
.prog-fill{height:8px;border-radius:20px;background:linear-gradient(90deg,#1e3a8a,#22c55e);transition:.4s}

/* Rounds container */
.rounds-wrap{padding:20px 24px 24px}
.rounds-title{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;
              letter-spacing:1px;margin-bottom:16px}

/* Round row */
.round-row{display:flex;align-items:center;gap:14px;padding:14px 16px;
           border-radius:12px;margin-bottom:10px;border:1px solid #e2e8f0;
           background:#fafafa;transition:.2s;flex-wrap:wrap}
.round-row:hover{box-shadow:0 2px 8px rgba(0,0,0,.08);background:white}
.round-row.rr-pass{border-left:4px solid #16a34a;background:#f0fdf4}
.round-row.rr-fail{border-left:4px solid #dc2626;background:#fff5f5}
.round-row.rr-active{border-left:4px solid #1e3a8a;background:#eff6ff}
.round-row.rr-locked{border-left:4px solid #e2e8f0;opacity:.65}
.round-row.rr-review{border-left:4px solid #f59e0b;background:#fefce8}
.round-row.rr-video{border-left:4px solid #7c3aed;background:#faf5ff}

/* Step number */
.step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;
          justify-content:center;font-size:14px;font-weight:900;flex-shrink:0}
.sn-pass{background:#dcfce7;color:#16a34a}
.sn-fail{background:#fee2e2;color:#dc2626}
.sn-active{background:#dbeafe;color:#1e3a8a}
.sn-locked{background:#f1f5f9;color:#94a3b8}
.sn-review{background:#fef9c3;color:#854d0e}
.sn-video{background:#ede9fe;color:#6d28d9}

/* Round info */
.round-info{flex:1;min-width:150px}
.round-name{font-size:14px;font-weight:700;color:#0f1e46}
.round-meta{font-size:12px;color:#64748b;margin-top:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* Type chip */
.type-chip{padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700}
.tc-mcq{background:#ede9fe;color:#6d28d9}
.tc-tech{background:#fef3c7;color:#92400e}
.tc-video{background:#d1fae5;color:#065f46}

/* Status chip */
.status-chip{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.sc-pass{background:#dcfce7;color:#16a34a}
.sc-fail{background:#fee2e2;color:#dc2626}
.sc-active{background:#dbeafe;color:#1e40af}
.sc-locked{background:#f1f5f9;color:#94a3b8}
.sc-review{background:#fef9c3;color:#854d0e}
.sc-waiting{background:#fef3c7;color:#92400e}

/* Score bar inline */
.score-inline{display:flex;align-items:center;gap:6px}
.si-track{width:60px;height:5px;background:#e2e8f0;border-radius:10px}
.si-fill{height:5px;border-radius:10px}
.sf-g{background:#16a34a}.sf-o{background:#f59e0b}.sf-r{background:#dc2626}

/* Action button */
.act-btn{padding:8px 18px;border-radius:8px;font-size:12px;font-weight:700;
         text-decoration:none;border:none;cursor:pointer;white-space:nowrap;
         display:inline-block;transition:.2s;flex-shrink:0}
.act-btn:hover{transform:translateY(-1px)}
.ab-start{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:white}
.ab-tech{background:linear-gradient(135deg,#92400e,#d97706);color:white}
.ab-video{background:linear-gradient(135deg,#5b21b6,#7c3aed);color:white}
.ab-view{background:#eff6ff;color:#1e40af}
.ab-locked{background:#f1f5f9;color:#94a3b8;pointer-events:none;cursor:not-allowed}
.ab-waiting{background:#fef9c3;color:#854d0e;pointer-events:none}

/* Video link badge */
.link-ready{display:inline-flex;align-items:center;gap:5px;font-size:11px;
            background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:20px;font-weight:700}
.link-missing{display:inline-flex;align-items:center;gap:5px;font-size:11px;
              background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:20px;font-weight:700}

.empty-box{text-align:center;padding:60px;background:white;border-radius:16px;color:#94a3b8;
           box-shadow:0 2px 10px rgba(0,0,0,.06)}
.empty-box .ei{font-size:52px;margin-bottom:14px}
</style>
</head>
<body>

<div class="sidebar">
    <h2>User Panel</h2>
    <a href="index.php">Dashboard</a>
    <a href="my_applications.php">My Applications</a>
    <a href="available_jobs.php">Available Jobs</a>
    <a href="my_tests.php" class="active">
        My Tests<?php if($pending_rounds>0): ?><span class="notif"></span><?php endif; ?>
    </a>
    <a href="my_rounds.php">My Journey</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<div class="main">
    <div class="page-title">🎯 My Tests & Rounds</div>
    <div class="page-sub">Track all your interview rounds — pass one to unlock the next!</div>

    <!-- Offer Letter Banner -->
    <?php if ($offer): ?>
    <div style="background:linear-gradient(135deg,#16a34a,#059669);color:white;border-radius:14px;
                padding:20px 26px;margin-bottom:22px;display:flex;justify-content:space-between;
                align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 4px 20px rgba(22,163,74,.3)">
        <div>
            <div style="font-size:18px;font-weight:800">🎉 Congratulations! You Got an Offer!</div>
            <div style="font-size:13px;opacity:.85;margin-top:3px">
                You have been selected for <b><?= htmlspecialchars($offer['job_title']) ?></b>. Your offer letter is ready.
            </div>
        </div>
        <a href="offer_letter.php" style="background:white;color:#16a34a;padding:11px 24px;
           border-radius:10px;font-size:14px;font-weight:800;text-decoration:none;white-space:nowrap;
           transition:.2s;display:inline-block">📄 View Offer Letter</a>
    </div>
    <?php endif; ?>

    <!-- Top Stats -->
    <div class="top-stats">
        <div class="ts s1"><div class="l">Total Rounds</div><div class="n" style="color:#1e3a8a"><?= $total_rounds ?></div></div>
        <div class="ts s2"><div class="l">⏳ Pending</div><div class="n" style="color:#f59e0b"><?= $pending_rounds ?></div></div>
        <div class="ts s3"><div class="l">✅ Completed</div><div class="n" style="color:#22c55e"><?= $completed_rounds ?></div></div>
        <div class="ts s4"><div class="l">🏆 Passed</div><div class="n" style="color:#8b5cf6"><?= $passed_rounds ?></div></div>
    </div>

    <?php if (empty($applications)): ?>
    <div class="empty-box">
        <div class="ei">📭</div>
        <p style="font-size:16px;font-weight:700;color:#374151;margin-bottom:6px">No Tests Assigned Yet</p>
        <p>HR will assign interview rounds after reviewing your application.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($applications as $app):
        $rounds     = $app['rounds'];
        $total_r    = count($rounds);
        $done_r     = count(array_filter($rounds, fn($r) => $r['is_done']));
        $prog_pct   = $total_r > 0 ? round(($done_r / $total_r) * 100) : 0;
        $remaining  = $total_r - $done_r;

        $as = strtolower($app['app_status']);
        $as_class = match($as){ 'accepted'=>'ab-accepted','rejected'=>'ab-rejected',default=>'ab-pending' };
        $as_icon  = match($as){ 'accepted'=>'✅','rejected'=>'❌',default=>'⏳' };
    ?>
    <div class="app-block">

        <!-- App header -->
        <div class="app-head">
            <div>
                <h2>💼 <?= htmlspecialchars($app['job_title']) ?></h2>
                <div class="meta">
                    📍 <?= htmlspecialchars($app['city']) ?>
                    &nbsp;|&nbsp; <?= $total_r ?> Round<?= $total_r!=1?'s':'' ?>
                    &nbsp;|&nbsp; <?= $done_r ?> done
                    &nbsp;|&nbsp; <b style="color:<?= $remaining>0?'#fbbf24':'#4ade80' ?>"><?= $remaining ?> remaining</b>
                </div>
            </div>
            <span class="app-badge <?= $as_class ?>"><?= $as_icon ?> <?= ucfirst($app['app_status']) ?></span>
        </div>

        <!-- Progress bar -->
        <div class="prog-wrap">
            <div class="prog-info">
                <span>Progress: <?= $done_r ?>/<?= $total_r ?> rounds</span>
                <span><?= $prog_pct ?>% complete</span>
            </div>
            <div class="prog-track">
                <div class="prog-fill" style="width:<?= $prog_pct ?>%"></div>
            </div>
        </div>

        <!-- Rounds list -->
        <div class="rounds-wrap">
            <div class="rounds-title">📋 Round-by-Round Status</div>

            <?php foreach ($rounds as $idx => $r):
                $res     = $r['result']   ?? 'pending';
                $st      = strtolower($r['status'] ?? 'pending');
                $locked  = $r['is_locked'];
                $done    = $r['is_done'];
                $no_q    = $r['no_q'];
                $pct     = $r['score_pct'];
                $has_link= !empty($r['meeting_link']);

                // Row class
                if ($locked)              $rr_c = 'rr-locked';
                elseif ($done && $res==='pass') $rr_c = 'rr-pass';
                elseif ($done && $res==='fail') $rr_c = 'rr-fail';
                elseif ($done)            $rr_c = 'rr-review';
                elseif ($r['is_video'])   $rr_c = 'rr-video';
                else                      $rr_c = 'rr-active';

                // Step number circle
                if ($locked)              $sn_c = 'sn-locked';
                elseif ($done && $res==='pass') $sn_c = 'sn-pass';
                elseif ($done && $res==='fail') $sn_c = 'sn-fail';
                elseif ($done)            $sn_c = 'sn-review';
                elseif ($r['is_video'])   $sn_c = 'sn-video';
                else                      $sn_c = 'sn-active';

                $step_icon = $locked ? '🔒' : ($done&&$res==='pass'?'✅':($done&&$res==='fail'?'❌':($idx+1)));

                // Type
                if ($r['is_video'])         { $type_lbl='📹 Video'; $tc_c='tc-video'; }
                elseif($r['is_technical'])  { $type_lbl='💻 Technical'; $tc_c='tc-tech'; }
                else                        { $type_lbl='🤖 MCQ'; $tc_c='tc-mcq'; }

                // Status chip
                $is_sched = $r['is_scheduled_yet'] ?? true;
                if ($locked)              { $chip='🔒 Locked';        $chip_c='sc-locked'; }
                elseif($done&&$res==='pass'){$chip='🏆 Passed';       $chip_c='sc-pass'; }
                elseif($done&&$res==='fail'){$chip='❌ Failed';        $chip_c='sc-fail'; }
                elseif($done)             { $chip='⏳ Under Review';  $chip_c='sc-review'; }
                elseif(!$is_sched)        {
                    $sched_time = $r['scheduled_at'] ?? '';
                    $chip = '📅 ' . ($sched_time ? date('d M, h:i A', strtotime($sched_time)) : 'Scheduled');
                    $chip_c='sc-waiting';
                }
                elseif($no_q)             { $chip='⏳ Preparing';    $chip_c='sc-waiting'; }
                else                      { $chip='🔵 Ready';         $chip_c='sc-active'; }

                // Button
                if ($locked) {
                    $btn='🔒 Locked'; $btn_c='ab-locked'; $href='#';
                } elseif ($done) {
                    if ($r['is_video'])        { $btn='📹 View Details'; $btn_c='ab-view'; $href="take_video.php?round_id={$r['round_id']}"; }
                    elseif($r['is_technical']) { $btn='📊 View Result';  $btn_c='ab-view'; $href="take_technical.php?round_id={$r['round_id']}"; }
                    else                       { $btn='📊 View Result';  $btn_c='ab-view'; $href="take_mcq.php?round_id={$r['round_id']}"; }
                } elseif ($no_q) {
                    $btn='⏳ Waiting'; $btn_c='ab-waiting'; $href='#';
                } else {
                    if ($r['is_video'])        { $btn='📹 Join Call';  $btn_c='ab-video'; $href="take_video.php?round_id={$r['round_id']}"; }
                    elseif($r['is_technical']) { $btn='💻 Start';      $btn_c='ab-tech';  $href="take_technical.php?round_id={$r['round_id']}"; }
                    else                       { $btn='▶ Start Test';  $btn_c='ab-start'; $href="take_mcq.php?round_id={$r['round_id']}"; }
                }
            ?>
            <div class="round-row <?= $rr_c ?>">
                <!-- Step number -->
                <div class="step-num <?= $sn_c ?>"><?= $step_icon ?></div>

                <!-- Info -->
                <div class="round-info">
                    <div class="round-name"><?= htmlspecialchars($r['round_title']) ?></div>
                    <div class="round-meta">
                        <span class="type-chip <?= $tc_c ?>"><?= $type_lbl ?></span>
                        <?php if(!empty($r['scheduled_at'])): ?>
                        <span>📅 <?= date('d M Y', strtotime($r['scheduled_at'])) ?></span>
                        <?php endif; ?>
                        <?php if (!$r['is_video'] && !$locked): ?>
                        <span>📋 <?= $r['is_technical']?$r['tech_total']:$r['mcq_total'] ?> questions</span>
                        <?php endif; ?>
                        <?php if ($r['is_video'] && !$locked): ?>
                            <?php if ($has_link): ?>
                            <span class="link-ready">🔗 Link Ready</span>
                            <?php else: ?>
                            <span class="link-missing">⏳ Link Pending</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Score bar if available -->
                <?php if ($pct > 0 && !$locked): ?>
                <div class="score-inline">
                    <div class="si-track">
                        <div class="si-fill <?= $pct>=60?'sf-g':($pct>=40?'sf-o':'sf-r') ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span style="font-size:12px;font-weight:700;color:#0f1e46"><?= $pct ?>%</span>
                </div>
                <?php endif; ?>

                <!-- Status -->
                <span class="status-chip <?= $chip_c ?>"><?= $chip ?></span>

                <!-- Button -->
                <a class="act-btn <?= $btn_c ?>" href="<?= $href ?>"><?= $btn ?></a>
            </div>
            <?php endforeach; ?>

            <!-- Summary footer -->
            <div style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-top:8px;
                        display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;
                        font-size:13px;color:#64748b">
                <span>
                    ✅ <b><?= $done_r ?></b> completed &nbsp;|&nbsp;
                    ⏳ <b style="color:<?= $remaining>0?'#f59e0b':'#22c55e' ?>"><?= $remaining ?></b> remaining
                </span>
                <?php if ($remaining === 0 && $done_r > 0): ?>
                <span style="color:#16a34a;font-weight:700">🎉 All rounds completed!</span>
                <?php elseif ($remaining > 0): ?>
                <span><?= $remaining ?> round<?= $remaining!=1?'s':'' ?> left to complete</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>
