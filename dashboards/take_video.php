<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php"); exit();
}

$user_id  = (int)$_SESSION['id'];
$round_id = (int)($_GET['round_id'] ?? 0);
if (!$round_id) { header("Location: my_tests.php"); exit(); }

$round = $conn->query("
    SELECT ir.*, j.title AS job_title, a.id AS app_id
    FROM interview_rounds ir
    JOIN applications a ON ir.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE ir.id = $round_id AND a.user_id = $user_id
")->fetch_assoc();
if (!$round) { header("Location: my_tests.php"); exit(); }

$app_id = (int)$round['app_id'];
$video  = $conn->query("SELECT * FROM video_rounds WHERE round_id=$round_id LIMIT 1")->fetch_assoc();
$title  = !empty($round['round_title']) ? $round['round_title'] : (!empty($round['round_name']) ? $round['round_name'] : 'Video Interview');
$status = strtolower($round['status'] ?? 'pending');
$result = $round['result'] ?? 'pending';

// Round result from round_results table too
$rr = $conn->query("SELECT * FROM round_results WHERE round_id=$round_id AND application_id=$app_id LIMIT 1")->fetch_assoc();
if ($rr && $rr['result'] !== 'pending') $result = $rr['result'];

// All rounds for this application — for the progress tracker
$all_rounds = $conn->query("
    SELECT ir.id, ir.round_title, ir.round_name, ir.round_type, ir.status, ir.result,
           (SELECT result FROM round_results WHERE round_id=ir.id AND application_id=$app_id LIMIT 1) AS rr_result
    FROM interview_rounds ir
    WHERE ir.application_id = $app_id
    ORDER BY ir.id ASC
");
$rounds_list = [];
while ($r = $all_rounds->fetch_assoc()) {
    if (!empty($r['rr_result']) && $r['rr_result'] !== 'pending') $r['result'] = $r['rr_result'];
    $rounds_list[] = $r;
}
$total_rounds    = count($rounds_list);
$completed_rounds= count(array_filter($rounds_list, fn($r) => strtolower($r['status']) === 'completed' || $r['result'] !== 'pending'));
$current_index   = 0;
foreach ($rounds_list as $i => $r) { if ($r['id'] == $round_id) { $current_index = $i; break; } }

$platform_icons = ['google_meet'=>'🟢','zoom'=>'🔵','teams'=>'🟣','jitsi'=>'🟠'];
$platform_names = ['google_meet'=>'Google Meet','zoom'=>'Zoom','teams'=>'Microsoft Teams','jitsi'=>'Jitsi Meet'];
$plat     = $video['meeting_platform'] ?? 'google_meet';
$plat_ico = $platform_icons[$plat] ?? '📹';
$plat_nam = $platform_names[$plat] ?? 'Video Call';
$link     = $video['meeting_link'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial;background:linear-gradient(135deg,#0c4a6e,#0f172a);min-height:100vh;padding:30px 20px;color:white}
.container{max-width:780px;margin:0 auto}

/* Round Progress Tracker */
.tracker{background:rgba(255,255,255,.08);border-radius:16px;padding:20px 24px;margin-bottom:22px}
.tracker h3{font-size:13px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-bottom:16px}
.tracker-steps{display:flex;align-items:center;gap:0}
.tr-step{display:flex;flex-direction:column;align-items:center;min-width:80px}
.tr-circle{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;border:3px solid rgba(255,255,255,.2)}
.tr-pass{background:#16a34a;border-color:#22c55e}
.tr-fail{background:#dc2626;border-color:#ef4444}
.tr-active{background:#1e3a8a;border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.3)}
.tr-locked{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.15)}
.tr-label{font-size:10px;color:rgba(255,255,255,.6);margin-top:6px;text-align:center;max-width:70px;line-height:1.3}
.tr-connector{flex:1;height:3px;min-width:20px}
.tc-pass{background:#22c55e}
.tc-active{background:rgba(255,255,255,.2)}
.tc-locked{background:rgba(255,255,255,.1)}
.progress-info{display:flex;justify-content:space-between;margin-top:14px;font-size:12px;color:rgba(255,255,255,.6)}
.prog-bar-bg{background:rgba(255,255,255,.15);border-radius:20px;height:6px;margin-top:8px}
.prog-bar-fill{height:6px;border-radius:20px;background:linear-gradient(90deg,#3b82f6,#22c55e);transition:.5s}

/* Header card */
.header-card{background:rgba(255,255,255,.1);border-radius:16px;padding:22px 26px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.header-card h1{font-size:20px}
.header-card .meta{font-size:13px;opacity:.75;margin-top:4px}
.hbadge{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700}
.hb-blue{background:#bfdbfe;color:#1e40af}
.hb-green{background:#dcfce7;color:#16a34a}
.hb-red{background:#fee2e2;color:#dc2626}
.hb-yellow{background:#fef9c3;color:#854d0e}

/* Main cards */
.card{background:white;color:#111;border-radius:16px;padding:28px;margin-bottom:20px}

/* No link yet */
.no-link-card{background:rgba(255,255,255,.08);border-radius:16px;padding:36px;text-align:center;margin-bottom:20px;border:2px dashed rgba(255,255,255,.2)}
.no-link-card .icon{font-size:52px;margin-bottom:14px}
.no-link-card h2{font-size:20px;margin-bottom:8px}
.no-link-card p{font-size:14px;opacity:.7;line-height:1.7}

/* Meeting info card */
.meet-icon-big{font-size:52px;text-align:center;margin-bottom:10px}
.meet-title{font-size:22px;font-weight:800;color:#0c4a6e;text-align:center;margin-bottom:4px}
.meet-sub{text-align:center;font-size:13px;color:#6b7280;margin-bottom:22px}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}}
.info-item{background:#f8fafc;border-radius:10px;padding:12px 14px;border-left:3px solid #3b82f6}
.info-lbl{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.info-val{font-size:14px;font-weight:700;color:#0f172a}

/* Link display */
.link-box{background:#eff6ff;border:2px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.link-url{font-size:13px;color:#1d4ed8;word-break:break-all;flex:1;font-weight:600}
.copy-btn{background:#1e3a8a;color:white;border:none;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s}
.copy-btn:hover{background:#2563eb}

/* Join button */
.join-btn{display:block;background:linear-gradient(135deg,#059669,#10b981);color:white;text-decoration:none;padding:16px;border-radius:12px;text-align:center;font-size:17px;font-weight:800;margin-bottom:14px;transition:.2s}
.join-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.4);color:white}

/* Notes */
.notes-box{background:#fef9c3;border-radius:10px;padding:14px 16px;border-left:4px solid #f59e0b}
.notes-box h4{font-size:12px;font-weight:700;color:#854d0e;margin-bottom:6px;text-transform:uppercase}
.notes-box p{font-size:13px;color:#78350f;line-height:1.6}

/* Result card */
.result-card{border-radius:20px;padding:36px;text-align:center;margin-bottom:20px}
.rc-pass{background:white;border-top:8px solid #16a34a}
.rc-fail{background:white;border-top:8px solid #dc2626}
.rc-pending{background:white;border-top:8px solid #f59e0b}
.rc-icon{font-size:56px;margin-bottom:12px}
.rc-title{font-size:24px;font-weight:900;margin-bottom:8px}
.t-pass{color:#16a34a}.t-fail{color:#dc2626}.t-pending{color:#854d0e}
.rc-sub{color:#6b7280;font-size:14px;line-height:1.7}

/* Tips */
.tips-card{background:rgba(255,255,255,.06);border-radius:14px;padding:20px 22px}
.tips-card h3{font-size:14px;font-weight:700;margin-bottom:14px;color:rgba(255,255,255,.85)}
.tip{display:flex;gap:10px;margin-bottom:10px;font-size:13px;color:rgba(255,255,255,.7);align-items:flex-start}
.tip-icon{flex-shrink:0;font-size:16px}

.back-btn{display:inline-block;background:rgba(255,255,255,.15);color:white;padding:10px 22px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;margin-top:10px}
.back-btn:hover{background:rgba(255,255,255,.25);color:white}
</style>
</head>
<body>
<div class="container">

    <!-- ── Round Progress Tracker ─────────────────────────────────── -->
    <div class="tracker">
        <h3>🎯 Your Interview Journey</h3>
        <div class="tracker-steps">
        <?php foreach ($rounds_list as $i => $r):
            $rt  = strtolower($r['round_type'] ?? '');
            $rs  = strtolower($r['status'] ?? 'pending');
            $rres= $r['result'] ?? 'pending';
            $is_current = ($r['id'] == $round_id);
            $is_done = ($rs === 'completed' || $rres !== 'pending');

            if ($is_current)       $cc = 'tr-active';
            elseif ($rres==='pass') $cc = 'tr-pass';
            elseif ($rres==='fail') $cc = 'tr-fail';
            elseif ($is_done)      $cc = 'tr-pass';
            else                   $cc = 'tr-locked';

            $icon = in_array($rt,['hr','video_call','video','videocall']) ? '📹'
                  : ($rt==='technical' ? '💻' : '🤖');
            if ($rres==='pass') $icon = '✅';
            if ($rres==='fail') $icon = '❌';
            if ($is_current)   $icon = '▶';

            $lbl = !empty($r['round_title']) ? $r['round_title'] : ($r['round_name'] ?? "Round ".($i+1));
            if (strlen($lbl) > 10) $lbl = substr($lbl,0,9).'…';

            // Connector
            if ($i > 0) {
                $prev = $rounds_list[$i-1];
                $prev_done = strtolower($prev['status'])==='completed' || $prev['result'] !== 'pending';
                $conn_c = $prev_done ? 'tc-pass' : 'tc-locked';
                echo "<div class='tr-connector $conn_c'></div>";
            }
        ?>
            <div class="tr-step">
                <div class="tr-circle <?= $cc ?>"><?= $icon ?></div>
                <div class="tr-label"><?= htmlspecialchars($lbl) ?></div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="progress-info">
            <span>Round <?= $current_index+1 ?> of <?= $total_rounds ?></span>
            <span><?= $completed_rounds ?>/<?= $total_rounds ?> completed</span>
        </div>
        <div class="prog-bar-bg">
            <div class="prog-bar-fill" style="width:<?= $total_rounds>0?round(($completed_rounds/$total_rounds)*100):0 ?>%"></div>
        </div>
    </div>

    <!-- ── Header ─────────────────────────────────────────────────── -->
    <div class="header-card">
        <div>
            <h1>📹 <?= htmlspecialchars($title) ?></h1>
            <div class="meta">💼 <?= htmlspecialchars($round['job_title']) ?> &nbsp;|&nbsp; Round <?= $current_index+1 ?> of <?= $total_rounds ?></div>
        </div>
        <?php
        if ($result === 'pass')         echo '<span class="hbadge hb-green">🏆 Passed</span>';
        elseif ($result === 'fail')     echo '<span class="hbadge hb-red">❌ Failed</span>';
        elseif ($status === 'completed')echo '<span class="hbadge hb-yellow">⏳ Under Review</span>';
        elseif (!empty($link))          echo '<span class="hbadge hb-blue">📹 Ready to Join</span>';
        else                            echo '<span class="hbadge hb-yellow">⏳ Awaiting Link</span>';
        ?>
    </div>

    <?php
    // ── CASE 1: Result declared ───────────────────────────────────
    if ($result === 'pass' || $result === 'fail'):
    ?>
    <div class="result-card <?= $result==='pass'?'rc-pass':'rc-fail' ?>">
        <div class="rc-icon"><?= $result==='pass'?'🏆':'😔' ?></div>
        <div class="rc-title <?= $result==='pass'?'t-pass':'t-fail' ?>">
            <?= $result==='pass' ? 'You Passed the Video Interview!' : 'Better Luck Next Time' ?>
        </div>
        <div class="rc-sub">
            <?= $result==='pass'
                ? 'Congratulations! HR has cleared you for the next stage.'
                : 'Unfortunately you were not selected. Keep trying!' ?>
        </div>
        <br>
        <a href="my_tests.php" class="back-btn">← Back to My Tests</a>
        <a href="my_rounds.php" class="back-btn">🎯 View Full Journey</a>
    </div>

    <?php
    // ── CASE 2: No link yet ───────────────────────────────────────
    elseif (!$video || empty($link)):
    ?>
    <div class="no-link-card">
        <div class="icon">⏳</div>
        <h2>Meeting Link Not Set Yet</h2>
        <p>HR will add the video call link soon.<br>Please check back later — the link will appear here automatically.</p>
        <br>
        <a href="my_tests.php" class="back-btn">← Back to My Tests</a>
    </div>

    <?php
    // ── CASE 3: Link available — show meeting details ─────────────
    else:
    ?>
    <div class="card">
        <div class="meet-icon-big"><?= $plat_ico ?></div>
        <div class="meet-title"><?= $plat_nam ?> Interview</div>
        <div class="meet-sub">Your video interview is scheduled. Join using the link below.</div>

        <!-- Info grid -->
        <div class="info-grid">
            <?php if (!empty($video['interviewer_name'])): ?>
            <div class="info-item">
                <div class="info-lbl">👤 Interviewer</div>
                <div class="info-val"><?= htmlspecialchars($video['interviewer_name']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($round['scheduled_at'])): ?>
            <div class="info-item">
                <div class="info-lbl">📅 Date & Time</div>
                <div class="info-val"><?= date('d M Y, h:i A', strtotime($round['scheduled_at'])) ?></div>
            </div>
            <?php endif; ?>
            <?php $dur = $video['duration_minutes'] ?? $video['duration'] ?? ''; if (!empty($dur)): ?>
            <div class="info-item">
                <div class="info-lbl">⏱️ Duration</div>
                <div class="info-val"><?= htmlspecialchars($dur) ?> minutes</div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-lbl">📱 Platform</div>
                <div class="info-val"><?= $plat_ico ?> <?= $plat_nam ?></div>
            </div>
        </div>

        <!-- Meeting link box -->
        <div class="link-box">
            <div class="link-url">🔗 <?= htmlspecialchars($link) ?></div>
            <button class="copy-btn" onclick="copyLink('<?= htmlspecialchars($link, ENT_QUOTES) ?>', this)">📋 Copy Link</button>
        </div>

        <!-- Join button -->
        <a href="<?= htmlspecialchars($link) ?>" target="_blank" class="join-btn">
            🚀 Join Meeting Now
        </a>

        <!-- Notes -->
        <?php $notes_val = $video['meeting_notes'] ?? $video['notes'] ?? ''; if (!empty($notes_val)): ?>
        <div class="notes-box">
            <h4>📋 HR Instructions</h4>
            <p><?= nl2br(htmlspecialchars($notes_val)) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tips -->
    <div class="tips-card">
        <h3>💡 Video Interview Tips</h3>
        <div class="tip"><span class="tip-icon">🌐</span><span>Test your internet connection before joining</span></div>
        <div class="tip"><span class="tip-icon">🎤</span><span>Check microphone and camera beforehand</span></div>
        <div class="tip"><span class="tip-icon">💡</span><span>Sit in a well-lit, quiet place</span></div>
        <div class="tip"><span class="tip-icon">👔</span><span>Dress professionally for the interview</span></div>
        <div class="tip"><span class="tip-icon">⏰</span><span>Join 5 minutes early to avoid technical issues</span></div>
    </div>

    <?php endif; ?>

    <br>
    <a href="my_tests.php" class="back-btn">← Back to My Tests</a>

</div>

<script>
function copyLink(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        btn.textContent = '✅ Copied!';
        btn.style.background = '#16a34a';
        setTimeout(() => { btn.textContent = '📋 Copy Link'; btn.style.background = '#1e3a8a'; }, 2000);
    });
}
</script>
</body>
</html>
